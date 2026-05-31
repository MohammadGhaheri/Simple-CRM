<?php

declare(strict_types=1);

function auth_check(): bool
{
    return !empty($_SESSION['user']);
}

function require_auth(): void
{
    if (!auth_check()) {
        redirect('login.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'sales',
    ];
}

function current_user_role(): string
{
    return (string) ($_SESSION['user']['role'] ?? 'sales');
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('دسترسی به این بخش فقط برای مدیر سیستم مجاز است.');
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
