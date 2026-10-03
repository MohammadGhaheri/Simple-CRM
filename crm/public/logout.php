<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/session.php';
start_public_app_session();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/PerformanceAnalytics.php';
require __DIR__ . '/../app/services/BackupService.php';

BackupService::denyIfRestoreLocked();

if (auth_check()) {
    PerformanceAnalytics::endUserSession(current_user_id());
}
logout_user();
redirect('login.php');
