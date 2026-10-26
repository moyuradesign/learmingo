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
if (!in_array($command, ['--check', '--apply'], true) || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php scripts/migrate.php --check|--apply\n");
    exit(2);
}
try {
    $db = Database::connection();
    $target = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if ($target === '') { throw new RuntimeException('Unknown target database.'); }
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
