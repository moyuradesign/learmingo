<?php
declare(strict_types=1);

namespace Learmingo\Services;

use Learmingo\Domain\CoursePolicy;
use PDO;
use RuntimeException;

/**
 * Domain workflows for course authoring, publication and learner progress.
 * Requires the existing InnoDB schema. Controllers must authenticate and
 * check roles first; this service independently enforces ownership and scope.
 */
final class LearningCore
{
    public function __construct(private readonly PDO $db) {}

    public function authorCourse(int $actorId, int $courseId): ?array
    {
        $s = $this->db->prepare('SELECT id,instructor_id,title,slug,description,price,status FROM lm_courses WHERE id=:course AND instructor_id=:actor LIMIT 1');
        $s->execute(['course' => $courseId, 'actor' => $actorId]);
        $course = $s->fetch(PDO::FETCH_ASSOC);
        return is_array($course) ? $course : null;
    }

    public function sections(int $courseId): array
    {
        $s = $this->db->prepare('SELECT id,title,sort_order FROM lm_course_sections WHERE course_id=:course ORDER BY sort_order,id');
        $s->execute(['course' => $courseId]);
        $sections = $s->fetchAll(PDO::FETCH_ASSOC);
        $lessons = $this->db->prepare('SELECT l.id,l.section_id,l.title,l.content,l.video_url,l.sort_order FROM lm_lessons l INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE s.course_id=:course ORDER BY s.sort_order,s.id,l.sort_order,l.id');
        $lessons->execute(['course' => $courseId]);
        $bySection = [];
        foreach ($lessons->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
            $bySection[(int) $lesson['section_id']][] = $lesson;
        }
        foreach ($sections as &$section) {
            $section['lessons'] = $bySection[(int) $section['id']] ?? [];
        }
        unset($section);
        return $sections;
    }

    /** @throws RuntimeException when a course is not editable by its owner. */
    private function lockedDraft(int $actorId, int $courseId): array
    {
        $s = $this->db->prepare('SELECT id,instructor_id,status FROM lm_courses WHERE id=:course FOR UPDATE');
        $s->execute(['course' => $courseId]);
        $course = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($course) || !CoursePolicy::canEdit($course, $actorId)) {
            throw new RuntimeException('Course unavailable or not editable.');
        }
        return $course;
    }

    public function addSection(int $actorId, int $courseId, string $title): void
    {
        if (!CoursePolicy::validTitle($title)) {
            throw new \InvalidArgumentException('Section title is required (maximum 200 characters).');
        }
        $this->db->beginTransaction();
        try {
            $this->lockedDraft($actorId, $courseId);
            $p = $this->db->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM lm_course_sections WHERE course_id=:course');
            $p->execute(['course' => $courseId]);
            $s = $this->db->prepare('INSERT INTO lm_course_sections (course_id,title,sort_order) VALUES (:course,:title,:position)');
            $s->execute(['course' => $courseId, 'title' => trim($title), 'position' => (int) $p->fetchColumn()]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addLesson(int $actorId, int $sectionId, string $title, string $content, string $videoUrl): void
    {
        $this->validateLesson($title, $content, $videoUrl);
        $this->db->beginTransaction();
        try {
            $course = $this->courseForSection($sectionId);
            $this->lockedDraft($actorId, (int) $course);
            $p = $this->db->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM lm_lessons WHERE section_id=:section');
            $p->execute(['section' => $sectionId]);
            $s = $this->db->prepare('INSERT INTO lm_lessons (section_id,title,content,video_url,sort_order) VALUES (:section,:title,:content,:video,:position)');
            $s->execute(['section' => $sectionId, 'title' => trim($title), 'content' => trim($content), 'video' => $videoUrl, 'position' => (int) $p->fetchColumn()]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function editLesson(int $actorId, int $lessonId, string $title, string $content, string $videoUrl): void
    {
        $this->validateLesson($title, $content, $videoUrl);
        $this->db->beginTransaction();
        try {
            $s = $this->db->prepare('SELECT s.course_id FROM lm_lessons l INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE l.id=:lesson');
            $s->execute(['lesson' => $lessonId]);
            $courseId = (int) $s->fetchColumn();
            $this->lockedDraft($actorId, $courseId);
            $s = $this->db->prepare('UPDATE lm_lessons SET title=:title,content=:content,video_url=:video WHERE id=:lesson');
            $s->execute(['title' => trim($title), 'content' => trim($content), 'video' => $videoUrl, 'lesson' => $lessonId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function courseForSection(int $sectionId): int
    {
        $s = $this->db->prepare('SELECT course_id FROM lm_course_sections WHERE id=:section');
        $s->execute(['section' => $sectionId]);
        $id = (int) $s->fetchColumn();
        if ($id < 1) {
            throw new RuntimeException('Section not found.');
        }
        return $id;
    }

    private function validateLesson(string $title, string $content, string $videoUrl): void
    {
        if (!CoursePolicy::validTitle($title)
            || strlen($content) > 100000
            || !CoursePolicy::validVideoUrl($videoUrl)) {
            throw new \InvalidArgumentException('Invalid lesson title, content or video URL.');
        }
    }

    public function submit(int $actorId, int $courseId): void
    {
        $this->db->beginTransaction();
        try {
            $course = $this->lockedDraft($actorId, $courseId);
            $s = $this->db->prepare('SELECT COUNT(*) FROM lm_lessons l INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE s.course_id=:course');
            $s->execute(['course' => $courseId]);
            if (!CoursePolicy::canSubmit($course, $actorId, (int) $s->fetchColumn())) {
                throw new RuntimeException('Add at least one lesson before submitting for review.');
            }
            $update = $this->db->prepare("UPDATE lm_courses SET status='pending' WHERE id=:course AND status='draft'");
            $update->execute(['course' => $courseId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function review(int $actorId, string $role, int $courseId, string $decision): void
    {
        if ($actorId < 1 || $role !== 'admin') {
            throw new RuntimeException('Administrator permissions required.');
        }
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('Invalid review decision.');
        }
        $this->db->beginTransaction();
        try {
            $s = $this->db->prepare('SELECT id,status FROM lm_courses WHERE id=:course FOR UPDATE');
            $s->execute(['course' => $courseId]);
            $course = $s->fetch(PDO::FETCH_ASSOC);
            if (!is_array($course) || !CoursePolicy::canReview($course)) {
                throw new RuntimeException('Course is not awaiting review.');
            }
            if ($decision === 'approve') {
                $count = $this->db->prepare('SELECT COUNT(*) FROM lm_lessons l INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE s.course_id=:course');
                $count->execute(['course' => $courseId]);
                if ((int) $count->fetchColumn() < 1) {
                    throw new RuntimeException('Cannot publish an empty course.');
                }
            }
            $status = $decision === 'approve' ? 'published' : 'draft';
            $u = $this->db->prepare("UPDATE lm_courses SET status=:status WHERE id=:course AND status='pending'");
            $u->execute(['status' => $status, 'course' => $courseId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Marks a lesson once and recalculates progress from authoritative rows.
     * Returns the enrolled course slug for a safe same-origin redirect.
     */
    public function completeLesson(int $studentId, int $lessonId): string
    {
        $this->db->beginTransaction();
        try {
            $s = $this->db->prepare("SELECT e.id,e.course_id,c.slug FROM lm_enrollments e
                INNER JOIN lm_courses c ON c.id=e.course_id
                INNER JOIN lm_course_sections sec ON sec.course_id=c.id
                INNER JOIN lm_lessons l ON l.section_id=sec.id
                WHERE e.student_id=:student AND l.id=:lesson
                AND e.status IN ('active','completed') LIMIT 1 FOR UPDATE");
            $s->execute(['student' => $studentId, 'lesson' => $lessonId]);
            $enrollment = $s->fetch(PDO::FETCH_ASSOC);
            if (!is_array($enrollment)) {
                throw new RuntimeException('Lesson not available to this student.');
            }
            $s = $this->db->prepare('INSERT INTO lm_lesson_progress (lesson_id,student_id) VALUES (:lesson,:student) ON DUPLICATE KEY UPDATE completed_at=completed_at');
            $s->execute(['lesson' => $lessonId, 'student' => $studentId]);
            $total = $this->db->prepare('SELECT COUNT(*) FROM lm_lessons l INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE s.course_id=:course');
            $total->execute(['course' => $enrollment['course_id']]);
            $lessonCount = (int) $total->fetchColumn();
            if ($lessonCount < 1) {
                throw new RuntimeException('Course has no lessons.');
            }
            $done = $this->db->prepare('SELECT COUNT(*) FROM lm_lesson_progress lp INNER JOIN lm_lessons l ON l.id=lp.lesson_id INNER JOIN lm_course_sections s ON s.id=l.section_id WHERE lp.student_id=:student AND s.course_id=:course');
            $done->execute(['student' => $studentId, 'course' => $enrollment['course_id']]);
            $completed = min((int) $done->fetchColumn(), $lessonCount);
            $progress = round(100.0 * $completed / $lessonCount, 2);
            $status = $completed === $lessonCount ? 'completed' : 'active';
            $u = $this->db->prepare('UPDATE lm_enrollments SET progress=:progress,status=:status WHERE id=:enrollment');
            $u->execute(['progress' => $progress, 'status' => $status, 'enrollment' => $enrollment['id']]);
            $this->db->commit();
            return (string) $enrollment['slug'];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
