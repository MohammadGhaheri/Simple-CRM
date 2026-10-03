<?php

declare(strict_types=1);

function permission_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = file_get_contents(__DIR__ . '/../public/index.php');
$dealsStart = strpos($controller, "if (\$page === 'deals')");
$contractsStart = strpos($controller, "if (\$page === 'contracts')");
$activitiesStart = strpos($controller, "if (\$page === 'activities')");
$dealsController = substr($controller, $dealsStart, $contractsStart - $dealsStart);
$contractsController = substr($controller, $contractsStart, $activitiesStart - $contractsStart);

foreach (['deals' => $dealsController, 'contracts' => $contractsController] as $entity => $source) {
    $deleteAt = strpos($source, "if (\$action === 'delete' && is_post())");
    $adminAt = strpos($source, 'require_admin()', $deleteAt);
    $deleteActionAt = strpos($source, 'delete_action(', $deleteAt);
    permission_expect($deleteAt !== false && $adminAt > $deleteAt && $adminAt < $deleteActionAt, ucfirst($entity) . ' delete must require admin before mutation and CSRF handling.');
}

$dealView = file_get_contents(__DIR__ . '/../app/views/deals/index.php');
$contractView = file_get_contents(__DIR__ . '/../app/views/contracts/index.php');
permission_expect(str_contains($dealView, '<?php if (is_admin()): ?>') && str_contains($dealView, "action' => 'delete'"), 'Deal delete button must be admin-only.');
permission_expect(str_contains($contractView, '<?php if (is_admin()): ?>') && str_contains($contractView, "action' => 'delete'"), 'Contract delete button must be admin-only.');

$authSource = file_get_contents(__DIR__ . '/../app/core/auth.php');
permission_expect(str_contains($authSource, "current_user_role() === 'admin'") && str_contains($authSource, 'http_response_code(403)'), 'Sales, support, and operations roles must receive a server-side 403.');

function redirect(string $path): void
{
}

require __DIR__ . '/../app/core/auth.php';
foreach (['admin' => true, 'sales' => false, 'support' => false, 'operations' => false] as $role => $allowed) {
    $_SESSION = ['user' => ['role' => $role]];
    permission_expect(is_admin() === $allowed, ucfirst($role) . ' delete authorization is incorrect.');
}

echo "Delete permission tests passed." . PHP_EOL;
