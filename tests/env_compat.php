<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Environment.php';
use Learmingo\Core\Environment;

$assert = static function (bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "ENV COMPAT FAILED: $label\n"); exit(1); }
};

$path = tempnam(sys_get_temp_dir(), 'learmingo-env-');
if ($path === false) { exit(1); }
try {
    file_put_contents($path, implode("\n", [
        'LEARMINGO_ENV_QA_ALPHA="qa-verified"',
        'LEARMINGO_ENV_QA_ZERO="0"',
        'LEARMINGO_ENV_QA_QUOTED="value with spaces = safe"',
        'LEARMINGO_ENV_QA_NEWLINE="line\\nbreak"',
        'LEARMINGO_ENV_QA_PRECEDENCE="from-file"',
        "LEARMINGO_ENV_QA_SINGLE='single-quoted'",
        'LEARMINGO_ENV_QA_UNSAFE-KEY="ignored"',
        '',
    ]));
    $_ENV['LEARMINGO_ENV_QA_PRECEDENCE'] = 'server-choice';
    Environment::load($path);
    $assert(Environment::get('LEARMINGO_ENV_QA_ALPHA') === 'qa-verified', 'quoted file values');
    $assert(Environment::get('LEARMINGO_ENV_QA_ZERO') === '0', 'zero string');
    $assert(Environment::get('LEARMINGO_ENV_QA_QUOTED') === 'value with spaces = safe', 'embedded equals');
    $assert(Environment::get('LEARMINGO_ENV_QA_SINGLE') === 'single-quoted', 'single quotes');
    $assert(Environment::get('LEARMINGO_ENV_QA_PRECEDENCE') === 'server-choice', 'server precedence');
    $assert(Environment::get('LEARMINGO_ENV_QA_NEWLINE') === false, 'newline injection rejected');
    $assert(Environment::get('LEARMINGO_ENV_QA_UNSAFE-KEY') === false, 'invalid key rejected');
    $assert(Environment::get('LEARMINGO_ENV_QA_ABSENT') === false, 'missing key returns false');
    echo "PASS  PHP environment loader works with current putenv() setting\n";
} finally {
    @unlink($path);
}
