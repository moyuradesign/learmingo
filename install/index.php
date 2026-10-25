<?php
declare(strict_types=1);

session_start();
$root = dirname(__DIR__);
$step = max(1, min(5, (int)($_GET['step'] ?? $_POST['step'] ?? 1)));
$error = null;
$success = null;

function installer_requirements(string $root): array
{
    return [
        'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO extension' => extension_loaded('pdo'),
        'PDO MySQL extension' => extension_loaded('pdo_mysql'),
        'OpenSSL extension' => extension_loaded('openssl'),
        'JSON extension' => extension_loaded('json'),
        'Mbstring extension' => extension_loaded('mbstring'),
        'Writable storage directory' => is_writable($root . '/storage'),
        'Writable upload directory' => is_writable($root . '/public/uploads'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $_SESSION['db'] = array_map('trim', [
            'host' => $_POST['host'] ?? '127.0.0.1', 'port' => $_POST['port'] ?? '3306',
            'database' => $_POST['database'] ?? '', 'username' => $_POST['username'] ?? '', 'password' => $_POST['password'] ?? '',
        ]);
        try {
            $db = $_SESSION['db'];
            $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $success = 'Database connection successful.';
        } catch (Throwable $exception) {
            $error = 'Unable to connect to the database. Check the credentials and database name.';
        }
    }
    if ($step === 3) {
        $_SESSION['app'] = ['name' => trim($_POST['name'] ?? 'Learmingo'), 'url' => rtrim(trim($_POST['url'] ?? ''), '/'), 'timezone' => trim($_POST['timezone'] ?? 'UTC')];
    }
    if ($step === 4) {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $password !== ($_POST['password_confirmation'] ?? '')) {
            $error = 'Enter a valid name, email, matching password, and a password of at least 8 characters.';
        } else {
            $_SESSION['admin'] = ['name' => $name, 'email' => $email, 'password' => $password];
        }
    }
    if (!$error && $step < 5) { header('Location: index.php?step=' . ($step + 1)); exit; }
}

$requirements = installer_requirements($root);
$allPassed = !in_array(false, $requirements, true);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Learmingo Installer</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:760px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3">Learmingo Installer</h1><p class="text-muted">Step <?= $step ?> of 5</p><div class="progress mb-4"><div class="progress-bar" style="width:<?= $step * 20 ?>%"></div></div><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($step === 1): ?><h2 class="h5">1. Server requirements</h2><ul class="list-group mb-3"><?php foreach ($requirements as $label => $passed): ?><li class="list-group-item d-flex justify-content-between"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="badge text-bg-<?= $passed ? 'success' : 'danger' ?>"><?= $passed ? 'Pass' : 'Fail' ?></span></li><?php endforeach; ?></ul><a class="btn btn-primary <?= $allPassed ? '' : 'disabled' ?>" href="?step=2">Continue</a>
<?php elseif ($step === 2): ?><h2 class="h5">2. Database connection</h2><form method="post"><input type="hidden" name="step" value="2"><?php foreach (['host'=>'Database host','port'=>'Database port','database'=>'Database name','username'=>'Database username','password'=>'Database password'] as $field => $label): ?><div class="mb-3"><label class="form-label"><?= $label ?></label><input class="form-control" type="<?= $field === 'password' ? 'password' : 'text' ?>" name="<?= $field ?>" value="<?= htmlspecialchars($_SESSION['db'][$field] ?? ($field === 'host' ? '127.0.0.1' : ($field === 'port' ? '3306' : '')), ENT_QUOTES, 'UTF-8') ?>" required></div><?php endforeach; ?><button class="btn btn-primary">Test and continue</button></form>
<?php elseif ($step === 3): ?><h2 class="h5">3. Application configuration</h2><form method="post"><input type="hidden" name="step" value="3"><div class="mb-3"><label class="form-label">Application name</label><input class="form-control" name="name" value="Learmingo" required></div><div class="mb-3"><label class="form-label">Application URL</label><input class="form-control" type="url" name="url" placeholder="https://example.com" required></div><div class="mb-3"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="UTC" required></div><button class="btn btn-primary">Continue</button></form>
<?php elseif ($step === 4): ?><h2 class="h5">4. Administrator account</h2><form method="post"><input type="hidden" name="step" value="4"><div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8" required></div><div class="mb-3"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="password_confirmation" minlength="8" required></div><button class="btn btn-primary">Continue</button></form>
<?php else: ?><h2 class="h5">5. Complete installation</h2><p>Configuration is validated. Database migrations and the administrator account will be finalized in the next installer service implementation.</p><div class="alert alert-warning">Do not expose the installer publicly after setup. Create <code>storage/installed.lock</code> when installation is complete.</div><a class="btn btn-success" href="../">Open Learmingo</a><?php endif; ?></div></div></main></body></html>
