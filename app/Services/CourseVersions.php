<?php
declare(strict_types=1);

namespace Learmingo\Services;

use Learmingo\Domain\CoursePolicy;
use Learmingo\Infrastructure\AuditTrail;
use PDO;
use RuntimeException;

/** Immutable published snapshots, editable independent revision drafts and pinned enrollments. */
final class CourseVersions
{
    public function __construct(private readonly PDO $db) {}

    private function audit(int $actor, string $action, string $type, int $id, array $details = []): void
    {
        (new AuditTrail($this->db))->record($actor, $action, $type, $id, $details);
    }

    private function role(int $actor, string $role): void
    {
        $q=$this->db->prepare("SELECT r.slug FROM lm_users u JOIN lm_roles r ON r.id=u.role_id WHERE u.id=:id AND u.status='active'");
        $q->execute(['id'=>$actor]);
        if ($q->fetchColumn()!==$role) {
            throw new RuntimeException('Insufficient permissions.');
        }
    }

    private function author(int $actorId): void
    {
        $q=$this->db->prepare("SELECT r.slug FROM lm_users u JOIN lm_roles r ON r.id=u.role_id WHERE u.id=:id AND u.status='active'");
        $q->execute(['id'=>$actorId]);
        if (!in_array($q->fetchColumn(),['instructor','admin'],true)) {
            throw new RuntimeException('Instructor permissions required.');
        }
    }

    private function validateDigest(array $draft): void
    {
        if (!hash_equals((string)$draft['snapshot_sha256'],hash('sha256',(string)$draft['snapshot_json']))) {
            throw new RuntimeException('Revision snapshot integrity mismatch.');
        }
    }

    private function lockedCourse(int $courseId): array
    {
        $q=$this->db->prepare('SELECT id,instructor_id,title,slug,status,price FROM lm_courses WHERE id=:id FOR UPDATE');
        $q->execute(['id'=>$courseId]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if (!$r) { throw new RuntimeException('Course unavailable.'); }
        return $r;
    }

    private function latest(int $courseId): ?array
    {
        $q=$this->db->prepare('SELECT id,version_no,title FROM lm_course_versions WHERE course_id=:id ORDER BY version_no DESC LIMIT 1');
        $q->execute(['id'=>$courseId]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function latestVersion(int $courseId): ?array
    {
        return $this->latest($courseId);
    }

    private function canonical(array $lessons): string
    {
        if (count($lessons)<1 || count($lessons)>400) {
            throw new RuntimeException('Version must contain between one and 400 lessons.');
        }
        $normalized=[];
        foreach ($lessons as $lesson) {
            if (!is_array($lesson)) { throw new \InvalidArgumentException('Invalid revision lesson.'); }
            $section=trim((string)($lesson['section_title'] ?? ''));
            $title=trim((string)($lesson['title'] ?? ''));
            $body=(string)($lesson['content'] ?? '');
            $video=trim((string)($lesson['video_url'] ?? ''));
            $source=(int)($lesson['source_lesson_id'] ?? 0);
            $sectionOrder=(int)($lesson['section_position'] ?? -1);
            $lessonOrder=(int)($lesson['lesson_position'] ?? -1);
            if (!CoursePolicy::validTitle($section) || !CoursePolicy::validTitle($title)
                || strlen($body)>100000 || !CoursePolicy::validVideoUrl($video)
                || $source<0 || $sectionOrder<0 || $lessonOrder<0) {
                throw new \InvalidArgumentException('Invalid revision lesson data.');
            }
            $normalized[]=[
                'source_lesson_id'=>$source ?: null,
                'section_title'=>$section, 'section_position'=>$sectionOrder,
                'title'=>$title, 'content'=>$body, 'video_url'=>$video,
                'lesson_position'=>$lessonOrder,
            ];
        }
        return json_encode($normalized,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    private function fromLegacy(int $courseId): array
    {
        $q=$this->db->prepare('SELECT l.id AS source_lesson_id,s.title AS section_title,
            s.sort_order AS section_position,l.title,l.content,l.video_url,l.sort_order AS lesson_position
            FROM lm_lessons l JOIN lm_course_sections s ON s.id=l.section_id
            WHERE s.course_id=:course ORDER BY s.sort_order,s.id,l.sort_order,l.id');
        $q->execute(['course'=>$courseId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function snapshotLessons(int $versionId): array
    {
        $q=$this->db->prepare('SELECT source_lesson_id,section_title,section_position,title,content,video_url,lesson_position
            FROM lm_version_lessons WHERE version_id=:version ORDER BY section_position,lesson_position,id');
        $q->execute(['version'=>$versionId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function writeVersion(int $actor, array $course, array $lessons): int
    {
        // Caller holds an InnoDB lock on the parent course.
        $json=$this->canonical($lessons);
        $last=$this->latest((int)$course['id']);
        $next=(int)($last['version_no'] ?? 0)+1;
        $q=$this->db->prepare('INSERT INTO lm_course_versions(course_id,version_no,title,content_sha256,published_by)
            VALUES (:course,:version,:title,:checksum,:actor)');
        $q->execute(['course'=>$course['id'],'version'=>$next,'title'=>$course['title'],'checksum'=>hash('sha256',$json),'actor'=>$actor]);
        $id=(int)$this->db->lastInsertId();
        $insert=$this->db->prepare('INSERT INTO lm_version_lessons
            (version_id,source_lesson_id,section_title,section_position,title,content,video_url,lesson_position)
            VALUES (:version,:source,:section,:sectionpos,:title,:content,:video,:position)');
        foreach (json_decode($json,true,flags:JSON_THROW_ON_ERROR) as $lesson) {
            $insert->execute([
                'version'=>$id,'source'=>$lesson['source_lesson_id'],'section'=>$lesson['section_title'],
                'sectionpos'=>$lesson['section_position'],'title'=>$lesson['title'],
                'content'=>$lesson['content'],'video'=>$lesson['video_url'],'position'=>$lesson['lesson_position']
            ]);
        }
        $this->audit($actor,'curriculum.version.published','version',$id,['course_id'=>(int)$course['id'],'version'=>$next]);
        return $id;
    }

    /**
     * Explicit one-time backfill. Existing enrollments and completed legacy lesson IDs
     * are mapped to version 1. Never automatically changes active student versions.
     */
    public function establishBaseline(int $adminId, int $courseId): int
    {
        $this->db->beginTransaction();
        try {
            $this->role($adminId,'admin');
            $course=$this->lockedCourse($courseId);
            if ($course['status']!=='published' || $this->latest($courseId)!==null) {
                throw new RuntimeException('Course already versioned or not published.');
            }
            $id=$this->writeVersion($adminId,$course,$this->fromLegacy($courseId));
            $pin=$this->db->prepare('INSERT INTO lm_enrollment_versions(enrollment_id,version_id)
                SELECT id,:version FROM lm_enrollments WHERE course_id=:course');
            $pin->execute(['version'=>$id,'course'=>$courseId]);
            $progress=$this->db->prepare('INSERT IGNORE INTO lm_version_lesson_progress(enrollment_id,version_lesson_id,completed_at)
                SELECT e.id,vl.id,lp.completed_at
                FROM lm_enrollments e
                JOIN lm_lesson_progress lp ON lp.student_id=e.student_id
                JOIN lm_version_lessons vl ON vl.source_lesson_id=lp.lesson_id AND vl.version_id=:version
                WHERE e.course_id=:course');
            $progress->execute(['version'=>$id,'course'=>$courseId]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    /** Existing students retain their original pinned snapshot, new students use latest. */
    public function enrollFree(int $studentId, int $courseId): void
    {
        $this->db->beginTransaction();
        try {
            $this->role($studentId,'student');
            $course=$this->lockedCourse($courseId);
            if ($course['status']!=='published' || (float)$course['price']!==0.0) {
                throw new RuntimeException('Course unavailable for free enrollment.');
            }
            $q=$this->db->prepare('SELECT id,status FROM lm_enrollments WHERE course_id=:course AND student_id=:student FOR UPDATE');
            $q->execute(['course'=>$courseId,'student'=>$studentId]);
            $old=$q->fetch(PDO::FETCH_ASSOC);
            if ($old && $old['status']==='cancelled') {
                throw new RuntimeException('Cancelled enrollment cannot be reactivated automatically.');
            }
            if (!$old) {
                $q=$this->db->prepare('INSERT INTO lm_enrollments(course_id,student_id) VALUES (:course,:student)');
                $q->execute(['course'=>$courseId,'student'=>$studentId]);
                $enrollment=(int)$this->db->lastInsertId();
            } else { $enrollment=(int)$old['id']; }
            $v=$this->latest($courseId);
            if ($v) {
                $q=$this->db->prepare('INSERT IGNORE INTO lm_enrollment_versions(enrollment_id,version_id) VALUES (:enrollment,:version)');
                $q->execute(['enrollment'=>$enrollment,'version'=>$v['id']]);
            }
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    /** Return null only for legacy enrollments not yet pinned. */
    public function classroom(int $studentId, string $slug): ?array
    {
        $q=$this->db->prepare("SELECT c.id AS id,v.id version_id,v.title,e.id enrollment_id,e.progress,c.slug
            FROM lm_enrollments e JOIN lm_courses c ON c.id=e.course_id
            JOIN lm_enrollment_versions ev ON ev.enrollment_id=e.id
            JOIN lm_course_versions v ON v.id=ev.version_id
            WHERE e.student_id=:student AND c.slug=:slug AND e.status IN ('active','completed')");
        $q->execute(['student'=>$studentId,'slug'=>$slug]);
        $course=$q->fetch(PDO::FETCH_ASSOC);
        if (!$course) { return null; }
        $lessons=$this->db->prepare('SELECT vl.id AS version_lesson_id,vl.section_title,vl.title,vl.content,vl.video_url,vp.completed_at
            FROM lm_version_lessons vl LEFT JOIN lm_version_lesson_progress vp
            ON vp.version_lesson_id=vl.id AND vp.enrollment_id=:enrollment
            WHERE vl.version_id=:version ORDER BY vl.section_position,vl.lesson_position,vl.id');
        $lessons->execute(['enrollment'=>$course['enrollment_id'],'version'=>$course['version_id']]);
        return ['course'=>$course,'lessons'=>$lessons->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function completeVersionLesson(int $studentId, int $lessonId): string
    {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT e.id enrollment_id,e.course_id,c.slug,ev.version_id
                FROM lm_version_lessons vl JOIN lm_course_versions v ON v.id=vl.version_id
                JOIN lm_enrollment_versions ev ON ev.version_id=v.id
                JOIN lm_enrollments e ON e.id=ev.enrollment_id
                JOIN lm_courses c ON c.id=e.course_id
                WHERE vl.id=:lesson AND e.student_id=:student AND e.status IN ('active','completed')
                AND c.id=v.course_id FOR UPDATE");
            $q->execute(['lesson'=>$lessonId,'student'=>$studentId]);
            $entry=$q->fetch(PDO::FETCH_ASSOC);
            if (!$entry) { throw new RuntimeException('This lesson is not in your enrolled course version.'); }
            $p=$this->db->prepare('INSERT IGNORE INTO lm_version_lesson_progress(enrollment_id,version_lesson_id) VALUES (:enrollment,:lesson)');
            $p->execute(['enrollment'=>$entry['enrollment_id'],'lesson'=>$lessonId]);
            $new=$p->rowCount()===1;
            $count=$this->db->prepare('SELECT COUNT(*) FROM lm_version_lessons WHERE version_id=:version');
            $count->execute(['version'=>$entry['version_id']]);
            $total=(int)$count->fetchColumn();
            $done=$this->db->prepare('SELECT COUNT(*) FROM lm_version_lesson_progress p
                JOIN lm_version_lessons l ON l.id=p.version_lesson_id
                WHERE p.enrollment_id=:enrollment AND l.version_id=:version');
            $done->execute(['enrollment'=>$entry['enrollment_id'],'version'=>$entry['version_id']]);
            $completed=(int)$done->fetchColumn();
            if ($total<1) { throw new RuntimeException('Empty course version.'); }
            $q=$this->db->prepare('UPDATE lm_enrollments SET progress=:progress,status=:status WHERE id=:enrollment');
            $q->execute(['progress'=>round(100*$completed/$total,2),'status'=>$completed===$total?'completed':'active','enrollment'=>$entry['enrollment_id']]);
            if ($new) {
                $this->audit($studentId,'learning.version_lesson.completed','version_lesson',$lessonId,['course_id'=>(int)$entry['course_id']]);
            }
            $this->db->commit();
            return (string)$entry['slug'];
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function createRevision(int $ownerId, int $courseId): int
    {
        $this->db->beginTransaction();
        try {
            $course=$this->lockedCourse($courseId);
            $this->author($ownerId);
            if ((int)$course['instructor_id']!==$ownerId || $course['status']!=='published') {
                throw new RuntimeException('Only the active course instructor may propose a revision.');
            }
            $version=$this->latest($courseId);
            if (!$version) { throw new RuntimeException('Establish the baseline version before revising a published course.'); }
            $q=$this->db->prepare("SELECT COUNT(*) FROM lm_course_revision_drafts WHERE course_id=:course AND state IN ('draft','pending')");
            $q->execute(['course'=>$courseId]);
            if ((int)$q->fetchColumn()>0) { throw new RuntimeException('Finish the existing draft or review first.'); }
            $json=$this->canonical($this->snapshotLessons((int)$version['id']));
            $insert=$this->db->prepare('INSERT INTO lm_course_revision_drafts(course_id,base_version_id,owner_id,snapshot_json,snapshot_sha256)
                VALUES (:course,:base,:owner,:json,:sha)');
            $insert->execute(['course'=>$courseId,'base'=>$version['id'],'owner'=>$ownerId,'json'=>$json,'sha'=>hash('sha256',$json)]);
            $id=(int)$this->db->lastInsertId();
            $this->audit($ownerId,'curriculum.revision.created','revision',$id,['course_id'=>$courseId]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function myRevision(int $ownerId, int $revisionId): ?array
    {
        $q=$this->db->prepare('SELECT d.id,d.course_id,d.state,d.snapshot_json,c.title
            FROM lm_course_revision_drafts d JOIN lm_courses c ON c.id=d.course_id
            WHERE d.id=:id AND d.owner_id=:owner');
        $q->execute(['id'=>$revisionId,'owner'=>$ownerId]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return null; }
        $row['lessons']=json_decode($row['snapshot_json'],true,flags:JSON_THROW_ON_ERROR);
        unset($row['snapshot_json']);
        return $row;
    }

    /** Update a lesson in a detached draft: no live lesson/content tables are touched. */
    public function editRevisionLesson(int $ownerId, int $revisionId, int $index, string $title, string $content, string $video): void
    {
        $this->db->beginTransaction();
        try {
            $this->author($ownerId);
            $q=$this->db->prepare('SELECT d.*,c.instructor_id,c.status course_status FROM lm_course_revision_drafts d
                JOIN lm_courses c ON c.id=d.course_id WHERE d.id=:id FOR UPDATE');
            $q->execute(['id'=>$revisionId]);
            $draft=$q->fetch(PDO::FETCH_ASSOC);
            if (!$draft || (int)$draft['owner_id']!==$ownerId
                || (int)$draft['instructor_id']!==$ownerId || $draft['state']!=='draft'
                || $draft['course_status']!=='published') {
                throw new RuntimeException('Revision cannot be edited.');
            }
            $this->validateDigest($draft);
            $arr=json_decode($draft['snapshot_json'],true,flags:JSON_THROW_ON_ERROR);
            if ($index<0 || !array_key_exists($index,$arr)) {
                throw new \InvalidArgumentException('Lesson index does not exist.');
            }
            $arr[$index]['title']=$title;
            $arr[$index]['content']=$content;
            $arr[$index]['video_url']=$video;
            $json=$this->canonical($arr);
            $update=$this->db->prepare('UPDATE lm_course_revision_drafts SET snapshot_json=:json,snapshot_sha256=:hash WHERE id=:id');
            $update->execute(['json'=>$json,'hash'=>hash('sha256',$json),'id'=>$revisionId]);
            $this->audit($ownerId,'curriculum.revision.edited','revision',$revisionId,['course_id'=>(int)$draft['course_id']]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function submitRevision(int $ownerId, int $revisionId): void
    {
        $this->db->beginTransaction();
        try {
            $this->author($ownerId);
            $q=$this->db->prepare("SELECT d.id,d.course_id,d.base_version_id,d.snapshot_json,d.snapshot_sha256,d.state,c.instructor_id,c.status course_status
                FROM lm_course_revision_drafts d JOIN lm_courses c ON c.id=d.course_id WHERE d.id=:id FOR UPDATE");
            $q->execute(['id'=>$revisionId]);
            $d=$q->fetch(PDO::FETCH_ASSOC);
            if (!$d || $d['state']!=='draft' || (int)$d['instructor_id']!==$ownerId
                || $d['course_status']!=='published') { throw new RuntimeException('Cannot submit revision.'); }
            $this->validateDigest($d);
            $this->canonical(json_decode($d['snapshot_json'],true,flags:JSON_THROW_ON_ERROR));
            $latest=$this->latest((int)$d['course_id']);
            if (!$latest || (int)$latest['id']!==(int)$d['base_version_id']) {
                throw new RuntimeException('Base version is outdated.');
            }
            $q=$this->db->prepare("UPDATE lm_course_revision_drafts SET state='pending' WHERE id=:id");
            $q->execute(['id'=>$revisionId]);
            $this->audit($ownerId,'curriculum.revision.submitted','revision',$revisionId,['course_id'=>(int)$d['course_id']]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function reviewRevision(int $adminId, int $revisionId, string $decision): ?int
    {
        if (!in_array($decision,['approve','reject'],true)) {
            throw new \InvalidArgumentException('Invalid review action.');
        }
        $this->db->beginTransaction();
        try {
            $this->role($adminId,'admin');
            // Identify parent first, then lock it to serialize versions and enrollment pinning.
            $get=$this->db->prepare('SELECT course_id FROM lm_course_revision_drafts WHERE id=:id');
            $get->execute(['id'=>$revisionId]);
            $courseId=(int)$get->fetchColumn();
            $course=$this->lockedCourse($courseId);
            $q=$this->db->prepare('SELECT id,base_version_id,snapshot_json,snapshot_sha256,state FROM lm_course_revision_drafts WHERE id=:id AND course_id=:course FOR UPDATE');
            $q->execute(['id'=>$revisionId,'course'=>$courseId]);
            $d=$q->fetch(PDO::FETCH_ASSOC);
            if (!$d || $d['state']!=='pending' || $course['status']!=='published') {
                throw new RuntimeException('Revision not awaiting review.');
            }
            $version=null;
            if ($decision==='approve') {
                $this->validateDigest($d);
                $last=$this->latest($courseId);
                if (!$last || (int)$last['id']!==(int)$d['base_version_id']) {
                    throw new RuntimeException('Revision base changed. Approval denied.');
                }
                $lessons=json_decode($d['snapshot_json'],true,flags:JSON_THROW_ON_ERROR);
                $version=$this->writeVersion($adminId,$course,$lessons);
            }
            $status=$decision==='approve' ? 'approved' : 'rejected';
            $update=$this->db->prepare('UPDATE lm_course_revision_drafts SET state=:state,reviewed_by=:admin,reviewed_at=CURRENT_TIMESTAMP WHERE id=:id');
            $update->execute(['state'=>$status,'admin'=>$adminId,'id'=>$revisionId]);
            $this->audit($adminId,'curriculum.revision.'.$status,'revision',$revisionId,['course_id'=>$courseId]);
            $this->db->commit();
            return $version;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function pendingRevisions(int $adminId): array
    {
        $this->role($adminId,'admin');
        return $this->db->query("SELECT d.id,d.course_id,d.created_at,c.title,u.name instructor
            FROM lm_course_revision_drafts d JOIN lm_courses c ON c.id=d.course_id
            JOIN lm_users u ON u.id=d.owner_id
            WHERE d.state='pending' ORDER BY d.created_at,d.id LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }
}
