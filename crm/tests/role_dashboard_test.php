<?php

declare(strict_types=1);

// Static acceptance checks; end-to-end access control requires an HTTP/MySQL test suite.
$root = dirname(__DIR__);
$model = file_get_contents($root . '/app/models/RoleDashboard.php');
$router = file_get_contents($root . '/public/index.php');
$view = file_get_contents($root . '/app/views/dashboard/role.php');

function role_dashboard_expect(bool $ok, string $description): void
{
    if (!$ok) {
        fwrite(STDERR, $description . PHP_EOL);
        exit(1);
    }
}

role_dashboard_expect(str_contains($router, 'if (!is_admin())'), 'Admin and non-admin homepages must be separated.');
role_dashboard_expect(str_contains($router, 'RoleDashboard::build(current_user_role(), current_user_id())'), 'Dashboard scope must come from authenticated session.');
role_dashboard_expect(str_contains($router, "render('dashboard/role'"), 'Non-admins must render the dedicated role homepage.');
role_dashboard_expect(str_contains($model, 'WHERE d.owner_user_id = ?'), 'Sales dashboard must filter deals by owner.');
role_dashboard_expect(str_contains($model, 'WHERE t.assigned_user_id=?'), 'Support dashboard must filter tickets by assignee.');
role_dashboard_expect(str_contains($model, "Activity::agendaCountsForOwner(\$userId)"), 'Own tasks count must be scoped by user.');
role_dashboard_expect(str_contains($model, "Activity::agendaForOwner(\$userId, 'today')"), 'Task list must be scoped by user.');
role_dashboard_expect(str_contains($model, "elseif (\$role === 'finance')"), 'Finance must have its own summary.');
role_dashboard_expect(str_contains($model, 'تعهدات نصب و تحویل پس از راه‌اندازی'), 'Operations must not report fictional install figures.');
role_dashboard_expect(str_contains($view, "format_money(\$metric['value'])"), 'Financial metrics must use configured money display.');
role_dashboard_expect(!str_contains($model, 'Deal::statsByStage()'), 'Non-admins must not load global stage data.');
role_dashboard_expect(!str_contains($model, 'Customer::statsByType()'), 'Non-admins must not load global customer breakdown.');

echo "Role dashboard static checks passed." . PHP_EOL;
