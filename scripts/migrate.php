<?php
declare(strict_types=1);

// Operator-only CLI tool, never exposed through an HTTP route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

use Learmingo\Core\Database;
use Learmingo\Infrastructure\MigrationRunner;

$command = $argv[1] ?? '';
if ($command === '--check' && count($argv) !== 2) {
    fwrite(STDERR, "Usage: php scripts/migrate.php --check\n");
    exit(2);
}
if ($command === '--apply' && (count($argv) !== 4
    || ($argv[3] ?? '') !== 'I_HAVE_A_VERIFIED_DATABASE_BACKUP')) {
    fwrite(STDERR, "Usage: php scripts/migrate.php --apply TARGET_DB I_HAVE_A_VERIFIED_DATABASE_BACKUP\n");
    exit(2);
}
if (!in_array($command, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Invalid migration command.\n");
    exit(2);
}
try {
    $db = Database::connection();
    $target = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if ($target === '') { throw new RuntimeException('Unknown target database.'); }
    if ($command === '--apply' && !hash_equals($target, (string)$argv[2])) {
        throw new RuntimeException('Target database does not match expected name.');
    }
    $runner = new MigrationRunner($db, $root . '/database/migrations');
    $statuses = $command === '--apply' ? $runner->apply() : $runner->status();
    foreach ($statuses as $name => $state) {
        echo $state . '  ' . $name . "\n";
    }
    echo 'MODE=' . ($command === '--apply' ? 'APPLY' : 'CHECK') . "\n";
    echo 'RESULT=' . (in_array('PENDING', $statuses, true) ? 'PENDING_MIGRATIONS' : 'MIGRATIONS_CURRENT') . "\n";
    echo 'TARGET_DATABASE_SHA256=' . substr(hash('sha256', $target), 0, 16) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'BLOCK  Migration runner: ' . get_class($error) . ' (review schema/ledger/permissions)' . "\n");
    exit(1);
}
