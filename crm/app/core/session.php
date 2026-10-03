<?php

declare(strict_types=1);

function app_session_uses_https(): bool
{
    $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    // Accept only the unambiguous value commonly supplied by a TLS-terminating proxy.
    return strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (session_status() === PHP_SESSION_DISABLED) {
        throw new RuntimeException('راه‌اندازی نشست کاربری روی این سرور امکان‌پذیر نیست.');
    }

    $storageRoot = dirname(__DIR__, 2) . '/storage';
    $sessionPath = $storageRoot . '/sessions';

    if (is_link($sessionPath)) {
        throw new RuntimeException('مسیر امن نشست‌های کاربری معتبر نیست.');
    }

    if (!is_dir($sessionPath) && !@mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
        throw new RuntimeException('ساخت فضای امن نشست‌های کاربری ناموفق بود.');
    }

    $storageRealPath = realpath($storageRoot);
    $sessionRealPath = realpath($sessionPath);
    if ($storageRealPath === false || $sessionRealPath === false) {
        throw new RuntimeException('مسیر امن نشست‌های کاربری قابل تأیید نیست.');
    }

    $storageBoundary = rtrim(str_replace('\\', '/', $storageRealPath), '/') . '/';
    $normalizedSessionPath = rtrim(str_replace('\\', '/', $sessionRealPath), '/') . '/';
    if (!str_starts_with($normalizedSessionPath, $storageBoundary) || !is_writable($sessionRealPath)) {
        throw new RuntimeException('فضای امن نشست‌های کاربری قابل نوشتن نیست.');
    }

    if (DIRECTORY_SEPARATOR !== '\\') {
        @chmod($sessionRealPath, 0700);
    }

    $cookie = session_get_cookie_params();
    if (@ini_set('session.use_only_cookies', '1') === false
        || @ini_set('session.use_strict_mode', '1') === false
        || @session_save_path($sessionRealPath) === false
        || !@session_set_cookie_params([
            'lifetime' => (int) $cookie['lifetime'],
            'path' => (string) $cookie['path'],
            'domain' => (string) $cookie['domain'],
            'secure' => app_session_uses_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ])) {
        throw new RuntimeException('پیکربندی امن نشست کاربری ناموفق بود.');
    }

    if (!@session_start()) {
        throw new RuntimeException('راه‌اندازی نشست کاربری ناموفق بود.');
    }
}

function start_public_app_session(): void
{
    try {
        start_app_session();
    } catch (RuntimeException $error) {
        error_log('CRM session bootstrap failed: ' . $error->getMessage());
        http_response_code(500);
        exit('راه‌اندازی نشست کاربری ناموفق بود. لطفاً با مدیر سامانه تماس بگیرید.');
    }
}
