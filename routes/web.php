<?php
declare(strict_types=1);

use Learmingo\Core\Auth;
use Learmingo\Core\Csrf;
use Learmingo\Core\Database;
use Learmingo\Core\Response;
use Learmingo\Core\View;

$router = $app->getRouter();
$render = static function (string $view, array $data = []): void { View::render($view, $data); };
$requireAuth = static function (): array { if (!Auth::check()) { Response::redirect('/login'); } return Auth::user() ?? []; };
$requireRole = static function (string ...$roles): array { if (!Auth::check() || !Auth::role(...$roles)) { Response::html('Forbidden', 403); exit; } return Auth::user() ?? []; };

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

$router->get('/login', static function () use ($render): void { $render('auth/login',['error'=>null]); });
$router->post('/login', static function () use ($render): void { if(!Csrf::verify($_POST['_token']??null)){ $render('auth/login',['error'=>'Your session expired.']); return; } if(Auth::attempt((string)($_POST['email']??''),(string)($_POST['password']??''))){Response::redirect('/dashboard');} $render('auth/login',['error'=>'Invalid credentials.']); });
$router->get('/register', static function () use ($render): void { $render('auth/register',['error'=>null]); });
$router->post('/register', static function () use ($render): void { if(!Csrf::verify($_POST['_token']??null)){ $render('auth/register',['error'=>'Your session expired.']); return; } $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??''); if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||$password!==($_POST['password_confirmation']??'')){ $render('auth/register',['error'=>'Enter valid details and a matching password of at least 8 characters.']); return; } $db=Database::connection(); $role=(int)$db->query("SELECT id FROM lm_roles WHERE slug='student' LIMIT 1")->fetchColumn(); try{$s=$db->prepare('INSERT INTO lm_users (role_id,name,email,password) VALUES (:role,:name,:email,:password)');$s->execute(['role'=>$role,'name'=>$name,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]); Auth::attempt($email,$password); Response::redirect('/dashboard');}catch(Throwable){$render('auth/register',['error'=>'This email may already be registered.']);} });
$router->post('/logout', static function (): void { Auth::logout(); Response::redirect('/'); });

$router->get('/dashboard', static function () use ($requireAuth,$render): void { $user=$requireAuth(); $s=Database::connection()->prepare('SELECT e.progress,e.status,c.title,c.slug FROM lm_enrollments e INNER JOIN lm_courses c ON c.id=e.course_id WHERE e.student_id=:id ORDER BY e.created_at DESC');$s->execute(['id'=>$user['id']]);$render('dashboard/index',['enrollments'=>$s->fetchAll()]); });
$router->post('/enroll', static function () use ($requireAuth): void { $user=$requireAuth(); if(!Csrf::verify($_POST['_token']??null)){Response::html('Invalid request',419);return;} $course=(int)($_POST['course_id']??0); $s=Database::connection()->prepare("INSERT IGNORE INTO lm_enrollments (course_id,student_id) SELECT id,:student FROM lm_courses WHERE id=:course AND status='published'");$s->execute(['student'=>$user['id'],'course'=>$course]);Response::redirect('/dashboard'); });

$router->get('/admin', static function () use ($requireRole,$render): void { $requireRole('admin');$db=Database::connection();$stats=['users'=>(int)$db->query('SELECT COUNT(*) FROM lm_users')->fetchColumn(),'courses'=>(int)$db->query('SELECT COUNT(*) FROM lm_courses')->fetchColumn(),'enrollments'=>(int)$db->query('SELECT COUNT(*) FROM lm_enrollments')->fetchColumn(),'published'=>(int)$db->query("SELECT COUNT(*) FROM lm_courses WHERE status='published'")->fetchColumn()];$render('admin/dashboard',['stats'=>$stats]); });
$router->get('/admin/users', static function () use ($requireRole,$render): void { $requireRole('admin');$users=Database::connection()->query('SELECT u.id,u.name,u.email,u.status,r.name role FROM lm_users u INNER JOIN lm_roles r ON r.id=u.role_id ORDER BY u.created_at DESC')->fetchAll();$render('admin/users',['users'=>$users]); });
$router->post('/admin/users/status', static function () use ($requireRole): void { $requireRole('admin');if(!Csrf::verify($_POST['_token']??null)){Response::html('Invalid request',419);return;}$s=Database::connection()->prepare("UPDATE lm_users SET status=CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id=:id");$s->execute(['id'=>(int)($_POST['id']??0)]);Response::redirect('/admin/users'); });

$router->get('/instructor', static function () use ($requireRole,$render): void { $user=$requireRole('instructor','admin');$s=Database::connection()->prepare('SELECT id,title,slug,price,status FROM lm_courses WHERE instructor_id=:id ORDER BY created_at DESC');$s->execute(['id'=>$user['id']]);$render('instructor/dashboard',['courses'=>$s->fetchAll()]); });
$router->get('/instructor/courses/create', static function () use ($requireRole,$render): void { $requireRole('instructor','admin');$render('instructor/course-form',['course'=>null,'error'=>null]); });
$router->post('/instructor/courses/store', static function () use ($requireRole,$render): void { $user=$requireRole('instructor','admin');if(!Csrf::verify($_POST['_token']??null)){ $render('instructor/course-form',['course'=>null,'error'=>'Invalid request.']);return; }$title=trim((string)($_POST['title']??''));$description=trim((string)($_POST['description']??''));$price=max(0,(float)($_POST['price']??0));$slug=trim((string)($_POST['slug']??''));$slug=$slug!==''?$slug:strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $title),'-'));if($title===''||$slug===''){ $render('instructor/course-form',['course'=>null,'error'=>'Title and slug are required.']);return; }$s=Database::connection()->prepare('INSERT INTO lm_courses (instructor_id,title,slug,description,price,status) VALUES (:instructor,:title,:slug,:description,:price,:status)');try{$s->execute(['instructor'=>$user['id'],'title'=>$title,'slug'=>$slug,'description'=>$description,'price'=>$price,'status'=>Auth::role('admin')?'published':'pending']);Response::redirect('/instructor');}catch(Throwable){$render('instructor/course-form',['course'=>null,'error'=>'The slug must be unique.']);} });

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
    $lessonsQuery = $db->prepare('SELECT s.title section_title,l.id,l.title,l.content,l.video_url FROM lm_course_sections s INNER JOIN lm_lessons l ON l.section_id=s.id WHERE s.course_id=:course ORDER BY s.sort_order,s.id,l.sort_order,l.id');
    $lessonsQuery->execute(['course' => $course['id']]);
    $render('dashboard/learn', ['course' => $course, 'lessons' => $lessonsQuery->fetchAll()]);
});
