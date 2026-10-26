<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
'bootstrap/app.php','bootstrap/autoload.php','config/app.php','config/database.php',
'public/index.php','routes/web.php','database/schema.sql','install/index.php',
'resources/views/layouts/app.php','resources/views/home.php',
'resources/views/auth/login.php','resources/views/auth/register.php',
'resources/views/courses/index.php','resources/views/courses/show.php',
'resources/views/dashboard/index.php','resources/views/dashboard/learn.php',
'resources/views/admin/dashboard.php','resources/views/admin/users.php',
'resources/views/instructor/dashboard.php','resources/views/instructor/course-form.php',
'resources/views/instructor/course-builder.php','resources/views/admin/courses.php',
'app/Domain/CoursePolicy.php','app/Services/LearningCore.php','routes/learning.php',
'app/Infrastructure/MigrationRunner.php','app/Infrastructure/AuditTrail.php','scripts/migrate.php',
'database/migrations/20261026_000001_create_audit_events.sql','resources/views/admin/audit.php',
'public/assets/css/learmingo-premium.css','public/assets/js/learmingo-ui.js'
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) { fwrite(STDERR,"Missing required file: {$file}\n"); exit(1); }
}
$schema = file_get_contents($root.'/database/schema.sql');
foreach (['lm_users','lm_courses','lm_enrollments','lm_course_sections','lm_lessons','lm_lesson_progress'] as $table) {
    if (!is_string($schema) || !str_contains($schema,chr(96).$table.chr(96))) { fwrite(STDERR,"Schema missing {$table}.\n"); exit(1); }
}
$routes = file_get_contents($root.'/routes/web.php');
foreach (["'/register'","'/enroll'","'/learn'","'/admin/users'","'/instructor/courses/store'"] as $route) {
    if (!is_string($routes) || !str_contains($routes,$route)) { fwrite(STDERR,"Route missing {$route}.\n"); exit(1); }
}
$layout = file_get_contents($root.'/resources/views/layouts/app.php');
foreach (['lm-main','lm-footer','lm-nav','lm-mobile-menu','learmingo-premium.css','learmingo-ui.js'] as $marker) {
    if (!is_string($layout) || !str_contains($layout,$marker)) { fwrite(STDERR,"Layout marker missing {$marker}.\n"); exit(1); }
}
$css = file_get_contents($root.'/public/assets/css/learmingo-premium.css');
foreach (['.lm-hero','.lm-auth-page','.lm-course-card','.lm-footer','.lm-mobile-menu','@media',':focus-visible'] as $marker) {
    if (!is_string($css) || !str_contains($css,$marker)) { fwrite(STDERR,"Styles missing {$marker}.\n"); exit(1); }
}
foreach (['resources/views/auth/login.php','resources/views/auth/register.php'] as $file) {
    $content = file_get_contents($root.'/'.$file);
    foreach (['csrf_field()','method="post"','lm-auth-card'] as $marker) {
        if (!is_string($content) || !str_contains($content,$marker)) { fwrite(STDERR,"Auth marker missing from {$file}: {$marker}\n"); exit(1); }
    }
}
foreach (['resources/views/home.php','resources/views/courses/index.php','resources/views/courses/show.php','resources/views/dashboard/index.php','resources/views/dashboard/learn.php'] as $file) {
    $content = file_get_contents($root.'/'.$file);
    if (!is_string($content) || !str_contains($content,'lm-')) { fwrite(STDERR,"Premium UI missing from {$file}\n"); exit(1); }
}
$learn = file_get_contents($root.'/resources/views/dashboard/learn.php');
if (!is_string($learn) || !str_contains($learn,'nl2br(e(') || !str_contains($routes,'e.student_id=:student')) {
    fwrite(STDERR,"Learner route or escaped lesson body is missing.\n"); exit(1);
}
echo "Learmingo structural integration checks passed.\n";
