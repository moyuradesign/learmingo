<?php
declare(strict_types=1);

namespace Learmingo\Services;

use Learmingo\Infrastructure\AuditTrail;
use PDO;
use RuntimeException;

/** Student-to-instructor applications with administrator-only approval. */
final class InstructorApplications
{
    public function __construct(private readonly PDO $db) {}

    private function assertRole(int $id, string $role): void
    {
        $q = $this->db->prepare('SELECT r.slug FROM lm_users u JOIN lm_roles r ON r.id=u.role_id WHERE u.id=:id AND u.status=\'active\' FOR UPDATE');
        $q->execute(['id' => $id]);
        if ($q->fetchColumn() !== $role) {
            throw new RuntimeException('Role is not eligible for this action.');
        }
    }

    public function apply(int $applicantId, string $statement): void
    {
        $statement = trim($statement);
        if ($applicantId < 1 || strlen($statement) < 30 || strlen($statement) > 3000) {
            throw new \InvalidArgumentException('Provide a 30–3000 character teaching statement.');
        }
        $this->db->beginTransaction();
        try {
            $this->assertRole($applicantId, 'student');
            $q = $this->db->prepare('SELECT id,status FROM lm_instructor_applications WHERE applicant_id=:id FOR UPDATE');
            $q->execute(['id' => $applicantId]);
            $application = $q->fetch(PDO::FETCH_ASSOC);
            if ($application && $application['status'] !== 'rejected') {
                throw new RuntimeException('You already have an application awaiting or approved after review.');
            }
            if ($application) {
                $q = $this->db->prepare("UPDATE lm_instructor_applications SET statement=:statement,status='pending',reviewed_by=NULL,review_note=NULL,reviewed_at=NULL,submitted_at=CURRENT_TIMESTAMP WHERE id=:id");
                $q->execute(['statement' => $statement,'id' => $application['id']]);
                $id = (int)$application['id'];
            } else {
                $q = $this->db->prepare('INSERT INTO lm_instructor_applications(applicant_id,statement) VALUES (:applicant,:statement)');
                $q->execute(['applicant' => $applicantId,'statement' => $statement]);
                $id = (int)$this->db->lastInsertId();
            }
            (new AuditTrail($this->db))->record($applicantId, 'instructor.application.submitted', 'application', $id);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function review(int $adminId, int $applicationId, string $decision, string $note): void
    {
        if ($adminId < 1 || $applicationId < 1 || !in_array($decision,['approve','reject'],true) || strlen($note) > 500) {
            throw new \InvalidArgumentException('Invalid application review.');
        }
        $this->db->beginTransaction();
        try {
            $this->assertRole($adminId,'admin');
            $q = $this->db->prepare('SELECT id,applicant_id,status FROM lm_instructor_applications WHERE id=:id FOR UPDATE');
            $q->execute(['id' => $applicationId]);
            $app = $q->fetch(PDO::FETCH_ASSOC);
            if (!$app || $app['status'] !== 'pending' || (int)$app['applicant_id'] === $adminId) {
                throw new RuntimeException('Application unavailable or already reviewed.');
            }
            $this->assertRole((int)$app['applicant_id'],'student');
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            if ($decision === 'approve') {
                $q = $this->db->prepare("UPDATE lm_users SET role_id=(SELECT id FROM lm_roles WHERE slug='instructor') WHERE id=:id");
                $q->execute(['id' => $app['applicant_id']]);
                if ($q->rowCount() !== 1) {
                    throw new RuntimeException('Applicant role update failed.');
                }
            }
            $q = $this->db->prepare('UPDATE lm_instructor_applications SET status=:status,reviewed_by=:reviewer,review_note=:note,reviewed_at=CURRENT_TIMESTAMP WHERE id=:id');
            $q->execute(['status' => $status,'reviewer' => $adminId,'note' => trim($note),'id' => $applicationId]);
            (new AuditTrail($this->db))->record($adminId, 'instructor.application.' . $status,'application',$applicationId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function mine(int $userId): ?array
    {
        $q = $this->db->prepare('SELECT id,status,statement,review_note,submitted_at,reviewed_at FROM lm_instructor_applications WHERE applicant_id=:id LIMIT 1');
        $q->execute(['id' => $userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function pending(int $adminId): array
    {
        $q = $this->db->prepare("SELECT r.slug FROM lm_users u INNER JOIN lm_roles r ON r.id=u.role_id WHERE u.id=:id AND u.status='active'");
        $q->execute(['id' => $adminId]);
        if ($q->fetchColumn() !== 'admin') {
            throw new RuntimeException('Administrator access required.');
        }
        return $this->db->query("SELECT a.id,a.statement,a.submitted_at,u.name,u.email FROM lm_instructor_applications a JOIN lm_users u ON u.id=a.applicant_id WHERE a.status='pending' ORDER BY a.submitted_at,a.id LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }
}
