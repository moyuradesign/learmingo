<?php
declare(strict_types=1);

// This adapter is loaded by routes/web.php after its shared closures.

$router->get('/instructor/course', static function () use ($requireRole, $render, $query): void {
    $user = $requireRole('instructor', 'admin');
    $id = filter_var($query('course'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { Response::html('Course not found', 404); return; }
    $core = new \Learmingo\Services\LearningCore(Database::connection());
    $course = $core->authorCourse((int) $user['id'], (int) $id);
    if ($course === null) { Response::html('Course not found', 404); return; }
    $render('instructor/course-builder', ['course' => $course, 'sections' => $core->sections((int) $id)]);
});

$router->post('/instructor/sections/store', static function () use ($requireRole, $input): void {
    $user = $requireRole('instructor', 'admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    $course = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$course) { Response::html('Invalid course', 422); return; }
    try {
        (new \Learmingo\Services\LearningCore(Database::connection()))->addSection((int) $user['id'], (int) $course, $input('title', 200));
        Response::redirect('/instructor/course?course=' . (int) $course);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html(e($e->getMessage()), 422);
    }
});

$router->post('/instructor/lessons/store', static function () use ($requireRole, $input): void {
    $user = $requireRole('instructor', 'admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    $section = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $course = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$section || !$course) { Response::html('Invalid lesson location', 422); return; }
    try {
        $core = new \Learmingo\Services\LearningCore(Database::connection());
        if ($core->authorCourse((int) $user['id'], (int) $course) === null) { Response::html('Course not found', 404); return; }
        $core->addLesson((int) $user['id'], (int) $section, $input('title', 200), $input('content', 100000), trim($input('video_url', 500)));
        Response::redirect('/instructor/course?course=' . (int) $course);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html(e($e->getMessage()), 422);
    }
});

$router->post('/instructor/lessons/update', static function () use ($requireRole, $input): void {
    $user = $requireRole('instructor', 'admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    $lesson = filter_var($_POST['lesson_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $course = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$lesson || !$course) { Response::html('Invalid lesson', 422); return; }
    try {
        $core = new \Learmingo\Services\LearningCore(Database::connection());
        if ($core->authorCourse((int) $user['id'], (int) $course) === null) { Response::html('Course not found', 404); return; }
        $core->editLesson((int) $user['id'], (int) $lesson, $input('title', 200), $input('content', 100000), trim($input('video_url', 500)));
        Response::redirect('/instructor/course?course=' . (int) $course);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html(e($e->getMessage()), 422);
    }
});

$router->post('/instructor/courses/submit', static function () use ($requireRole): void {
    $user = $requireRole('instructor', 'admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    $course = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$course) { Response::html('Invalid course', 422); return; }
    try {
        (new \Learmingo\Services\LearningCore(Database::connection()))->submit((int) $user['id'], (int) $course);
        Response::redirect('/instructor/course?course=' . (int) $course);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html(e($e->getMessage()), 422);
    }
});

$router->get('/admin/courses', static function () use ($requireRole, $render): void {
    $requireRole('admin');
    $courses = Database::connection()->query("SELECT c.id,c.title,c.slug,c.status,c.price,u.name instructor,
        (SELECT COUNT(*) FROM lm_lessons l INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE s.course_id=c.id) lessons
        FROM lm_courses c INNER JOIN lm_users u ON u.id=c.instructor_id
        ORDER BY FIELD(c.status,'pending','draft','published','archived'),c.created_at DESC LIMIT 300")->fetchAll();
    $render('admin/courses', ['courses' => $courses]);
});

$router->post('/admin/courses/review', static function () use ($requireRole, $input): void {
    $actor = $requireRole('admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    $course = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$course) { Response::html('Invalid course', 422); return; }
    try {
        (new \Learmingo\Services\LearningCore(Database::connection()))->review((int) $actor['id'], (string) $actor['role'], (int) $course, $input('decision', 20));
        Response::redirect('/admin/courses');
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html(e($e->getMessage()), 422);
    }
});

$router->post('/learn/complete', static function () use ($requireAuth): void {
    $user = $requireAuth();
    if (!Csrf::verify($_POST['_token'] ?? null)) { Response::html('Invalid request', 419); return; }
    $lesson = filter_var($_POST['lesson_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$lesson) { Response::html('Invalid lesson', 422); return; }
    try {
        $slug = (new \Learmingo\Services\LearningCore(Database::connection()))->completeLesson((int) $user['id'], (int) $lesson);
        Response::redirect('/learn?course=' . rawurlencode($slug));
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html('Lesson not found or access denied', 404);
    }
});


$router->post('/instructor/curriculum/change', static function () use ($requireRole, $input): void {
    $actor = $requireRole('instructor', 'admin');
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        Response::html('Invalid request', 419);
        return;
    }
    $id = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { Response::html('Invalid curriculum item', 422); return; }
    $operation = $input('operation', 32);
    $core = new \Learmingo\Services\LearningCore(Database::connection());
    try {
        $courseId = match ($operation) {
            'rename_section' => $core->renameSection((int)$actor['id'], (int)$id, $input('title', 200)),
            'move_section' => $core->moveSection((int)$actor['id'], (int)$id, $input('direction', 8)),
            'move_lesson' => $core->moveLesson((int)$actor['id'], (int)$id, $input('direction', 8)),
            'delete_section' => $input('confirm', 8) === 'DELETE'
                ? $core->deleteItem((int)$actor['id'], (int)$id, 'section')
                : throw new \InvalidArgumentException('Type DELETE to confirm deletion.'),
            'delete_lesson' => $input('confirm', 8) === 'DELETE'
                ? $core->deleteItem((int)$actor['id'], (int)$id, 'lesson')
                : throw new \InvalidArgumentException('Type DELETE to confirm deletion.'),
            default => throw new \InvalidArgumentException('Invalid curriculum operation.'),
        };
        Response::redirect('/instructor/course?course=' . (int)$courseId);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        Response::html(e($e->getMessage()), 422);
    }
});

$router->get('/admin/audit', static function () use ($requireRole, $render, $query): void {
    $requireRole('admin');
    $page = filter_var($query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $page = min(10000, $page ?: 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $db = Database::connection();
    $s = $db->prepare('SELECT id,actor_id,action,subject_type,subject_id,created_at
        FROM lm_audit_events ORDER BY id DESC LIMIT :limit OFFSET :offset');
    $s->bindValue(':limit', $limit + 1, \PDO::PARAM_INT);
    $s->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $s->execute();
    $events = $s->fetchAll(\PDO::FETCH_ASSOC);
    $hasMore = count($events) > $limit;
    $render('admin/audit', ['events' => array_slice($events, 0, $limit), 'page' => $page, 'hasMore' => $hasMore]);
});
