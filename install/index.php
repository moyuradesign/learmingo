<?php
declare(strict_types=1);

use Learmingo\Core\Csrf;
use Learmingo\Core\RateLimiter;
use Learmingo\Core\Security;
use Learmingo\Core\Session;

$root = dirname(__DIR__);
require_once $root . '/bootstrap/app.php';
Security::sendHeaders();
Session::start();
header('Content-Type: text/html; charset=UTF-8');

$storage = $root . '/storage';
$lock = $storage . '/installed.lock';
$envPath = $root . '/.env';
if (is_file($lock) || is_file($envPath)) {
    http_response_code(403);
    exit('Learmingo is already installed or setup requires private recovery.');
}
if (Security::postTooLarge()) {
    http_response_code(413);
    exit('Request body too large.');
}
if ((!is_dir($storage) && !@mkdir($storage, 0700, true)) || is_link($storage) || !is_writable($storage)) {
    http_response_code(503);
    exit('Private storage must be writable before installation.');
}

function install_h(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_field(string $key, int $max = 2048): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) && strlen($value) <= $max ? trim($value) : '';
}

function install_requirements(string $root): array
{
    return [
        'PHP 8.1+' => PHP_VERSION_ID >= 80100,
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'OpenSSL' => extension_loaded('openssl'),
        'JSON' => extension_loaded('json'),
        'Mbstring' => extension_loaded('mbstring'),
        'Writable private storage' => is_writable($root . '/storage'),
        'Writable upload directory' => is_writable($root . '/public/uploads'),
    ];
}

function install_db(array $db): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
        $db['username'], $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function install_env(array $db, array $app): string
{
    $values = [
        'APP_NAME' => $app['name'], 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
        'APP_URL' => $app['url'], 'APP_TIMEZONE' => $app['timezone'],
        'APP_KEY' => bin2hex(random_bytes(32)),
        'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'],
        'DB_PASSWORD' => $db['password'], 'DB_PREFIX' => 'lm_',
        'SESSION_NAME' => 'learmingo_session',
    ];
    $out = '';
    foreach ($values as $key => $value) {
        $out .= $key . '=' . json_encode((string) $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
    return $out;
}

function install_private_file(string $path, string $contents): void
{
    $handle = @fopen($path, 'x');
    if (!$handle) {
        throw new RuntimeException('Private setup file cannot be created.');
    }
    try {
        @chmod($path, 0600);
        if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
            throw new RuntimeException('Private setup file could not be written.');
        }
    } catch (Throwable $error) {
        fclose($handle);
        @unlink($path);
        throw $error;
    }
    fclose($handle);
}

$tokenFile = $storage . '/setup.token';
if (!is_file($tokenFile)) {
    try {
        install_private_file($tokenFile, bin2hex(random_bytes(32)));
    } catch (Throwable $error) {
        if (!is_file($tokenFile)) {
            http_response_code(503);
            exit('Unable to initialize private setup access.');
        }
    }
}
$token = @file_get_contents($tokenFile);
if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/D', $token) || is_link($tokenFile)) {
    http_response_code(503);
    exit('Invalid private installer token.');
}

$error = null;
$success = null;
$step = max(1, min(5, (int) ($_POST['step'] ?? $_GET['step'] ?? 1)));
$authorized = (bool) Session::get('_installer_authorized', false);
if (!$authorized && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        $error = 'Invalid setup session. Reload the page.';
    } else {
        try {
            $wait = RateLimiter::hit('installer-ip', Security::clientIp(), 10, 3600);
            if ($wait > 0) {
                http_response_code(429);
                header('Retry-After: ' . $wait);
                $error = 'Too many setup attempts. Try again later.';
            } elseif (hash_equals($token, install_field('setup_token', 64))) {
                Session::regenerate();
                Csrf::rotate();
                Session::put('_installer_authorized', true);
                $authorized = true;
            } else {
                $error = 'Invalid private setup token.';
            }
        } catch (Throwable $exception) {
            http_response_code(503);
            $error = 'Setup verification is temporarily unavailable.';
        }
    }
}

if ($authorized && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['setup_token'])) {
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        http_response_code(419);
        $error = 'Your installer session expired. Reload and try again.';
    } elseif ($step === 2) {
        $db = [
            'host' => install_field('host', 150),
            'port' => install_field('port', 5),
            'database' => install_field('database', 64),
            'username' => install_field('username', 128),
            'password' => is_string($_POST['password'] ?? null) && strlen($_POST['password']) <= 2048 ? $_POST['password'] : '',
        ];
        if (!preg_match('/^[a-zA-Z0-9.:-]+$/D', $db['host'])
            || !ctype_digit($db['port']) || (int) $db['port'] < 1 || (int) $db['port'] > 65535
            || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $db['database'])
            || $db['username'] === '') {
            $error = 'Enter valid database connection settings.';
        } else {
            $db['port'] = (int) $db['port'];
            try {
                install_db($db)->query('SELECT 1');
                Session::put('_installer_db', $db);
                $success = 'Database connection verified.';
            } catch (Throwable $exception) {
                $error = 'Database connection failed. Check your credentials and server.';
            }
        }
    } elseif ($step === 3) {
        $name = install_field('name', 120);
        $url = rtrim(install_field('url', 300), '/');
        $timezone = install_field('timezone', 64);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array($scheme, ['http', 'https'], true)
            || ($scheme === 'http' && !in_array($host, ['localhost', '127.0.0.1'], true))
            || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $error = 'Enter a name, a valid HTTPS URL, and a valid timezone.';
        } else {
            Session::put('_installer_app', compact('name', 'url', 'timezone'));
        }
    } elseif ($step === 4) {
        $name = install_field('admin_name', 150);
        $email = strtolower(install_field('admin_email', 190));
        $password = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
        $confirm = $_POST['admin_password_confirm'] ?? null;
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12 || strlen($password) > 4096 || !is_string($confirm) || $password !== $confirm) {
            $error = 'Enter a name, email, and matching password of at least 12 characters.';
        } else {
            // Never persist plaintext administrator passwords in PHP session storage.
            Session::put('_installer_admin', ['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
    } elseif ($step === 5) {
        try {
            $db = Session::get('_installer_db');
            $config = Session::get('_installer_app');
            $admin = Session::get('_installer_admin');
            if (!is_array($db) || !is_array($config) || !is_array($admin)
                || is_file($envPath) || is_file($lock)) {
                throw new RuntimeException('Incomplete or existing setup.');
            }
            if (in_array(false, install_requirements($root), true)) {
                throw new RuntimeException('Requirements are not satisfied.');
            }
            $pdo = install_db($db);
            $schema = @file_get_contents($root . '/database/schema.sql');
            if (!is_string($schema)) {
                throw new RuntimeException('Schema is unavailable.');
            }
            // DDL autocommits in MySQL. Do not pretend schema installation is transactional.
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }
            $role = (int) $pdo->query("SELECT id FROM lm_roles WHERE slug='admin' LIMIT 1")->fetchColumn();
            if ($role < 1) {
                throw new RuntimeException('Administrator role was not created.');
            }
            $pdo->beginTransaction();
            try {
                // Never overwrite or elevate a preexisting account during setup.
                $statement = $pdo->prepare('INSERT INTO lm_users (role_id,name,email,password) VALUES (:role,:name,:email,:password)');
                $statement->execute(['role' => $role, 'name' => $admin['name'], 'email' => $admin['email'], 'password' => $admin['hash']]);
                $pdo->commit();
            } catch (Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
            install_private_file($envPath, install_env($db, $config));
            install_private_file($lock, gmdate('c') . "\n");
            @unlink($tokenFile);
            unset($_SESSION['_installer_admin'], $_SESSION['_installer_db'], $_SESSION['_installer_app'], $_SESSION['_installer_authorized']);
            Session::regenerate();
            header('Location: /login?installed=1', true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('Learmingo installer failed: ' . get_class($exception));
            http_response_code(500);
            $error = 'Installation could not complete safely. Review server logs and private setup files before retrying.';
        }
    }
    if ($error === null && $step < 5) {
        header('Location: /install/?step=' . ($step + 1), true, 303);
        exit;
    }
}
$requirements = install_requirements($root);
$ready = !in_array(false, $requirements, true);
$csrf = Csrf::token();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Learmingo Secure Installer</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:760px"><div class="card shadow-sm rounded-4"><div class="card-body p-4 p-md-5">
<h1 class="h3 fw-bold">Learmingo setup</h1>
<?php if (!$authorized): ?>
<p class="text-muted">For security, enter your private installation token from <code>storage/setup.token</code> on the server. Never share this token or place it in a URL.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= install_h($error) ?></div><?php endif; ?>
<form method="post" action="/install/"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="setup-token">Private setup token</label><input class="form-control" id="setup-token" name="setup_token" type="password" required autocomplete="off" pattern="[0-9a-f]{64}"></div><button class="btn btn-primary">Unlock setup</button></form>
<?php else: ?>
<p class="text-muted">Step <?= $step ?> of 5 · Secure installation</p>
<div class="progress mb-4"><div class="progress-bar" style="width:<?= $step * 20 ?>%"></div></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= install_h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= install_h($success) ?></div><?php endif; ?>
<?php if ($step === 1): ?>
<h2 class="h5">1. Requirements</h2><ul class="list-group mb-3"><?php foreach ($requirements as $label => $passed): ?><li class="list-group-item d-flex justify-content-between"><?= install_h($label) ?><span class="badge text-bg-<?= $passed ? 'success' : 'danger' ?>"><?= $passed ? 'Pass' : 'Fail' ?></span></li><?php endforeach; ?></ul>
<?php if ($ready): ?><a href="/install/?step=2" class="btn btn-primary">Continue</a><?php else: ?><p class="text-danger">Resolve failed requirements before continuing.</p><?php endif; ?>
<?php elseif ($step === 2): ?>
<h2 class="h5">2. Database</h2><form action="/install/" method="post"><?= csrf_field() ?><input type="hidden" name="step" value="2">
<?php foreach (['host'=>'Host','port'=>'Port','database'=>'Database','username'=>'Username','password'=>'Password'] as $field=>$label): ?>
<div class="mb-3"><label class="form-label" for="db-<?= $field ?>"><?= install_h($label) ?></label><input class="form-control" id="db-<?= $field ?>" type="<?= $field === 'password' ? 'password' : 'text' ?>" name="<?= $field ?>" value="<?= $field === 'password' ? '' : install_h((string)(Session::get('_installer_db')[$field] ?? ($field === 'host' ? '127.0.0.1' : ($field === 'port' ? '3306' : '')))) ?>" required></div>
<?php endforeach; ?><button class="btn btn-primary">Test connection</button></form>
<?php elseif ($step === 3): ?>
<h2 class="h5">3. Application</h2><form action="/install/" method="post"><?= csrf_field() ?><input type="hidden" name="step" value="3">
<div class="mb-3"><label class="form-label">Application name</label><input class="form-control" name="name" value="Learmingo" required></div>
<div class="mb-3"><label class="form-label">HTTPS URL</label><input class="form-control" type="url" name="url" placeholder="https://example.com" required></div>
<div class="mb-3"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="Asia/Phnom_Penh" required></div><button class="btn btn-primary">Continue</button></form>
<?php elseif ($step === 4): ?>
<h2 class="h5">4. Administrator</h2><form action="/install/" method="post"><?= csrf_field() ?><input type="hidden" name="step" value="4">
<div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="admin_name" maxlength="150" required></div>
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="admin_email" maxlength="190" required></div>
<div class="mb-3"><label class="form-label">Password (12+ characters)</label><input class="form-control" type="password" name="admin_password" minlength="12" required></div>
<div class="mb-3"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="admin_password_confirm" minlength="12" required></div><button class="btn btn-primary">Continue</button></form>
<?php else: ?>
<h2 class="h5">5. Finalize setup</h2><p>The installer will create database tables, the first admin account, and private environment and install-lock files. Existing accounts will not be overwritten.</p>
<form action="/install/" method="post"><?= csrf_field() ?><input type="hidden" name="step" value="5"><button class="btn btn-success">Install Learmingo</button></form>
<?php endif; ?>
<?php endif; ?>
</div></div></main></body></html>
