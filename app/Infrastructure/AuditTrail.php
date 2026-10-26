<?php
declare(strict_types=1);

namespace Learmingo\Infrastructure;

use PDO;
use RuntimeException;

/** Append-only operational events, committed atomically with workflow writes. */
final class AuditTrail
{
    public function __construct(private readonly PDO $db) {}

    public function record(int $actorId, string $action, string $type, int $subjectId, array $context = []): void
    {
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('Audited changes must be inside a database transaction.');
        }
        if ($actorId < 1 || $subjectId < 1
            || !preg_match('/^[a-z][a-z0-9_.]{2,99}$/D', $action)
            || !preg_match('/^[a-z][a-z0-9_]{1,39}$/D', $type)) {
            throw new \InvalidArgumentException('Invalid audit event.');
        }
        // Deliberately permit only small, scalar, nonsensitive metadata.
        if (count($context) > 8) {
            throw new \InvalidArgumentException('Too much audit context.');
        }
        foreach ($context as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z_]{1,32}$/D', $key)
                || (!is_string($value) && !is_int($value) && !is_bool($value))
                || (is_string($value) && strlen($value) > 180)) {
                throw new \InvalidArgumentException('Invalid audit context.');
            }
        }
        $statement = $this->db->prepare(
            'INSERT INTO lm_audit_events(actor_id,action,subject_type,subject_id,context_json)
             VALUES (:actor,:action,:type,:subject,:context)'
        );
        $statement->execute([
            'actor' => $actorId, 'action' => $action, 'type' => $type,
            'subject' => $subjectId,
            'context' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
