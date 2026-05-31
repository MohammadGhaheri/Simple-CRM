<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/UsageReport.php';

if (auth_check()) {
    redirect('index.php');
}

$errors = [];

if (is_post()) {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $user = User::findByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user);
        UsageReport::logLogin('user', (int) $user['id']);
        redirect('index.php');
    }

    $errors[] = 'ایمیل یا رمز عبور اشتباه است.';
}

require __DIR__ . '/../app/views/auth/login.php';
