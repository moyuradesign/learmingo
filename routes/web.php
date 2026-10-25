<?php
declare(strict_types=1);

use Learmingo\Core\Auth;
use Learmingo\Core\Csrf;
use Learmingo\Core\Database;
use Learmingo\Core\Response;
use Learmingo\Core\View;

$router = $app->getRouter();

$render = static function (string $view, array $data = []): void {
    View::render($view, $data);
};

$requireAuth = static function (): ?array {
    if (!Auth::check()) {
        Response::redirect('/login');
    }
    return Auth::user();
};

$requireRole = static function (string ...$roles): array {
    if (!Auth::check() || !Auth::role(...$roles)) {
        Response::html('Forbidden', 403);
        exit;
    }
    return Auth::user() ?? [];
};

$router->get('/', static function () use ($render): void {
    $courses = [];
    try {
        $courses = Database::connection()->query("SELECT c.id, c.title, c.slug, c.description, c.price, u.name AS instructor FROM lm_courses c INNER JOIN lm_users u ON u.id = c.instructor_id WHERE c.status = 'published' ORDER BY c.created_at DESC LIMIT 6")->fetchAll();
    } catch (Throwable) {}
    $render('home', ['courses' => $courses]);
});

$router->get('/courses', static function () use ($render): void {
    $search = trim((string)($_GET['q'] ?? ''));
    $sql = "SELECT c.id, c.title, c.slug, c.description, c.price, u.name AS instructor FROM lm_courses c INNER JOIN lm_users u ON u.id = c.instructor_id WHERE c.status = 'published'";
    $params = [];
    if ($search !== '') { $sql .= ' AND (c.title LIKE :search OR c.description LIKE :search)'; $params['search'] = '%' . $search . '%'; }
    $sql .= ' ORDER BY c.created_at DESC';
    $statement = Database::connection()->prepare($sql); $statement->execute($params);
    $render('courses/index', ['courses' => $statement->fetchAll(), 'search' => $search]);
});

$router->get('/course', static function () use ($render): void {
    $slug = trim((string)($_GET['slug'] ?? ''));
    $statement = Database::connection()->prepare("SELECT c.*, u.name AS instructor FROM lm_courses c INNER JOIN lm_users u ON u.id = c.instructor_id WHERE c.slug = :slug AND c.status = 'published' LIMIT 1");
    $statement->execute(['slug' => $slug]);
    $course = $statement->fetch();
    if (!$course) { Response::html('Course not found', 404); return; }
    $render('courses/show', ['course' => $course]);
});

$router->get('/login', static function () use ($render): void { $render('auth/login', ['error' => null]); });
$router->post('/login', static function () use ($render): void {
    if (!Csrf::verify($_POST['_token'] ?? null)) { $render('auth/login', ['error' => 'Your session expired. Please try again.']); return; }
    if (Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) { Response::redirect('/dashboard'); }
    $render('auth/login', ['error' => 'Invalid credentials.']);
});
$router->post('/logout', static function (): void { Auth::logout(); Response::redirect('/'); });

$router->get('/dashboard', static function () use ($requireAuth, $render): void {
    $user = $requireAuth();
    $enrollments = Database::connection()->prepare("SELECT e.progress, e.status, c.title, c.slug FROM lm_enrollments e INNER JOIN lm_courses c ON c.id = e.course_id WHERE e.student_id = :id ORDER BY e.created_at DESC");
    $enrollments->execute(['id' => $user['id']]);
    $render('dashboard/index', ['enrollments' => $enrollments->fetchAll()]);
});

$router->get('/admin', static function () use ($requireRole, $render): void {
    $requireRole('admin');
    $db = Database::connection();
    $stats = [
        'users' => (int)$db->query('SELECT COUNT(*) FROM lm_users')->fetchColumn(),
        'courses' => (int)$db->query('SELECT COUNT(*) FROM lm_courses')->fetchColumn(),
        'enrollments' => (int)$db->query('SELECT COUNT(*) FROM lm_enrollments')->fetchColumn(),
        'published' => (int)$db->query("SELECT COUNT(*) FROM lm_courses WHERE status = 'published'")->fetchColumn(),
    ];
    $render('admin/dashboard', ['stats' => $stats]);
});

$router->get('/instructor', static function () use ($requireRole, $render): void {
    $user = $requireRole('instructor', 'admin');
    $statement = Database::connection()->prepare('SELECT id, title, slug, price, status FROM lm_courses WHERE instructor_id = :id ORDER BY created_at DESC');
    $statement->execute(['id' => $user['id']]);
    $render('instructor/dashboard', ['courses' => $statement->fetchAll()]);
});
