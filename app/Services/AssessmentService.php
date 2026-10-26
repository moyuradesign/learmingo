<?php
declare(strict_types=1);

namespace Learmingo\Services;

use Learmingo\Infrastructure\AuditTrail;
use PDO;
use RuntimeException;

/** Immutable published MCQ quizzes and server-side grading. */
final class AssessmentService
{
    public function __construct(private readonly PDO $db) {}

    private function ownCourse(int $actorId, int $courseId, bool $publishedOnly = false): array
    {
        $q = $this->db->prepare("SELECT c.id,c.status,c.instructor_id FROM lm_courses c JOIN lm_users u ON u.id=c.instructor_id JOIN lm_roles r ON r.id=u.role_id WHERE c.id=:course AND c.instructor_id=:actor AND u.status='active' AND r.slug IN ('admin','instructor') FOR UPDATE");
        $q->execute(['course'=>$courseId,'actor'=>$actorId]);
        $course = $q->fetch(PDO::FETCH_ASSOC);
        if (!$course || ($publishedOnly && $course['status'] !== 'published')) {
            throw new RuntimeException('You cannot manage this course.');
        }
        return $course;
    }

    public function createQuiz(int $instructorId, int $courseId, string $title, int $pass, int $limit): int
    {
        $title = trim($title);
        if ($courseId < 1 || strlen($title) < 3 || strlen($title) > 200
            || $pass < 1 || $pass > 100 || $limit < 1 || $limit > 10) {
            throw new \InvalidArgumentException('Invalid quiz settings.');
        }
        $this->db->beginTransaction();
        try {
            $this->ownCourse($instructorId,$courseId);
            $q = $this->db->prepare('INSERT INTO lm_quizzes(course_id,title,passing_percent,max_attempts) VALUES (:course,:title,:pass,:limit)');
            $q->execute(['course'=>$courseId,'title'=>$title,'pass'=>$pass,'limit'=>$limit]);
            $id=(int)$this->db->lastInsertId();
            (new AuditTrail($this->db))->record($instructorId,'assessment.quiz.created','quiz',$id,['course_id'=>$courseId]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function draftQuiz(int $actorId, int $quizId): array
    {
        $q = $this->db->prepare('SELECT q.id,q.course_id,q.status FROM lm_quizzes q WHERE q.id=:quiz FOR UPDATE');
        $q->execute(['quiz'=>$quizId]);
        $quiz=$q->fetch(PDO::FETCH_ASSOC);
        if (!$quiz || $quiz['status'] !== 'draft') {
            throw new RuntimeException('Quiz is not editable.');
        }
        $this->ownCourse($actorId,(int)$quiz['course_id']);
        return $quiz;
    }

    public function addQuestion(int $actorId, int $quizId, string $prompt, array $options, string $correct, int $points): int
    {
        $prompt=trim($prompt);
        if ($quizId < 1 || strlen($prompt) < 3 || strlen($prompt) > 1000 ||
            !in_array($correct,['a','b','c','d'],true) || $points < 1 || $points > 100 ||
            array_keys($options) !== ['a','b','c','d']) {
            throw new \InvalidArgumentException('Invalid question.');
        }
        foreach ($options as $text) {
            if (!is_string($text) || trim($text)==='' || strlen($text)>500) {
                throw new \InvalidArgumentException('Invalid answer choice.');
            }
        }
        $this->db->beginTransaction();
        try {
            $quiz=$this->draftQuiz($actorId,$quizId);
            $q=$this->db->prepare('SELECT COUNT(*) FROM lm_quiz_questions WHERE quiz_id=:quiz');
            $q->execute(['quiz'=>$quizId]);
            $index=(int)$q->fetchColumn();
            if ($index >= 40) {
                throw new RuntimeException('Maximum 40 questions per quiz.');
            }
            $insert=$this->db->prepare('INSERT INTO lm_quiz_questions(quiz_id,prompt,option_a,option_b,option_c,option_d,correct_option,points,sort_order) VALUES (:quiz,:prompt,:a,:b,:c,:d,:correct,:points,:position)');
            $insert->execute(['quiz'=>$quizId,'prompt'=>$prompt,'a'=>trim($options['a']),'b'=>trim($options['b']),'c'=>trim($options['c']),'d'=>trim($options['d']),'correct'=>$correct,'points'=>$points,'position'=>$index]);
            $id=(int)$this->db->lastInsertId();
            (new AuditTrail($this->db))->record($actorId,'assessment.question.created','question',$id,['quiz_id'=>$quizId,'course_id'=>(int)$quiz['course_id']]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }


    /** Draft-only advanced questions. Full answer keys are never sent to learners. */
    public function addAdvancedQuestion(
        int $actorId,int $quizId,string $format,string $prompt,array $options,string $answerKey,
        int $points,string $explanation = ''
    ): int {
        if (!in_array($format,['true_false','multi_select'],true)) {
            throw new \InvalidArgumentException('Unsupported question format.');
        }
        $prompt=trim($prompt);
        $explanation=trim($explanation);
        if (strlen($prompt)<3 || strlen($prompt)>1000 || strlen($explanation)>1000
            || $points<1 || $points>100 || array_keys($options)!==['a','b','c','d']) {
            throw new \InvalidArgumentException('Invalid question fields.');
        }
        foreach ($options as $value) {
            if (!is_string($value) || trim($value)==='' || strlen($value)>500) {
                throw new \InvalidArgumentException('Invalid choice.');
            }
        }
        $letters=str_split($answerKey);
        if (count($letters)<1 || count($letters)>4 || count(array_unique($letters))!==count($letters)) {
            throw new \InvalidArgumentException('Invalid correct answers.');
        }
        sort($letters,SORT_STRING);
        $answerKey=implode('',$letters);
        foreach ($letters as $choice) {
            if (!in_array($choice,['a','b','c','d'],true)) {
                throw new \InvalidArgumentException('Invalid correct answer.');
            }
        }
        if ($format==='true_false' && !in_array($answerKey,['a','b'],true)) {
            throw new \InvalidArgumentException('True/false has one correct answer.');
        }
        if ($format==='multi_select' && count($letters)<2) {
            throw new \InvalidArgumentException('Multi-select requires two or more correct choices.');
        }
        $this->db->beginTransaction();
        try {
            $quiz=$this->draftQuiz($actorId,$quizId);
            $count=$this->db->prepare('SELECT COUNT(*) FROM lm_quiz_questions WHERE quiz_id=:quiz');
            $count->execute(['quiz'=>$quizId]);
            $order=(int)$count->fetchColumn();
            if ($order>=40) { throw new RuntimeException('Maximum 40 questions per quiz.'); }
            $q=$this->db->prepare('INSERT INTO lm_quiz_questions
                (quiz_id,prompt,option_a,option_b,option_c,option_d,correct_option,points,sort_order)
                VALUES (:quiz,:prompt,:a,:b,:c,:d,:correct,:points,:position)');
            $q->execute(['quiz'=>$quizId,'prompt'=>$prompt,'a'=>trim($options['a']),
                'b'=>trim($options['b']),'c'=>trim($options['c']),'d'=>trim($options['d']),
                'correct'=>$letters[0],'points'=>$points,'position'=>$order]);
            $id=(int)$this->db->lastInsertId();
            $q=$this->db->prepare('INSERT INTO lm_question_formats(question_id,format,answer_key,explanation)
                VALUES (:id,:format,:key,:explanation)');
            $q->execute(['id'=>$id,'format'=>$format,'key'=>$answerKey,'explanation'=>$explanation]);
            (new AuditTrail($this->db))->record($actorId,'assessment.question.created','question',$id,
                ['quiz_id'=>$quizId,'course_id'=>(int)$quiz['course_id']]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    /** Gradebook rows are limited to courses owned by this authenticated instructor. */
    public function gradebook(int $actorId,int $courseId): array
    {
        $q=$this->db->prepare("SELECT c.id FROM lm_courses c
            JOIN lm_users u ON u.id=c.instructor_id
            JOIN lm_roles r ON r.id=u.role_id
            WHERE c.id=:course AND c.instructor_id=:actor AND u.status='active'
              AND r.slug IN ('instructor','admin')");
        $q->execute(['course'=>$courseId,'actor'=>$actorId]);
        if ($q->fetchColumn()===false) { throw new RuntimeException('Course unavailable.'); }
        $q=$this->db->prepare('SELECT a.id,a.attempt_no,a.score_percent,a.passed,a.submitted_at,
             u.name learner,q.title quiz_title
             FROM lm_quiz_attempts a JOIN lm_quizzes q ON q.id=a.quiz_id
             JOIN lm_users u ON u.id=a.student_id WHERE q.course_id=:course
             ORDER BY a.submitted_at DESC,a.id DESC LIMIT 250');
        $q->execute(['course'=>$courseId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Keep sensitive answer keys out of student attempt views. */
    public function studentAttempt(int $studentId,int $attemptId): ?array
    {
        $q=$this->db->prepare('SELECT a.id,a.attempt_no,a.points_earned,a.points_possible,
            a.score_percent,a.passed,a.submitted_at,q.title quiz_title
            FROM lm_quiz_attempts a JOIN lm_quizzes q ON q.id=a.quiz_id
            WHERE a.id=:attempt AND a.student_id=:student LIMIT 1');
        $q->execute(['attempt'=>$attemptId,'student'=>$studentId]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return null; }
        $answers=$this->db->prepare('SELECT x.question_id,x.selected_option,x.points_awarded,
            f.selection_key,qq.prompt,qq.points FROM lm_quiz_answers x
            JOIN lm_quiz_questions qq ON qq.id=x.question_id
            LEFT JOIN lm_answer_selections f ON f.question_id=x.question_id AND f.attempt_id=x.attempt_id
            WHERE x.attempt_id=:attempt ORDER BY qq.sort_order,qq.id');
        $answers->execute(['attempt'=>$attemptId]);
        $r['answers']=$answers->fetchAll(PDO::FETCH_ASSOC);
        return $r;
    }

    public function recentCertificates(int $adminId): array
    {
        $q=$this->db->prepare("SELECT r.slug FROM lm_users u JOIN lm_roles r ON r.id=u.role_id
            WHERE u.id=:id AND u.status='active'");
        $q->execute(['id'=>$adminId]);
        if ($q->fetchColumn()!=='admin') {
            throw new RuntimeException('Administrator access required.');
        }
        return $this->db->query('SELECT id,learner_name,course_title,issued_at,revoked_at
            FROM lm_certificates ORDER BY issued_at DESC,id DESC LIMIT 150')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revokeCertificate(int $adminId,int $certificateId,string $reason): void
    {
        $reason=trim($reason);
        if ($adminId<1 || $certificateId<1 || strlen($reason)<10 || strlen($reason)>500) {
            throw new \InvalidArgumentException('Reason must contain 10–500 characters.');
        }
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT r.slug FROM lm_users u JOIN lm_roles r ON r.id=u.role_id
                WHERE u.id=:id AND u.status='active' FOR UPDATE");
            $q->execute(['id'=>$adminId]);
            if ($q->fetchColumn()!=='admin') { throw new RuntimeException('Administrator required.'); }
            $q=$this->db->prepare('SELECT id,revoked_at FROM lm_certificates WHERE id=:id FOR UPDATE');
            $q->execute(['id'=>$certificateId]);
            $cert=$q->fetch(PDO::FETCH_ASSOC);
            if (!$cert || $cert['revoked_at']!==null) {
                throw new RuntimeException('Certificate unavailable or already revoked.');
            }
            $q=$this->db->prepare('INSERT INTO lm_certificate_revocations(certificate_id,admin_id,reason)
                VALUES (:id,:admin,:reason)');
            $q->execute(['id'=>$certificateId,'admin'=>$adminId,'reason'=>$reason]);
            $q=$this->db->prepare('UPDATE lm_certificates SET revoked_at=CURRENT_TIMESTAMP WHERE id=:id');
            $q->execute(['id'=>$certificateId]);
            (new AuditTrail($this->db))->record($adminId,'learning.certificate.revoked','certificate',$certificateId);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function publishQuiz(int $actorId, int $quizId): void
    {
        $this->db->beginTransaction();
        try {
            $quiz=$this->draftQuiz($actorId,$quizId);
            $this->ownCourse($actorId,(int)$quiz['course_id'],true);
            $q=$this->db->prepare('SELECT COUNT(*) FROM lm_quiz_questions WHERE quiz_id=:quiz');
            $q->execute(['quiz'=>$quizId]);
            if ((int)$q->fetchColumn()<1) {
                throw new RuntimeException('Add questions before publishing.');
            }
            $q=$this->db->prepare("UPDATE lm_quizzes SET status='published' WHERE id=:quiz AND status='draft'");
            $q->execute(['quiz'=>$quizId]);
            (new AuditTrail($this->db))->record($actorId,'assessment.quiz.published','quiz',$quizId,['course_id'=>(int)$quiz['course_id']]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function courseQuizzes(int $courseId): array
    {
        $q=$this->db->prepare('SELECT id,title,passing_percent,max_attempts,status FROM lm_quizzes WHERE course_id=:course ORDER BY id');
        $q->execute(['course'=>$courseId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function quizQuestions(int $quizId, bool $includeKeys = false): array
    {
        $cols="q.id,q.prompt,q.option_a,q.option_b,q.option_c,q.option_d,q.points,COALESCE(f.format,'single') AS format";
        if ($includeKeys) { $cols.=',COALESCE(f.answer_key,q.correct_option) AS answer_key,f.explanation'; }
        $q=$this->db->prepare("SELECT $cols FROM lm_quiz_questions q
            LEFT JOIN lm_question_formats f ON f.question_id=q.id
            WHERE q.quiz_id=:quiz ORDER BY q.sort_order,q.id");
        $q->execute(['quiz'=>$quizId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function availableToStudent(int $studentId, int $quizId): ?array
    {
        $q=$this->db->prepare("SELECT q.id,q.course_id,q.title,q.passing_percent,q.max_attempts,c.slug
            FROM lm_quizzes q INNER JOIN lm_courses c ON c.id=q.course_id
            INNER JOIN lm_enrollments e ON e.course_id=c.id
            WHERE q.id=:quiz AND e.student_id=:student AND q.status='published'
            AND c.status='published' AND e.status IN ('active','completed')");
        $q->execute(['quiz'=>$quizId,'student'=>$studentId]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** A client never provides score, pass status, correct answers or attempt number. */
    public function submit(int $studentId, int $quizId, array $choices): array
    {
        if ($studentId < 1 || $quizId < 1 || count($choices)>40) {
            throw new \InvalidArgumentException('Invalid assessment submission.');
        }
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT q.id,q.course_id,q.passing_percent,q.max_attempts
                FROM lm_quizzes q JOIN lm_courses c ON c.id=q.course_id
                WHERE q.id=:quiz AND q.status='published' AND c.status='published'");
            $q->execute(['quiz'=>$quizId]);
            $quiz=$q->fetch(PDO::FETCH_ASSOC);
            if (!$quiz) { throw new RuntimeException('Quiz unavailable.'); }
            $enroll=$this->db->prepare("SELECT id,status FROM lm_enrollments
                WHERE student_id=:student AND course_id=:course FOR UPDATE");
            $enroll->execute(['student'=>$studentId,'course'=>$quiz['course_id']]);
            $enrollment=$enroll->fetch(PDO::FETCH_ASSOC);
            if (!$enrollment || !in_array($enrollment['status'],['active','completed'],true)) {
                throw new RuntimeException('Enrollment required.');
            }
            $past=$this->db->prepare('SELECT attempt_no,passed FROM lm_quiz_attempts WHERE quiz_id=:quiz AND student_id=:student ORDER BY attempt_no DESC');
            $past->execute(['quiz'=>$quizId,'student'=>$studentId]);
            $attempts=$past->fetchAll(PDO::FETCH_ASSOC);
            if (count($attempts)>=(int)$quiz['max_attempts'] || in_array('1',array_map(static fn($x)=>(string)$x['passed'],$attempts),true)) {
                throw new RuntimeException('No further assessment attempts available.');
            }
            $questions=$this->quizQuestions($quizId,true);
            if (!$questions) { throw new RuntimeException('Quiz has no questions.'); }
            $expected=array_map(static fn($x)=>(string)$x['id'],$questions);
            $provided=[];
            foreach ($choices as $id=>$choice) {
                if ((!is_int($id) && !ctype_digit((string)$id))) {
                    throw new \InvalidArgumentException('Malformed answer identifier.');
                }
                $provided[]=(string)(int)$id;
            }
            sort($expected,SORT_STRING); sort($provided,SORT_STRING);
            if ($expected!==$provided) { throw new \InvalidArgumentException('Answer every question exactly once.'); }
            $normalized=[];
            $earned=0; $possible=0;
            foreach ($questions as $question) {
                $id=(int)$question['id'];
                $format=(string)$question['format'];
                $input=$choices[$id];
                if ($format==='multi_select') {
                    if (!is_array($input) || count($input)<2 || count($input)>4) {
                        throw new \InvalidArgumentException('Choose two or more options.');
                    }
                    foreach ($input as $option) {
                        if (!is_string($option) || !in_array($option,['a','b','c','d'],true)) {
                            throw new \InvalidArgumentException('Invalid multi-select option.');
                        }
                    }
                    if (count(array_unique($input))!==count($input)) {
                        throw new \InvalidArgumentException('Duplicate choice not permitted.');
                    }
                    sort($input,SORT_STRING);
                    $answer=implode('',$input);
                } else {
                    if (!is_string($input) || !in_array($input,$format==='true_false'?['a','b']:['a','b','c','d'],true)) {
                        throw new \InvalidArgumentException('Invalid answer choice.');
                    }
                    $answer=$input;
                }
                $normalized[$id]=$answer;
                $possible+=(int)$question['points'];
                if ($answer === $question['answer_key']) {
                    $earned+=(int)$question['points'];
                }
            }
            $score=round($earned*100/$possible,2);
            $pass=$score >= (int)$quiz['passing_percent'];
            $no=count($attempts)+1;
            $s=$this->db->prepare('INSERT INTO lm_quiz_attempts(quiz_id,student_id,attempt_no,points_earned,points_possible,score_percent,passed)
                VALUES (:quiz,:student,:no,:earned,:possible,:score,:passed)');
            $s->execute(['quiz'=>$quizId,'student'=>$studentId,'no'=>$no,'earned'=>$earned,'possible'=>$possible,'score'=>$score,'passed'=>(int)$pass]);
            $id=(int)$this->db->lastInsertId();
            $answer=$this->db->prepare('INSERT INTO lm_quiz_answers(attempt_id,question_id,selected_option,points_awarded) VALUES (?,?,?,?)');
            $detail=$this->db->prepare('INSERT INTO lm_answer_selections(attempt_id,question_id,selection_key)
                VALUES (:attempt,:question,:selection)');
            foreach ($questions as $question) {
                $selected=$normalized[(int)$question['id']];
                $points=$selected===$question['answer_key'] ? (int)$question['points'] : 0;
                $answer->execute([$id,$question['id'],$selected[0],$points]);
                $detail->execute(['attempt'=>$id,'question'=>$question['id'],'selection'=>$selected]);
            }
            (new AuditTrail($this->db))->record($studentId,'assessment.quiz.graded','attempt',$id,['quiz_id'=>$quizId,'passed'=>$pass]);
            $this->db->commit();
            return ['attempt_id'=>$id,'score'=>$score,'passed'=>$pass,'attempt_no'=>$no];
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function issueCertificate(int $studentId, int $courseId): array
    {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT e.id,e.status,c.title,u.name FROM lm_enrollments e
                JOIN lm_courses c ON c.id=e.course_id JOIN lm_users u ON u.id=e.student_id
                WHERE e.student_id=:student AND e.course_id=:course AND c.status='published'
                  AND u.status='active' FOR UPDATE");
            $q->execute(['student'=>$studentId,'course'=>$courseId]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'completed') {
                throw new RuntimeException('Complete all required lessons first.');
            }
            $existing=$this->db->prepare('SELECT id,credential,learner_name,course_title,revoked_at FROM lm_certificates WHERE student_id=:student AND course_id=:course');
            $existing->execute(['student'=>$studentId,'course'=>$courseId]);
            $cert=$existing->fetch(PDO::FETCH_ASSOC);
            if ($cert) {
                if ($cert['revoked_at'] !== null) {
                    throw new RuntimeException('Revoked certificate cannot be reissued automatically.');
                }
                $this->db->commit(); return $cert;
            }
            // A passing attempt on a published quiz belonging to this course is mandatory.
            $passed=$this->db->prepare("SELECT a.id FROM lm_quiz_attempts a JOIN lm_quizzes q ON q.id=a.quiz_id
                WHERE a.student_id=:student AND q.course_id=:course AND a.passed=1 AND q.status='published'
                ORDER BY a.submitted_at DESC,a.id DESC LIMIT 1");
            $passed->execute(['student'=>$studentId,'course'=>$courseId]);
            $attempt=(int)$passed->fetchColumn();
            if ($attempt<1) { throw new RuntimeException('Pass a published assessment before requesting a certificate.'); }
            // Unique random 256-bit credential; never derive from enumerable user/course IDs.
            $credential=bin2hex(random_bytes(32));
            $insert=$this->db->prepare('INSERT INTO lm_certificates(course_id,student_id,passing_attempt_id,credential,learner_name,course_title)
                VALUES (:course,:student,:attempt,:credential,:name,:title)');
            $insert->execute(['course'=>$courseId,'student'=>$studentId,'attempt'=>$attempt,'credential'=>$credential,'name'=>$row['name'],'title'=>$row['title']]);
            $id=(int)$this->db->lastInsertId();
            (new AuditTrail($this->db))->record($studentId,'learning.certificate.issued','certificate',$id,['course_id'=>$courseId]);
            $this->db->commit();
            return ['id'=>$id,'credential'=>$credential,'learner_name'=>$row['name'],'course_title'=>$row['title']];
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function verifyCredential(string $credential): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$credential)) { return null; }
        $q=$this->db->prepare('SELECT learner_name,course_title,issued_at FROM lm_certificates WHERE credential=:credential AND revoked_at IS NULL LIMIT 1');
        $q->execute(['credential'=>$credential]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
}
