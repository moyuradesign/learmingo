<?php
declare(strict_types=1);

namespace Learmingo\Infrastructure;

use PDO;
use RuntimeException;

/**
 * Explicit, checksummed, forward-only SQL migrations.
 * MySQL DDL autocommits. Back up data before --apply; never claim DDL rollback.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $directory
    ) {}

    private function files(): array
    {
        $paths = glob($this->directory . '/*.sql');
        if ($paths === false) {
            throw new RuntimeException('Cannot list database migrations.');
        }
        sort($paths, SORT_STRING);
        $found = [];
        foreach ($paths as $path) {
            if (is_link($path) || !is_file($path)
                || !preg_match('/^[0-9]{8}_[0-9]{6}_[a-z0-9_]+\.sql$/D', basename($path))) {
                throw new RuntimeException('Unsafe migration filename.');
            }
            $name = basename($path);
            $checksum = hash_file('sha256', $path);
            if (!is_string($checksum)) {
                throw new RuntimeException('Cannot checksum migration.');
            }
            $found[$name] = ['path' => $path, 'checksum' => $checksum];
        }
        return $found;
    }

    private function journalExists(): bool
    {
        $s = $this->db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='lm_schema_migrations'");
        return (int) $s->fetchColumn() === 1;
    }

    public function status(): array
    {
        $files = $this->files();
        if (!$this->journalExists()) {
            return array_map(static fn(array $f): string => 'PENDING', $files);
        }
        $applied = $this->db->query('SELECT filename,checksum FROM lm_schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($applied as $filename => $checksum) {
            if (!isset($files[$filename]) || !hash_equals($files[$filename]['checksum'], (string)$checksum)) {
                throw new RuntimeException('Migration ledger differs from approved source. Stop for review.');
            }
        }
        $status = [];
        foreach ($files as $filename => $f) {
            $status[$filename] = isset($applied[$filename]) ? 'APPLIED' : 'PENDING';
        }
        return $status;
    }

    public function apply(): array
    {
        $name = (string) $this->db->query('SELECT DATABASE()')->fetchColumn();
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
            throw new RuntimeException('Explicit, valid target database required.');
        }
        $lock = 'lm_migrate_' . substr(hash('sha256', $name), 0, 32);
        $acquire = $this->db->prepare('SELECT GET_LOCK(:name,10)');
        $acquire->execute(['name' => $lock]);
        if ((int)$acquire->fetchColumn() !== 1) {
            throw new RuntimeException('Migration lock unavailable.');
        }
        try {
            $files = $this->files();
            // Validate existing ledger *before* any DDL.
            $status = $this->status();
            if (!$this->journalExists()) {
                $this->db->exec("CREATE TABLE lm_schema_migrations (
                    filename VARCHAR(190) NOT NULL PRIMARY KEY,
                    checksum CHAR(64) NOT NULL,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            foreach ($status as $filename => $state) {
                if ($state === 'APPLIED') {
                    continue;
                }
                $sql = file_get_contents($files[$filename]['path']);
                if (!is_string($sql) || trim($sql) === '') {
                    throw new RuntimeException('Migration SQL unavailable.');
                }
                // One DDL statement per file. No hand-rolled SQL splitting.
                $statement = rtrim(trim($sql), ';' . " \r\n\t");
                if (str_contains($statement, ';')) {
                    throw new RuntimeException('Only one SQL statement per migration is supported.');
                }
                $this->db->exec($statement);
                $insert = $this->db->prepare('INSERT INTO lm_schema_migrations(filename,checksum) VALUES (:file,:hash)');
                $insert->execute(['file' => $filename, 'hash' => $files[$filename]['checksum']]);
            }
            return $this->status();
        } finally {
            $release = $this->db->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $lock]);
        }
    }
}
