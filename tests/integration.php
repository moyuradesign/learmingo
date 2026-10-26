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
'app/Services/InstructorApplications.php','app/Services/AssessmentService.php','app/Services/CourseVersions.php',
'routes/milestone3.php','resources/views/instructor/apply.php','resources/views/admin/instructor-applications.php',
'resources/views/instructor/quizzes.php','resources/views/dashboard/quiz.php','resources/views/dashboard/quiz-result.php',
'resources/views/dashboard/records.php','resources/views/instructor/records.php','resources/views/dashboard/certificate.php',
'resources/views/admin/course-versions.php','resources/views/instructor/revision.php',
'database/migrations/20261026_000002_instructor_applications.sql',
'database/migrations/20261026_000003_quizzes.sql',
'database/migrations/20261026_000004_quiz_questions.sql',
'database/migrations/20261026_000005_quiz_attempts.sql',
'database/migrations/20261026_000006_quiz_answers.sql',
'database/migrations/20261026_000007_certificates.sql',
'database/migrations/20261026_000008_course_versions.sql',
'database/migrations/20261026_000009_version_lessons.sql',
'database/migrations/20261026_000010_enrollment_versions.sql',
'database/migrations/20261026_000011_revision_drafts.sql',
'database/migrations/20261026_000012_version_lesson_progress.sql',
'database/migrations/20261026_000013_question_formats.sql',
'database/migrations/20261026_000014_answer_selections.sql',
'database/migrations/20261026_000015_certificate_revocations.sql',
'resources/views/instructor/gradebook.php','resources/views/dashboard/attempt.php',
'resources/views/admin/certificates.php',
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
// Accept the legacy shell on older releases; require the stronger v1.0.4 layout when installed.
$frontend104 = str_contains($routes, "require __DIR__ . '/frontend.php'");
$layoutMarkers = $frontend104
    ? ['lm2-main','lm2-footer','lm2-header','lm2-mobile-menu','learmingo-ui/style','learmingo-ui/script']
    : ['lm-main','lm-footer','lm-nav','lm-mobile-menu','learmingo-premium.css','learmingo-ui.js'];
foreach ($layoutMarkers as $marker) {
    if (!is_string($layout) || !str_contains($layout,$marker)) { fwrite(STDERR,"Layout marker missing {$marker}.\n"); exit(1); }
}
$css = file_get_contents($root.'/public/assets/css/learmingo-premium.css');
foreach (['.lm-hero','.lm-auth-page','.lm-course-card','.lm-footer','.lm-mobile-menu','@media',':focus-visible'] as $marker) {
    if (!is_string($css) || !str_contains($css,$marker)) { fwrite(STDERR,"Styles missing {$marker}.\n"); exit(1); }
}
foreach (['resources/views/auth/login.php','resources/views/auth/register.php'] as $file) {
    $content = file_get_contents($root.'/'.$file);
    foreach (['csrf_field()','method="post",$frontend104 ? 'lm2-auth-card' : 'lm-auth-card'] as $marker) {
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
