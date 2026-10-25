<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'bootstrap/app.php',
    'bootstrap/autoload.php',
    'config/app.php',
    'config/database.php',
    'public/index.php',
    'routes/web.php',
    'database/schema.sql',
    'install/index.php',
    'resources/views/home.php',
    'resources/views/auth/login.php',
    'resources/views/auth/register.php',
    'resources/views/courses/index.php',
    'resources/views/courses/show.php',
    'resources/views/admin/dashboard.php',
    'resources/views/admin/users.php',
    'resources/views/instructor/dashboard.php',
    'resources/views/instructor/course-form.php',
    'resources/views/dashboard/index.php',
];

$missing = array_values(array_filter($required, static fn (string $file): bool => !is_file($root . '/' . $file)));
if ($missing !== []) {
    fwrite(STDERR, "Missing required files:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$schema = file_get_contents($root . '/database/schema.sql');
foreach (['lm_users', 'lm_courses', 'lm_enrollments', 'lm_course_sections', 'lm_lessons', 'lm_lesson_progress'] as $table) {
    if (!is_string($schema) || !str_contains($schema, '`' . $table . '`')) {
        fwrite(STDERR, "Schema is missing {$table}.\n");
        exit(1);
    }
}

$routes = file_get_contents($root . '/routes/web.php');
foreach (['/register', '/enroll', '/admin/users', '/instructor/courses/store'] as $route) {
    if (!is_string($routes) || !str_contains($routes, "'{$route}'")) {
        fwrite(STDERR, "Route is missing {$route}.\n");
        exit(1);
    }
}

echo "Learmingo structural integration checks passed.\n";
