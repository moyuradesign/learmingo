<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/autoload.php';

use Learmingo\Services\LearningCore;
use Learmingo\Infrastructure\MigrationRunner;
use Learmingo\Services\InstructorApplications;
use Learmingo\Services\AssessmentService;
use Learmingo\Services\CourseVersions;

if (getenv('LEARNING_TEST_DB_NAME') !== 'learning_ci') {
    throw new RuntimeException('Refusing a non-disposable database.');
}
$pdo = new PDO(
    'mysql:host=' . (getenv('LEARNING_TEST_DB_HOST') ?: '127.0.0.1') .
    ';port=' . (getenv('LEARNING_TEST_DB_PORT') ?: '3306') . ';dbname=learning_ci;charset=utf8mb4',
    (string)getenv('LEARNING_TEST_DB_USER'), (string)getenv('LEARNING_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
);
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if (!is_string($schema)) { throw new RuntimeException('Missing schema'); }
foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $sql) {
    if (trim($sql) !== '') { $pdo->exec($sql); }
}
$migrator = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');
$pending = $migrator->status();
if (!in_array('PENDING', $pending, true)) { throw new RuntimeException('Fresh test database should have pending migrations.'); }
$installed = $migrator->apply();
if (in_array('PENDING', $installed, true) || $migrator->apply() !== $installed) {
    throw new RuntimeException('Migration apply or idempotency failure.');
}
// Use a disposable temporary migration copy to prove checksum drift fails closed.
$source = dirname(__DIR__) . '/database/migrations/20261026_000001_create_audit_events.sql';
$temporary = sys_get_temp_dir() . '/learmingo-ledger-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700)) { throw new RuntimeException('Temporary migration test directory unavailable.'); }
$copy = $temporary . '/' . basename($source);
try {
    foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [] as $migration) {
        if (!copy($migration, $temporary . '/' . basename($migration))) {
            throw new RuntimeException('Migration test copy failed.');
        }
    }
    $copyRunner = new MigrationRunner($pdo, $temporary);
    if ($copyRunner->status() !== $installed) { throw new RuntimeException('Identical migration source mismatch.'); }
    file_put_contents($copy, "\n", FILE_APPEND);
    try {
        $copyRunner->status();
        throw new RuntimeException('Checksum drift was not detected.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Checksum drift was not detected.') { throw $e; }
    }
} finally {
    foreach (glob($temporary.'/*.sql') ?: [] as $migration) { @unlink($migration); }
    @rmdir($temporary);
}
$check = static function (bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('LMS DB TEST FAILED: '.$label); }
};
$deny = static function (callable $action, string $label) use ($check): void {
    try { $action(); } catch (RuntimeException | InvalidArgumentException) { return; }
    $check(false, $label);
};
$roles = $pdo->query('SELECT slug,id FROM lm_roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$insertUser = $pdo->prepare('INSERT INTO lm_users (role_id,name,email,password) VALUES (?,?,?,?)');
$user = static function (string $role, string $email) use ($pdo,$insertUser,$roles): int {
    $insertUser->execute([$roles[$role],$role,$email,'test-only-hash']);
    return (int)$pdo->lastInsertId();
};
$a=$user('instructor','author@invalid.test');
$b=$user('instructor','other@invalid.test');
$s=$user('student','student@invalid.test');
$x=$user('student','otherstudent@invalid.test');
$admin=$user('admin','admin@invalid.test');
$insertCourse=$pdo->prepare("INSERT INTO lm_courses (instructor_id,title,slug,status) VALUES (?,? ,?,'draft')");
$course = static function (int $owner, string $slug) use ($pdo,$insertCourse): int {
    $insertCourse->execute([$owner,$slug,$slug]);
    return (int)$pdo->lastInsertId();
};
$id=$course($a,'first-course');
$empty=$course($a,'empty-course');
$core=new LearningCore($pdo);
$check($core->authorCourse($a,$id)!==null,'owner read');
$check($core->authorCourse($b,$id)===null,'other owner read denied');
$deny(fn()=>$core->addSection($b,$id,'Hijack'),'cross-owner create section');
$core->addSection($a,$id,'Start here');
$sec=(int)$core->sections($id)[0]['id'];
$core->addSection($a,$id,'Extra section');
$sec2=(int)$core->sections($id)[1]['id'];
$deny(fn()=>$core->renameSection($b,$sec2,'Hijacked'),'cross-owner section rename');
$core->renameSection($a,$sec2,'Extra renamed');
$core->moveSection($a,$sec2,'up');
$check((int)$core->sections($id)[0]['id']===$sec2,'section ordering');
$core->moveSection($a,$sec2,'down');
$check((int)$core->sections($id)[0]['id']===$sec,'section restore');
$deny(fn()=>$core->deleteItem($b,$sec2,'section'),'cross-owner delete denied');
$core->deleteItem($a,$sec2,'section');
$check(count($core->sections($id))===1,'draft section deletion');
$deny(fn()=>$core->addLesson($b,$sec,'Hijack','Body',''),'cross-owner add lesson');
$deny(fn()=>$core->addLesson($a,$sec,'Bad URL','Body','javascript:alert(1)'),'unsafe URL');
$core->addLesson($a,$sec,'First lesson','Learning body','https://example.com/lecture');
$core->addLesson($a,$sec,'Second lesson','Another body','');
$lessons=$core->sections($id)[0]['lessons'];
$check(count($lessons)===2,'lesson creation');
$l1=(int)$lessons[0]['id'];$l2=(int)$lessons[1]['id'];
$core->moveLesson($a,$l2,'up');
$check((int)$core->sections($id)[0]['lessons'][0]['id']===$l2,'lesson ordering');
$core->moveLesson($a,$l2,'down');
$check((int)$core->sections($id)[0]['lessons'][0]['id']===$l1,'lesson restore');
$core->addLesson($a,$sec,'Temporary lesson','Short draft','');
$temp=(int)$core->sections($id)[0]['lessons'][2]['id'];
$deny(fn()=>$core->deleteItem($b,$temp,'lesson'),'cross-owner lesson delete denied');
$core->deleteItem($a,$temp,'lesson');
$check(count($core->sections($id)[0]['lessons'])===2,'draft lesson deletion');
$deny(fn()=>$core->editLesson($b,$l1,'No','Body',''),'cross-owner editing');
$core->editLesson($a,$l1,'First lesson updated','Updated','');
$deny(fn()=>$core->submit($a,$empty),'empty submit');
$deny(fn()=>$core->submit($b,$id),'other owner submit');
$core->submit($a,$id);
$check($core->authorCourse($a,$id)['status']==='pending','submitted');
$deny(fn()=>$core->addLesson($a,$sec,'Late','Body',''),'pending locked');
$deny(fn()=>$core->review($b,'instructor',$id,'approve'),'nonadmin cannot review');
$core->review($admin,'admin',$id,'reject');
$check($core->authorCourse($a,$id)['status']==='draft','rejected');
$core->submit($a,$id);
$core->review($admin,'admin',$id,'approve');
$check($core->authorCourse($a,$id)['status']==='published','published');
$deny(fn()=>$core->editLesson($a,$l1,'Late','Body',''),'published locked');
$deny(fn()=>$core->moveSection($a,$sec,'up'),'published reorder locked');
$deny(fn()=>$core->deleteItem($a,$l1,'lesson'),'published delete locked');
$deny(fn()=>$core->completeLesson($s,$l1),'unenrolled student denied');
$pdo->prepare('INSERT INTO lm_enrollments (course_id,student_id) VALUES (?,?)')->execute([$id,$s]);
$check($core->completeLesson($s,$l1)==='first-course','redirect slug');
$stat=$pdo->query("SELECT progress,status FROM lm_enrollments WHERE course_id=$id AND student_id=$s")->fetch(PDO::FETCH_ASSOC);
$check((float)$stat['progress']===50.0 && $stat['status']==='active','50 percent');
$core->completeLesson($s,$l1);
$rows=(int)$pdo->query("SELECT COUNT(*) FROM lm_lesson_progress WHERE lesson_id=$l1 AND student_id=$s")->fetchColumn();
$check($rows===1,'idempotent');
$deny(fn()=>$core->completeLesson($x,$l2),'unenrolled peer denied');
$core->completeLesson($s,$l2);
$stat=$pdo->query("SELECT progress,status FROM lm_enrollments WHERE course_id=$id AND student_id=$s")->fetch(PDO::FETCH_ASSOC);
$check((float)$stat['progress']===100.0 && $stat['status']==='completed','100 percent');
$pdo->prepare("UPDATE lm_enrollments SET status='cancelled' WHERE id=(SELECT z.id FROM (SELECT id FROM lm_enrollments WHERE course_id=? AND student_id=?) z)")->execute([$id,$s]);
$deny(fn()=>$core->completeLesson($s,$l1),'cancelled access denied');
$events = $pdo->query('SELECT actor_id,action,subject_type,subject_id FROM lm_audit_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$check(count($events) >= 15,'audit event count');
$actions = array_column($events,'action');
foreach (['curriculum.section.created','curriculum.section.renamed','curriculum.section.moved',
          'curriculum.section.deleted','curriculum.lesson.created','curriculum.lesson.updated',
          'curriculum.lesson.moved','curriculum.lesson.deleted','course.review.submitted',
          'course.review.approved','course.review.rejected','learning.lesson.completed'] as $action) {
    $check(in_array($action,$actions,true),'missing audit event '.$action);
}
$lessonAudit = array_values(array_filter($events,static fn($e)=>$e['action']==='learning.lesson.completed' && (int)$e['subject_id']===$l1));
$check(count($lessonAudit)===1,'duplicate completion does not duplicate audit');
$check((int)$lessonAudit[0]['actor_id']===$s,'audit actor is authenticated student');
echo "Learmingo database-backed learning core and audit checks passed.\n";

/* Milestone 3: instructor access, version pinning, grading and certificates. */
$applications=new InstructorApplications($pdo);
$candidate=$user('student','candidate@invalid.test');
$deny(fn()=>$applications->apply($candidate,'Too short'),'minimum application statement');
$applications->apply($candidate,str_repeat('Teaching experience. ',4));
$state=$applications->mine($candidate);
$check($state['status']==='pending','application pending');
$deny(fn()=>$applications->apply($candidate,str_repeat('Duplicate pending. ',3)),'cannot duplicate pending application');
$deny(fn()=>$applications->review($a,(int)$state['id'],'approve',''),'instructor cannot approve applications');
$applications->review($admin,(int)$state['id'],'reject','Please describe your lessons.');
$check($applications->mine($candidate)['status']==='rejected','application rejection');
$applications->apply($candidate,str_repeat('Second application. ',4));
$applications->review($admin,(int)$state['id'],'approve','Approved');
$check($applications->mine($candidate)['status']==='approved','instructor approval');
$check((int)$pdo->query("SELECT role_id FROM lm_users WHERE id=$candidate")->fetchColumn()===(int)$roles['instructor'],'role promotion');

/* A course's published baseline can be backfilled without changing its
 * existing course lessons or moving an already enrolled student to v2. */
$versions=new CourseVersions($pdo);
$baseline=$versions->establishBaseline($admin,$id);
$check((int)$versions->latestVersion($id)['version_no']===1,'version 1 baseline');
$deny(fn()=>$versions->establishBaseline($admin,$id),'second baseline denied');
$check((int)$pdo->query("SELECT COUNT(*) FROM lm_enrollment_versions WHERE version_id=$baseline")->fetchColumn()===1,'historical enrollment pinned');
$oldProgress=(int)$pdo->query("SELECT COUNT(*) FROM lm_version_lesson_progress p
 JOIN lm_enrollment_versions ev ON ev.enrollment_id=p.enrollment_id WHERE ev.version_id=$baseline")->fetchColumn();
$check($oldProgress===2,'historical completed lessons mapped');
$learner=$user('student','versionedlearner@invalid.test');
$versions->enrollFree($learner,$id);
$before=$versions->classroom($learner,'first-course');
$check(count($before['lessons'])===2,'snapshot classroom has two lessons');
$check((int)$before['course']['id']===$id,'pinned classroom retains course id for assessments');
$versionLesson=(int)$before['lessons'][0]['version_lesson_id'];
$check($before['lessons'][0]['completed_at']===null,'new enrollment has no completions');
$deny(fn()=>$core->completeLesson($learner,$l1),'legacy write blocked for pinned learner');
$versions->completeVersionLesson($learner,$versionLesson);
$versions->completeVersionLesson($learner,$versionLesson);
$percentage=(float)$pdo->query("SELECT progress FROM lm_enrollments WHERE student_id=$learner AND course_id=$id")->fetchColumn();
$check($percentage===50.0,'versioned learner half done');
$revised=$versions->createRevision($a,$id);
$deny(fn()=>$versions->editRevisionLesson($b,$revised,0,'Hijacked','No',''),'cross-owner revision denied');
$versions->editRevisionLesson($a,$revised,0,'Updated for version two','Fresh learning content','');
$versions->submitRevision($a,$revised);
$deny(fn()=>$versions->editRevisionLesson($a,$revised,0,'Too late','No',''),'pending revision frozen');
$deny(fn()=>$versions->reviewRevision($b,$revised,'approve'),'review requires admin');
$versionTwo=$versions->reviewRevision($admin,$revised,'approve');
$check((int)$versions->latestVersion($id)['version_no']===2,'new immutable version published');
$check((int)$versions->classroom($learner,'first-course')['course']['version_id']===$baseline,'original enrollment pinned v1');
$check($versions->classroom($learner,'first-course')['lessons'][0]['title']!=='Updated for version two','v1 content never changed');
$learner2=$user('student','laterlearner@invalid.test');
$versions->enrollFree($learner2,$id);
$latestClass=$versions->classroom($learner2,'first-course');
$check((int)$latestClass['course']['version_id']===(int)$versionTwo,'later enrollment pinned v2');
$check($latestClass['lessons'][0]['title']==='Updated for version two','v2 content visible');
$check($versions->classroom($learner,'first-course')['lessons'][0]['title']!=='Updated for version two','v1 remains original after v2');
$check(count($versions->pendingRevisions($admin))===0,'no pending revisions after approval');

/* All quiz grading is computed from immutable published questions and an
 * authenticated student enrollment. User-supplied scores are never accepted. */
$assess=new AssessmentService($pdo);
$quiz=$assess->createQuiz($a,$id,'Final exam',60,2);
$deny(fn()=>$assess->addQuestion($b,$quiz,'Cross tenant question',['a'=>'One','b'=>'Two','c'=>'Three','d'=>'Four'],'a',1),'foreign instructor question denied');
$q1=$assess->addQuestion($a,$quiz,'Question number one',['a'=>'Correct','b'=>'Incorrect','c'=>'Choice C','d'=>'Choice D'],'a',1);
$q2=$assess->addQuestion($a,$quiz,'Question number two',['a'=>'Incorrect','b'=>'Correct','c'=>'Choice C','d'=>'Choice D'],'b',1);
$assess->publishQuiz($a,$quiz);
$deny(fn()=>$assess->addQuestion($a,$quiz,'No edits after publish',['a'=>'One','b'=>'Two','c'=>'Three','d'=>'Four'],'a',1),'published quiz immutable');
$deny(fn()=>$assess->submit($candidate,$quiz,[$q1=>'a',$q2=>'b']),'not enrolled cannot grade');
$deny(fn()=>$assess->submit($learner,$quiz,[$q1=>'a']),'partial assessment denied');
$failed=$assess->submit($learner,$quiz,[$q1=>'b',$q2=>'b']);
$check($failed['passed']===false && (float)$failed['score']===50.0,'incorrect answers graded at 50');
$success=$assess->submit($learner,$quiz,[$q1=>'a',$q2=>'b']);
$check($success['passed']===true && (float)$success['score']===100.0,'correct answers graded at 100');
$deny(fn()=>$assess->submit($learner,$quiz,[$q1=>'a',$q2=>'b']),'attempt limit and pass lock');
$deny(fn()=>$assess->issueCertificate($learner,$id),'certificate requires course completion');
$versions->completeVersionLesson($learner,(int)$before['lessons'][1]['version_lesson_id']);
$issued=$assess->issueCertificate($learner,$id);
$check(strlen($issued['credential'])===64,'unguessable credential');
$check($assess->issueCertificate($learner,$id)['credential']===$issued['credential'],'certificate issuance idempotent');
$verified=$assess->verifyCredential($issued['credential']);
$check($verified!==null && $verified['course_title']==='first-course','credential verification');
$check($assess->verifyCredential(str_repeat('a',64))===null,'unknown credential rejected');
$check($assess->verifyCredential('../bad')===null,'malformed credential rejected');
$events=$pdo->query("SELECT action FROM lm_audit_events")->fetchAll(PDO::FETCH_COLUMN);
foreach (['instructor.application.approved','assessment.quiz.graded','learning.certificate.issued',
 'curriculum.version.published','curriculum.revision.approved','learning.version_lesson.completed'] as $action) {
    $check(in_array($action,$events,true),'missing milestone3 audit '.$action);
}
echo "Learmingo milestone 3 database checks passed.\n";

