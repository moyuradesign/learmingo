<?php
declare(strict_types=1);

// Loaded after the shared router, authentication, input and view closures.
use Learmingo\Services\InstructorApplications;
use Learmingo\Services\AssessmentService;
use Learmingo\Services\CourseVersions;

$positive = static function (mixed $value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    return $id === false ? 0 : (int)$id;
};
$csrfGate = static function (): bool {
    if (Csrf::verify($_POST['_token'] ?? null)) { return true; }
    Response::html('Invalid request',419);
    return false;
};
$deniedWork = static function (\Throwable $e): void {
    if ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException) {
        Response::html(e($e->getMessage()),422);
        return;
    }
    throw $e;
};

$router->get('/instructor/apply', static function () use ($requireRole,$render): void {
    $user=$requireRole('student');
    $form=(new InstructorApplications(Database::connection()))->mine((int)$user['id']);
    $render('instructor/apply',['application'=>$form]);
});
$router->post('/instructor/apply', static function () use ($requireRole,$input,$csrfGate,$deniedWork): void {
    $user=$requireRole('student');
    if (!$csrfGate()) { return; }
    try {
        (new InstructorApplications(Database::connection()))->apply((int)$user['id'],$input('statement',3000));
        Response::redirect('/instructor/apply');
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->get('/admin/instructor-applications', static function () use ($requireRole,$render): void {
    $admin=$requireRole('admin');
    $items=(new InstructorApplications(Database::connection()))->pending((int)$admin['id']);
    $render('admin/instructor-applications',['applications'=>$items]);
});
$router->post('/admin/instructor-applications/review', static function () use ($requireRole,$positive,$input,$csrfGate,$deniedWork): void {
    $admin=$requireRole('admin');
    if (!$csrfGate()) { return; }
    try {
        (new InstructorApplications(Database::connection()))->review((int)$admin['id'],$positive($_POST['application_id'] ?? null),$input('decision',20),$input('note',500));
        Response::redirect('/admin/instructor-applications');
    } catch (\Throwable $e) { $deniedWork($e); }
});

$router->get('/instructor/quizzes', static function () use ($requireRole,$query,$positive,$render): void {
    $instructor=$requireRole('instructor','admin');
    $course=$positive($query('course'));
    $core=new \Learmingo\Services\LearningCore(Database::connection());
    $owned=$core->authorCourse((int)$instructor['id'],$course);
    if (!$owned) { Response::html('Course not found',404); return; }
    $assessment=new AssessmentService(Database::connection());
    $quizzes=$assessment->courseQuizzes($course);
    foreach ($quizzes as &$quiz) {
        $quiz['questions']=$assessment->quizQuestions((int)$quiz['id'],true);
    }
    unset($quiz);
    $render('instructor/quizzes',['course'=>$owned,'quizzes'=>$quizzes]);
});
$router->post('/instructor/quizzes/create', static function () use ($requireRole,$positive,$input,$csrfGate,$deniedWork): void {
    $actor=$requireRole('instructor','admin'); if (!$csrfGate()) { return; }
    $course=$positive($_POST['course_id']??null);
    try {
        (new AssessmentService(Database::connection()))->createQuiz((int)$actor['id'],$course,$input('title',200),
            (int)$input('passing_percent',3),(int)$input('max_attempts',2));
        Response::redirect('/instructor/quizzes?course='.$course);
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->post('/instructor/quizzes/question', static function () use ($requireRole,$positive,$input,$csrfGate,$deniedWork): void {
    $actor=$requireRole('instructor','admin'); if (!$csrfGate()) { return; }
    $course=$positive($_POST['course_id']??null);
    $quiz=$positive($_POST['quiz_id']??null);
    try {
        $owner=(new \Learmingo\Services\LearningCore(Database::connection()))->authorCourse((int)$actor['id'],$course);
        if (!$owner) { throw new \RuntimeException('Course not found.'); }
        (new AssessmentService(Database::connection()))->addQuestion((int)$actor['id'],$quiz,$input('prompt',1000),[
            'a'=>$input('option_a',500),'b'=>$input('option_b',500),
            'c'=>$input('option_c',500),'d'=>$input('option_d',500)
        ],$input('correct_option',1),(int)$input('points',3));
        Response::redirect('/instructor/quizzes?course='.$course);
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->post('/instructor/quizzes/publish', static function () use ($requireRole,$positive,$csrfGate,$deniedWork): void {
    $actor=$requireRole('instructor','admin'); if (!$csrfGate()) { return; }
    $course=$positive($_POST['course_id']??null);
    try {
        (new AssessmentService(Database::connection()))->publishQuiz((int)$actor['id'],$positive($_POST['quiz_id']??null));
        Response::redirect('/instructor/quizzes?course='.$course);
    } catch (\Throwable $e) { $deniedWork($e); }
});

$router->get('/quiz', static function () use ($requireRole,$query,$positive,$render): void {
    $student=$requireRole('student');
    $quizId=$positive($query('id'));
    $service=new AssessmentService(Database::connection());
    $quiz=$service->availableToStudent((int)$student['id'],$quizId);
    if (!$quiz) { Response::html('Assessment unavailable',404); return; }
    $render('dashboard/quiz',['quiz'=>$quiz,'questions'=>$service->quizQuestions($quizId)]);
});
$router->post('/quiz/submit', static function () use ($requireRole,$positive,$csrfGate,$deniedWork,$render): void {
    $student=$requireRole('student'); if (!$csrfGate()) { return; }
    $answers=$_POST['answers']??null;
    if (!is_array($answers)) { Response::html('Answer every question',422); return; }
    try {
        $outcome=(new AssessmentService(Database::connection()))->submit((int)$student['id'],
            $positive($_POST['quiz_id']??null),$answers);
        $render('dashboard/quiz-result',['outcome'=>$outcome]);
    } catch (\Throwable $e) { $deniedWork($e); }
});

$router->get('/student/records', static function () use ($requireRole,$render): void {
    $student=$requireRole('student');
    $db=Database::connection();
    $q=$db->prepare('SELECT e.progress,e.status,c.id course_id,c.title,c.slug FROM lm_enrollments e
        JOIN lm_courses c ON c.id=e.course_id WHERE e.student_id=:student ORDER BY e.created_at DESC');
    $q->execute(['student'=>$student['id']]);
    $courses=$q->fetchAll(\PDO::FETCH_ASSOC);
    $a=$db->prepare('SELECT a.score_percent,a.passed,a.submitted_at,q.title quiz_title,c.title course_title
        FROM lm_quiz_attempts a JOIN lm_quizzes q ON q.id=a.quiz_id JOIN lm_courses c ON c.id=q.course_id
        WHERE a.student_id=:student ORDER BY a.submitted_at DESC,a.id DESC LIMIT 100');
    $a->execute(['student'=>$student['id']]);
    $q=$db->prepare('SELECT credential,course_title,issued_at FROM lm_certificates WHERE student_id=:student AND revoked_at IS NULL ORDER BY issued_at DESC');
    $q->execute(['student'=>$student['id']]);
    $render('dashboard/records',['courses'=>$courses,'attempts'=>$a->fetchAll(\PDO::FETCH_ASSOC),
        'certificates'=>$q->fetchAll(\PDO::FETCH_ASSOC)]);
});
$router->get('/instructor/records', static function () use ($requireRole,$render): void {
    $actor=$requireRole('instructor','admin');
    $q=Database::connection()->prepare("SELECT u.name learner,c.title course_title,e.status,e.progress
        FROM lm_enrollments e JOIN lm_courses c ON c.id=e.course_id JOIN lm_users u ON u.id=e.student_id
        WHERE c.instructor_id=:instructor ORDER BY c.id,e.id DESC LIMIT 200");
    $q->execute(['instructor'=>$actor['id']]);
    $render('instructor/records',['records'=>$q->fetchAll(\PDO::FETCH_ASSOC)]);
});
$router->post('/certificate/issue', static function () use ($requireRole,$positive,$csrfGate,$deniedWork): void {
    $student=$requireRole('student'); if (!$csrfGate()) { return; }
    try {
        $cert=(new AssessmentService(Database::connection()))->issueCertificate((int)$student['id'],
            $positive($_POST['course_id']??null));
        Response::redirect('/certificate/verify?code='.$cert['credential']);
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->get('/certificate/verify', static function () use ($query,$render): void {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');
    $cert=(new AssessmentService(Database::connection()))->verifyCredential($query('code',64));
    $render('dashboard/certificate',['certificate'=>$cert]);
});

$router->get('/admin/course-versions', static function () use ($requireRole,$render): void {
    $admin=$requireRole('admin');
    $q=Database::connection()->query("SELECT c.id,c.title,c.status,
        (SELECT MAX(v.version_no) FROM lm_course_versions v WHERE v.course_id=c.id) version
        FROM lm_courses c WHERE c.status='published' ORDER BY c.id DESC LIMIT 100");
    $service=new CourseVersions(Database::connection());
    $render('admin/course-versions',['courses'=>$q->fetchAll(\PDO::FETCH_ASSOC),
        'revisions'=>$service->pendingRevisions((int)$admin['id'])]);
});
$router->post('/admin/course-versions/baseline', static function () use ($requireRole,$positive,$csrfGate,$deniedWork): void {
    $admin=$requireRole('admin'); if (!$csrfGate()) { return; }
    try {
        (new CourseVersions(Database::connection()))->establishBaseline((int)$admin['id'],$positive($_POST['course_id']??null));
        Response::redirect('/admin/course-versions');
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->post('/instructor/revisions/create', static function () use ($requireRole,$positive,$csrfGate,$deniedWork): void {
    $user=$requireRole('instructor'); if (!$csrfGate()) { return; }
    try {
        $id=(new CourseVersions(Database::connection()))->createRevision((int)$user['id'],$positive($_POST['course_id']??null));
        Response::redirect('/instructor/revision?id='.$id);
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->get('/instructor/revision', static function () use ($requireRole,$positive,$query,$render): void {
    $user=$requireRole('instructor');
    $d=(new CourseVersions(Database::connection()))->myRevision((int)$user['id'],$positive($query('id')));
    if (!$d) { Response::html('Revision not found',404); return; }
    $render('instructor/revision',['draft'=>$d]);
});
$router->post('/instructor/revision/edit', static function () use ($requireRole,$positive,$input,$csrfGate,$deniedWork): void {
    $user=$requireRole('instructor'); if (!$csrfGate()) { return; }
    $id=$positive($_POST['revision_id']??null);
    try {
        $index=filter_var($_POST['index']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($index===false) { throw new \InvalidArgumentException('Invalid lesson index.'); }
        (new CourseVersions(Database::connection()))->editRevisionLesson((int)$user['id'],$id,(int)$index,
            $input('title',200),$input('content',100000),$input('video_url',500));
        Response::redirect('/instructor/revision?id='.$id);
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->post('/instructor/revision/submit', static function () use ($requireRole,$positive,$csrfGate,$deniedWork): void {
    $user=$requireRole('instructor'); if (!$csrfGate()) { return; }
    $id=$positive($_POST['revision_id']??null);
    try {
        (new CourseVersions(Database::connection()))->submitRevision((int)$user['id'],$id);
        Response::redirect('/instructor/revision?id='.$id);
    } catch (\Throwable $e) { $deniedWork($e); }
});
$router->post('/admin/revisions/review', static function () use ($requireRole,$positive,$input,$csrfGate,$deniedWork): void {
    $admin=$requireRole('admin'); if (!$csrfGate()) { return; }
    try {
        (new CourseVersions(Database::connection()))->reviewRevision((int)$admin['id'],
            $positive($_POST['revision_id']??null),$input('decision',20));
        Response::redirect('/admin/course-versions');
    } catch (\Throwable $e) { $deniedWork($e); }
});
