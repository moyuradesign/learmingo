<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/autoload.php';

use Learmingo\Services\LearningCore;
use Learmingo\Infrastructure\MigrationRunner;

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
