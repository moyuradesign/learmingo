<?php
declare(strict_types=1);
// Included from web.php. PHP imports are file-scoped.
use Learmingo\Core\Auth;
use Learmingo\Core\Csrf;
use Learmingo\Core\Database;
use Learmingo\Core\Response;
use Learmingo\Services\FrontendSettings;
use Learmingo\Services\CataloguePreview;

$router->get('/categories', static function () use ($render): void {
    $rows = Database::connection()->query("SELECT cat.name,cat.slug,COUNT(c.id) course_count FROM lm_course_categories cat
       LEFT JOIN lm_courses c ON c.category_id=cat.id AND c.status='published'
       GROUP BY cat.id,cat.name,cat.slug ORDER BY cat.name ASC LIMIT 100")->fetchAll(\PDO::FETCH_ASSOC);
    if ((int)Database::connection()->query("SELECT COUNT(*) FROM lm_courses WHERE status='published'")->fetchColumn() === 0) { $rows = CataloguePreview::categories(); }
    $render('frontend/categories', ['categories' => $rows]);
});

// Replace the catalogue read route only; preserve the application's existing write operations.
$router->get('/courses', static function () use ($render): void {
    $search = $_GET['q'] ?? ''; $search = is_string($search) ? trim(substr($search, 0, 150)) : '';
    $category = $_GET['category'] ?? ''; $category = is_string($category) ? trim(substr($category, 0, 180)) : '';
    if ($category !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $category)) { $category = ''; }
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>1000]]) ?: 1;
    $categories = Database::connection()->query("SELECT cat.name,cat.slug,COUNT(c.id) total FROM lm_course_categories cat
        LEFT JOIN lm_courses c ON c.category_id=cat.id AND c.status='published'
        GROUP BY cat.id,cat.name,cat.slug ORDER BY cat.name LIMIT 100")->fetchAll(\PDO::FETCH_ASSOC);
    $where = " FROM lm_courses c INNER JOIN lm_users u ON u.id=c.instructor_id LEFT JOIN lm_course_categories cat ON cat.id=c.category_id WHERE c.status='published'";
    $values = [];
    if ($search !== '') {
        $where .= ' AND (c.title LIKE :q OR c.description LIKE :q2)';
        $values['q'] = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search) . '%';
        $values['q2'] = $values['q'];
    }
    if ($category !== '') { $where .= ' AND cat.slug=:category'; $values['category'] = $category; }
    $db = Database::connection();
    $counter = $db->prepare('SELECT COUNT(*)' . $where);
    $counter->execute($values);
    $total = (int) $counter->fetchColumn();
    $limit = 12; $page = min($page, max(1, (int)ceil($total / $limit)));
    $sql = 'SELECT c.id,c.title,c.slug,c.description,c.price,u.name instructor,cat.name category_name'
        . $where . ' ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset';
    $statement = $db->prepare($sql);
    foreach ($values as $key => $value) { $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR); }
    $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $limit, \PDO::PARAM_INT);
    $statement->execute();
    $rows=$statement->fetchAll(\PDO::FETCH_ASSOC); $demoMode=false;
    if (($_GET['preview'] ?? '') === '1' || (int)$db->query("SELECT COUNT(*) FROM lm_courses WHERE status='published'")->fetchColumn()===0) {
        $demoMode=true; $categories=CataloguePreview::categories();
        $rows=CataloguePreview::filter($search,$category);$total=count($rows);$page=1;
    }
    $render('courses/index', ['courses'=>$rows,'search'=>$search, 'previewMode'=>$demoMode,
        'category'=>$category, 'categories'=>$categories, 'page'=>$page, 'pages'=>(int)ceil($total/$limit), 'total'=>$total]);
});

$router->get('/dashboard/appearance', static function () use ($render): void {
    $user = Auth::user();
    if (!$user) { Response::redirect('/login'); return; }
    if (($user['role'] ?? '') !== 'admin') { Response::html('Forbidden', 403); return; }
    $render('frontend/appearance', ['settings' => FrontendSettings::all(), 'saved' => ($_GET['saved'] ?? '') === '1', 'error' => '']);
});
$router->post('/dashboard/appearance', static function () use ($render): void {
    $user = Auth::user();
    if (!$user) { Response::redirect('/login'); return; }
    if (($user['role'] ?? '') !== 'admin') { Response::html('Forbidden', 403); return; }
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    try {
        FrontendSettings::save($_POST);
        Response::redirect('/dashboard/appearance?saved=1');
    } catch (\InvalidArgumentException | \RuntimeException $exception) {
        http_response_code(422);
        $render('frontend/appearance', ['settings'=>array_replace(FrontendSettings::all(), array_intersect_key(array_filter($_POST, 'is_string'), FrontendSettings::defaults())),
            'saved'=>false,'error'=>$exception->getMessage()]);
    }
});


// LEARMINGO_UI_ASSET_BRIDGE_V102: unguessable filesystem paths are never accepted from the request.
// Extensionless paths intentionally avoid Nginx regex rules for *.css and *.js.
$router->get('/learmingo-ui/style', static function (): void {
    $asset = dirname(__DIR__) . '/app/Services/learmingo-ui-v102.css';
    if (!is_file($asset) || is_link($asset) || !is_readable($asset)) {
        \Learmingo\Core\Response::html('Frontend stylesheet unavailable', 503);
        return;
    }
    header('Content-Type: text/css; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    readfile($asset);
});
$router->get('/learmingo-ui/script', static function (): void {
    $asset = dirname(__DIR__) . '/app/Services/learmingo-ui-v102.js';
    if (!is_file($asset) || is_link($asset) || !is_readable($asset)) {
        \Learmingo\Core\Response::html('Frontend script unavailable', 503);
        return;
    }
    header('Content-Type: text/javascript; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    readfile($asset);
});

// LEARMINGO_DEMO_CATALOGUE_V103 — no database writes, no enrollment.
$router->get('/learmingo-course-cover', static function (): void {
    $slug=$_GET['slug'] ?? '';
    if (!is_string($slug) || strlen($slug)>220 || !preg_match('/^[a-z0-9-]+$/D',$slug)) {
        Response::html('Image not found',404);return;
    }
    $file=CataloguePreview::image($slug);
    if ($file===null) {
        // Real courses without a configured cover get a standard neutral vector cover.
        $file=__DIR__.'/../app/Services/CoursePreviewCovers/course-default.svg';
        if (!is_file($file) || is_link($file)) { Response::html('Image not found',404);return; }
    }
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; style-src 'none'; sandbox");
    readfile($file);
});
// LEARMINGO_COURSE_FIRST_HOME_V104: only read published data; virtual courses never stored.
$router->get('/learmingo-hero-art', static function (): void {
    $path = dirname(__DIR__) . '/app/Services/learmingo-hero-v104.webp';
    if (!is_file($path) || is_link($path) || !is_readable($path)) {
        Response::html('Hero artwork unavailable', 503); return;
    }
    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
});
$router->get('/', static function () use ($render): void {
    $db = Database::connection();
    $rows = $db->query("SELECT c.id,c.title,c.slug,c.description,c.price,u.name instructor,cat.name category_name
        FROM lm_courses c INNER JOIN lm_users u ON u.id=c.instructor_id
        LEFT JOIN lm_course_categories cat ON cat.id=c.category_id
        WHERE c.status='published' ORDER BY c.created_at DESC LIMIT 10")
        ->fetchAll(\PDO::FETCH_ASSOC);
    $previewMode = false;
    if (!$rows) { $previewMode = true; $rows = CataloguePreview::all(); }
    if ($previewMode) {
        $categories = CataloguePreview::categories();
    } else {
        $categories = $db->query("SELECT cat.name,cat.slug,COUNT(c.id) course_count
           FROM lm_course_categories cat INNER JOIN lm_courses c
             ON c.category_id=cat.id AND c.status='published'
           GROUP BY cat.id,cat.name,cat.slug ORDER BY course_count DESC, cat.name ASC LIMIT 10")
          ->fetchAll(\PDO::FETCH_ASSOC);
    }
    $render('home', ['courses'=>$rows, 'previewMode'=>$previewMode,'categories'=>$categories]);
});

$router->get('/course', static function () use($render):void {
    $slug=$_GET['slug']??'';
    if (!is_string($slug) || strlen($slug)>220 || !preg_match('/^[a-z0-9-]+$/D',$slug)) {
        Response::html('Course not found',404);return;
    }
    $preview=CataloguePreview::find($slug);
    if ($preview!==null) {
        $render('courses/show',['course'=>$preview]);return;
    }
    $s=Database::connection()->prepare("SELECT c.*,u.name instructor FROM lm_courses c
        INNER JOIN lm_users u ON u.id=c.instructor_id WHERE c.slug=:slug AND c.status='published' LIMIT 1");
    $s->execute(['slug'=>$slug]);$course=$s->fetch();
    if (!$course) { Response::html('Course not found',404);return; }
    $render('courses/show',['course'=>$course]);
});
