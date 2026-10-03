<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/session.php';
start_public_app_session();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/UsageReport.php';
require __DIR__ . '/../app/models/PerformanceAnalytics.php';
require __DIR__ . '/../app/services/BackupService.php';
require __DIR__ . '/../app/services/LoginRateLimiter.php';

BackupService::denyIfRestoreLocked();

if (auth_check()) {
    redirect('index.php');
}

$errors = [];

if (is_post()) {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $rateLimiter = new LoginRateLimiter();
    $clientIp = LoginRateLimiter::clientIp();
    $limit = ['blocked' => true, 'retry_after' => 0];

    try {
        $limit = $rateLimiter->check('internal', $email, $clientIp);
    } catch (RuntimeException $error) {
        error_log('CRM internal login rate limiter failed: ' . $error->getMessage());
        http_response_code(503);
        $errors[] = 'ورود موقتاً در دسترس نیست. لطفاً چند دقیقه دیگر دوباره تلاش کنید.';
    }

    if (!$errors && $limit['blocked']) {
        http_response_code(429);
        header('Retry-After: ' . $limit['retry_after']);
        $errors[] = 'تعداد تلاش‌های ورود بیش از حد مجاز است. چند دقیقه دیگر دوباره تلاش کنید.';
    }

    if (!$errors) {
        $user = User::findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            try {
                $rateLimiter->recordSuccess('internal', $email, $clientIp);
            } catch (RuntimeException $error) {
                error_log('CRM internal login rate limiter failed: ' . $error->getMessage());
                http_response_code(503);
                $errors[] = 'ورود موقتاً در دسترس نیست. لطفاً چند دقیقه دیگر دوباره تلاش کنید.';
            }
            if (!$errors) {
                login_user($user);
                UsageReport::logLogin('user', (int) $user['id']);
                PerformanceAnalytics::startUserSession((int) $user['id']);
                redirect('index.php');
            }
        } else {
            try {
                $limit = $rateLimiter->recordFailure('internal', $email, $clientIp);
            } catch (RuntimeException $error) {
                error_log('CRM internal login rate limiter failed: ' . $error->getMessage());
                http_response_code(503);
                $errors[] = 'ورود موقتاً در دسترس نیست. لطفاً چند دقیقه دیگر دوباره تلاش کنید.';
            }
            if (!$errors && $limit['blocked']) {
                http_response_code(429);
                header('Retry-After: ' . $limit['retry_after']);
                $errors[] = 'تعداد تلاش‌های ورود بیش از حد مجاز است. چند دقیقه دیگر دوباره تلاش کنید.';
            } elseif (!$errors) {
                $errors[] = 'ایمیل یا رمز عبور اشتباه است.';
            }
        }
    }
}

require __DIR__ . '/../app/views/auth/login.php';
