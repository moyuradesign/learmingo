<?php
declare(strict_types=1);

use Learmingo\Core\Auth;
use Learmingo\Core\Csrf;
use Learmingo\Core\Database;
use Learmingo\Core\Response;
use Learmingo\Core\RateLimiter;
use Learmingo\Core\Security;
use Learmingo\Core\View;

$router = $app->getRouter();
$render = static function (string $view, array $data = []): void { View::render($view, $data); };
$input = static function (string $key, int $maxBytes = 4096): string { $value = $_POST[$key] ?? ''; return is_string($value) && strlen($value) <= $maxBytes ? $value : ''; };
$query = static function (string $key, int $maxBytes = 250): string { $value = $_GET[$key] ?? ''; return is_string($value) && strlen($value) <= $maxBytes ? trim($value) : ''; };
$requireAuth = static function (): array { $user = Auth::user(); if ($user === null) { Response::redirect('/login'); } return $user; };
$requireRole = static function (string ...$roles): array { $user = Auth::user(); if ($user === null) { Response::redirect('/login'); } if (!in_array($user['role'] ?? '', $roles, true)) { Response::html('Forbidden',403); exit; } return $user; };

$router->get('/', static function () use ($render): void {
    $courses = [];
    try { $courses = Database::connection()->query("SELECT c.id,c.title,c.slug,c.description,c.price,u.name instructor FROM lm_courses c INNER JOIN lm_users u ON u.id=c.instructor_id WHERE c.status='published' ORDER BY c.created_at DESC LIMIT 6")->fetchAll(); } catch (Throwable) {}
    $render('home', ['courses' => $courses]);
});
$router->get('/courses', static function () use ($render): void {
    $search = trim((string)($_GET['q'] ?? '')); $sql = "SELECT c.id,c.title,c.slug,c.description,c.price,u.name instructor FROM lm_courses c INNER JOIN lm_users u ON u.id=c.instructor_id WHERE c.status='published'"; $params=[];
    if ($search !== '') { $sql .= ' AND (c.title LIKE :search OR c.description LIKE :search)'; $params['search']='%'.$search.'%'; } $sql .= ' ORDER BY c.created_at DESC'; $s=Database::connection()->prepare($sql); $s->execute($params); $render('courses/index',['courses'=>$s->fetchAll(),'search'=>$search]);
});
$router->get('/course', static function () use ($render): void { $s=Database::connection()->prepare("SELECT c.*,u.name instructor FROM lm_courses c INNER JOIN lm_users u ON u.id=c.instructor_id WHERE c.slug=:slug AND c.status='published' LIMIT 1"); $s->execute(['slug'=>trim((string)($_GET['slug']??''))]); $course=$s->fetch(); if(!$course){Response::html('Course not found',404);return;} $render('courses/show',['course'=>$course]); });

$router->get('/login', static function () use ($render): void { if (Auth::user() !== null) { Response::redirect('/dashboard'); } $render('auth/login',['error'=>null]); });

$router->post('/login', static function () use ($render,$input): void {
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Session expired. Reload and try again.',419); return; }
    $email = strtolower(trim($input('email',190)));
    $password = $input('password',4096);
    try {
        $ipWait = RateLimiter::hit('login-ip',Security::clientIp(),30,900);
        $accountWait = RateLimiter::hit('login-account',$email,6,900);
    } catch (Throwable $e) {
        error_log('Learmingo login throttling unavailable: '.get_class($e));
        Response::html('Sign in temporarily unavailable.',503);
        return;
    }
    if ($ipWait > 0 || $accountWait > 0) { Response::tooManyRequests(max($ipWait,$accountWait)); return; }
    if (filter_var($email,FILTER_VALIDATE_EMAIL) && $password !== '' && Auth::attempt($email,$password)) {
        RateLimiter::clear('login-account',$email);
        Response::redirect('/dashboard');
    }
    $render('auth/login',['error'=>'Invalid email or password.']);
});

$router->get('/register', static function () use ($render): void { if (Auth::user() !== null) { Response::redirect('/dashboard'); } $render('auth/register',['error'=>null]); });

$router->post('/register', static function () use ($render,$input): void {
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Session expired. Reload and try again.',419); return; }
    try { $wait = RateLimiter::hit('register-ip',Security::clientIp(),5,3600); }
    catch (Throwable $e) { error_log('Learmingo registration throttling unavailable: '.get_class($e)); Response::html('Registration temporarily unavailable.',503); return; }
    if ($wait > 0) { Response::tooManyRequests($wait); return; }
    $name = trim($input('name',150));
    $email = strtolower(trim($input('email',190)));
    $password = $input('password',4096);
    if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || $password !== $input('password_confirmation',4096)) {
        $render('auth/register',['error'=>'Enter a valid name, email and matching password of at least 12 characters.']);
        return;
    }
    try {
        $db = Database::connection();
        $role = (int)$db->query("SELECT id FROM lm_roles WHERE slug='student' LIMIT 1")->fetchColumn();
        if ($role < 1) { throw new RuntimeException('Student role missing.'); }
        $statement = $db->prepare('INSERT INTO lm_users (role_id,name,email,password) VALUES (:role,:name,:email,:password)');
        $statement->execute(['role'=>$role,'name'=>$name,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]);
        Auth::attempt($email,$password);
        Response::redirect('/dashboard');
    } catch (Throwable $e) {
        error_log('Learmingo registration failed: '.get_class($e));
        $render('auth/register',['error'=>'Unable to register this account. The email may already be used.']);
    }
});

$router->post('/logout', static function (): void { if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request',419); return; } Auth::logout(); Response::redirect('/'); });

$router->get('/dashboard', static function () use ($requireAuth,$render): void { $user=$requireAuth(); $s=Database::connection()->prepare('SELECT e.progress,e.status,c.title,c.slug FROM lm_enrollments e INNER JOIN lm_courses c ON c.id=e.course_id WHERE e.student_id=:id AND e.status<>'cancelled' ORDER BY e.created_at DESC');$s->execute(['id'=>$user['id']]);$render('dashboard/index',['enrollments'=>$s->fetchAll()]); });
$router->post('/enroll', static function () use ($requireAuth): void {
    $user = $requireAuth();
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request',419); return; }
    $id = filter_var($_POST['course_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id) { Response::html('Invalid course',422); return; }
    // Paid checkout is not implemented; never grant paid lessons without verified payment.
    $s = Database::connection()->prepare("INSERT IGNORE INTO lm_enrollments (course_id,student_id) SELECT id,:student FROM lm_courses WHERE id=:course AND status='published' AND price=0");
    $s->execute(['student'=>$user['id'],'course'=>$id]);
    if ($s->rowCount() === 0) {
        $existing = Database::connection()->prepare("SELECT id FROM lm_enrollments WHERE course_id=:course AND student_id=:student AND status<>'cancelled' LIMIT 1");
        $existing->execute(['student'=>$user['id'],'course'=>$id]);
        if (!$existing->fetch()) { Response::html('This course is not available for free enrollment.',403); return; }
    }
    Response::redirect('/dashboard');
});

$router->get('/admin', static function () use ($requireRole,$render): void { $requireRole('admin');$db=Database::connection();$stats=['users'=>(int)$db->query('SELECT COUNT(*) FROM lm_users')->fetchColumn(),'courses'=>(int)$db->query('SELECT COUNT(*) FROM lm_courses')->fetchColumn(),'enrollments'=>(int)$db->query('SELECT COUNT(*) FROM lm_enrollments')->fetchColumn(),'published'=>(int)$db->query("SELECT COUNT(*) FROM lm_courses WHERE status='published'")->fetchColumn()];$render('admin/dashboard',['stats'=>$stats]); });
$router->get('/admin/users', static function () use ($requireRole,$render): void {
    $requireRole('admin');
    $users = Database::connection()->query('SELECT u.id,u.name,u.email,u.status,r.name role,r.slug role_slug FROM lm_users u INNER JOIN lm_roles r ON r.id=u.role_id ORDER BY u.created_at DESC LIMIT 500')->fetchAll();
    $render('admin/users',['users'=>$users]);
});

$router->post('/admin/users/status', static function () use ($requireRole): void {
    $actor = $requireRole('admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request',419); return; }
    $id = filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id || $id === (int)$actor['id']) { Response::html('This account cannot be changed here.',403); return; }
    // Protect all administrators, including the last active administrator.
    $s = Database::connection()->prepare("UPDATE lm_users u INNER JOIN lm_roles r ON r.id=u.role_id SET u.status=CASE WHEN u.status='active' THEN 'inactive' ELSE 'active' END WHERE u.id=:id AND r.slug<>'admin'");
    $s->execute(['id'=>$id]);
    if ($s->rowCount() !== 1) { Response::html('Account not found or protected.',403); return; }
    Response::redirect('/admin/users');
});

$router->get('/instructor', static function () use ($requireRole,$render): void { $user=$requireRole('instructor','admin');$s=Database::connection()->prepare('SELECT id,title,slug,price,status FROM lm_courses WHERE instructor_id=:id ORDER BY created_at DESC');$s->execute(['id'=>$user['id']]);$render('instructor/dashboard',['courses'=>$s->fetchAll()]); });
$router->get('/instructor/courses/create', static function () use ($requireRole,$render): void { $requireRole('instructor','admin');$render('instructor/course-form',['course'=>null,'error'=>null]); });
$router->post('/instructor/courses/store', static function () use ($requireRole,$render,$input): void {
    $user = $requireRole('instructor','admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request',419); return; }
    $title = trim($input('title',200));
    $description = trim($input('description',50000));
    $rawPrice = trim($input('price',32));
    $slug = strtolower(trim($input('slug',220)));
    if ($slug === '') { $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$title),'-')); }
    if ($title === '' || $slug === '' || strlen($slug) > 220 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)
        || !is_numeric($rawPrice) || !is_finite((float)$rawPrice) || (float)$rawPrice < 0 || (float)$rawPrice > 9999999999.99) {
        $render('instructor/course-form',['course'=>null,'error'=>'Enter a valid title, slug and price.']);
        return;
    }
    try {
        $s = Database::connection()->prepare('INSERT INTO lm_courses (instructor_id,title,slug,description,price,status) VALUES (:instructor,:title,:slug,:description,:price,:status)');
        $s->execute(['instructor'=>$user['id'],'title'=>$title,'slug'=>$slug,'description'=>$description,'price'=>round((float)$rawPrice,2),'status'=>'draft']);
        Response::redirect('/instructor/course?course=' . (int)Database::connection()->lastInsertId());
    } catch (Throwable $e) {
        error_log('Learmingo course creation failed: '.get_class($e));
        $render('instructor/course-form',['course'=>null,'error'=>'Unable to create course. The slug may already exist.']);
    }
});

$router->get('/learn', static function () use ($requireAuth, $render): void {
    $user = $requireAuth();
    $slug = trim((string) ($_GET['course'] ?? ''));
    $db = Database::connection();
    $statement = $db->prepare("SELECT c.id,c.title,c.description,e.progress FROM lm_enrollments e INNER JOIN lm_courses c ON c.id=e.course_id WHERE e.student_id=:student AND c.slug=:slug AND e.status <> 'cancelled' LIMIT 1");
    $statement->execute(['student' => $user['id'], 'slug' => $slug]);
    $course = $statement->fetch();
    if (!$course) {
        Response::html('Course not found or access denied', 404);
        return;
    }
    $lessonsQuery = $db->prepare('SELECT s.title section_title,l.id,l.title,l.content,l.video_url,lp.completed_at FROM lm_course_sections s INNER JOIN lm_lessons l ON l.section_id=s.id LEFT JOIN lm_lesson_progress lp ON lp.lesson_id=l.id AND lp.student_id=:student WHERE s.course_id=:course ORDER BY s.sort_order,s.id,l.sort_order,l.id');
    $lessonsQuery->execute(['course' => $course['id'], 'student' => $user['id']]);
    $render('dashboard/learn', ['course' => $course, 'lessons' => $lessonsQuery->fetchAll()]);
});

// Learning core workflows live in a separate HTTP adapter.
require __DIR__ . '/learning.php';
