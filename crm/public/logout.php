<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/PerformanceAnalytics.php';

if (auth_check()) {
    PerformanceAnalytics::endUserSession(current_user_id());
}
logout_user();
redirect('login.php');
