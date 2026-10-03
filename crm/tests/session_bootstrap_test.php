<?php

declare(strict_types=1);

function session_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function remove_session_test_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$base = sys_get_temp_dir() . '/simple-crm-session-' . bin2hex(random_bytes(6));
$core = $base . '/app/core';
mkdir($core, 0700, true);
copy(__DIR__ . '/../app/core/session.php', $core . '/session.php');
require $core . '/session.php';

$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
session_expect(app_session_uses_https() === false, 'Plain HTTP must not be detected as HTTPS.');
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https, http';
session_expect(app_session_uses_https() === false, 'Ambiguous forwarded protocol values must not enable Secure cookies.');
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
session_expect(app_session_uses_https() === true, 'An unambiguous forwarded HTTPS protocol must be recognized.');
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
session_name('ELMCRMTEST');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/crm',
    'domain' => '',
    'secure' => false,
    'httponly' => false,
    'samesite' => '',
]);
session_id(bin2hex(random_bytes(16)));
start_app_session();

$sessionDirectory = $base . '/storage/sessions';
session_expect(is_dir($sessionDirectory), 'Session directory must be created at runtime.');
session_expect(realpath(session_save_path()) === realpath($sessionDirectory), 'PHP must use the private session directory.');
session_expect(!str_starts_with(str_replace('\\', '/', realpath($sessionDirectory)), str_replace('\\', '/', $base . '/public/')), 'Session storage must remain outside public.');
session_expect(!str_starts_with(str_replace('\\', '/', realpath($sessionDirectory)), str_replace('\\', '/', $base . '/public_html/')), 'Session storage must remain outside public_html.');
session_expect(ini_get('session.use_only_cookies') === '1', 'Cookie-only sessions must be enabled.');
session_expect(ini_get('session.use_strict_mode') === '1', 'Strict session mode must be enabled.');
$httpCookie = session_get_cookie_params();
session_expect($httpCookie['httponly'] === true, 'Session cookie must be HttpOnly.');
session_expect($httpCookie['secure'] === false, 'HTTP must not set the Secure cookie flag.');
session_expect(strtolower((string) ($httpCookie['samesite'] ?? '')) === 'lax', 'Session cookie SameSite must be Lax.');
session_expect($httpCookie['path'] === '/crm' && $httpCookie['domain'] === '', 'Existing cookie path and domain must be preserved.');
session_expect(session_name() === 'ELMCRMTEST', 'Existing session name must be preserved.');

$_SESSION['probe'] = 'kept';
$firstId = session_id();
start_app_session();
session_expect(session_id() === $firstId && $_SESSION['probe'] === 'kept', 'An active session bootstrap must be idempotent.');
session_write_close();

$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
session_id(bin2hex(random_bytes(16)));
start_app_session();
$httpsCookie = session_get_cookie_params();
session_expect($httpsCookie['secure'] === true, 'HTTPS must set the Secure cookie flag.');
session_destroy();

$sessionSource = file_get_contents(__DIR__ . '/../app/core/session.php');
session_expect(!str_contains($sessionSource, 'database.php'), 'Session bootstrap must not depend on database configuration.');
session_expect(!str_contains($sessionSource, 'helpers.php'), 'Session bootstrap must not depend on application helpers.');
session_expect(!str_contains($sessionSource, 'User.php') && !str_contains($sessionSource, 'Setting.php'), 'Session bootstrap must not depend on models.');

$entryPoints = ['index.php', 'login.php', 'logout.php', 'portal.php', 'install.php'];
foreach ($entryPoints as $entryPoint) {
    $source = file_get_contents(__DIR__ . '/../public/' . $entryPoint);
    session_expect(!preg_match('/(?<!_)session_start\s*\(/', $source), $entryPoint . ' must not call session_start directly.');
    $requirePosition = strpos($source, "app/core/session.php");
    $startPosition = strpos($source, $entryPoint === 'install.php' ? 'start_app_session()' : 'start_public_app_session()');
    session_expect($requirePosition !== false && $startPosition !== false && $requirePosition < $startPosition, $entryPoint . ' must load and start the shared bootstrap.');
    $sessionUsePosition = strpos($source, '$_SESSION');
    session_expect($sessionUsePosition === false || $startPosition < $sessionUsePosition, $entryPoint . ' must start the session before session data is used.');
}

$installSource = file_get_contents(__DIR__ . '/../public/install.php');
session_expect(strpos($installSource, 'start_app_session()') < strpos($installSource, '$configPath'), 'Installer session must start before installation configuration is accessed.');
session_expect(str_contains($installSource, 'مجوز نوشتن پوشه storage'), 'Installer failure must explain the storage permission requirement.');
$authSource = file_get_contents(__DIR__ . '/../app/core/auth.php');
$portalSource = file_get_contents(__DIR__ . '/../public/portal.php');
session_expect(str_contains($authSource, 'session_regenerate_id(true)'), 'Internal login must retain session ID regeneration.');
session_expect(str_contains($authSource, "\$_SESSION['user']"), 'Internal login must retain the existing user session structure.');
session_expect(str_contains($authSource, 'session_get_cookie_params()'), 'Internal logout must use the active cookie scope when expiring the cookie.');
session_expect(str_contains($authSource, 'session_destroy()'), 'Internal logout must retain session destruction.');
session_expect(str_contains($portalSource, 'session_regenerate_id(true)'), 'Portal login must retain session ID regeneration.');
session_expect(str_contains($portalSource, "\$_SESSION['portal_contact']"), 'Portal login must retain the existing contact session structure.');
session_expect(str_contains($portalSource, "unset(\$_SESSION['portal_contact'])"), 'Portal logout must retain portal session cleanup.');
session_expect(str_contains($portalSource, 'function portal_contact()') && str_contains($portalSource, 'function require_portal_auth()'), 'Portal session authorization helpers must remain available.');

$outsideSessionDirectory = $base . '/outside-sessions';
mkdir($outsideSessionDirectory, 0700, true);
if (is_dir($sessionDirectory)) {
    foreach (glob($sessionDirectory . '/*') ?: [] as $sessionFile) {
        unlink($sessionFile);
    }
    rmdir($sessionDirectory);
}
if (function_exists('symlink') && @symlink($outsideSessionDirectory, $sessionDirectory)) {
    try {
        start_app_session();
        session_expect(false, 'A symlinked session directory must be rejected.');
    } catch (RuntimeException) {
    }
    unlink($sessionDirectory);
}

remove_session_test_tree($base);
echo "Session bootstrap tests passed." . PHP_EOL;
