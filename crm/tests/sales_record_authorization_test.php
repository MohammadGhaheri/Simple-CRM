<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$auth = file_get_contents($root . '/app/core/auth.php');
$deal = file_get_contents($root . '/app/models/Deal.php');
$contract = file_get_contents($root . '/app/models/Contract.php');
$controller = file_get_contents($root . '/public/index.php');

function authorization_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

authorization_expect(str_contains($auth, 'function require_roles(array $roles)'), 'Role gate is missing.');
authorization_expect(str_contains($auth, 'function require_sales_manage()'), 'Sales mutation gate is missing.');
authorization_expect(str_contains($deal, "self::currentRole() === 'sales'"), 'Deals are not ownership scoped.');
authorization_expect(str_contains($deal, '{$alias}.owner_user_id = ?'), 'Deal SQL lacks a prepared owner filter.');
authorization_expect(str_contains($contract, "['admin', 'finance']"), 'Finance contract visibility is missing.');
authorization_expect(str_contains($contract, '{$alias}.owner_user_id = ?'), 'Contract SQL lacks a prepared owner filter.');
authorization_expect(substr_count($controller, "require_roles(['admin', 'sales'])") >= 1, 'Deal routes are not role gated.');
authorization_expect(substr_count($controller, "require_roles(['admin', 'sales', 'finance'])") >= 1, 'Contract routes are not role gated.');
authorization_expect(str_contains($controller, '$authorizedContract = $document ? Contract::find'), 'Contract document downloads do not verify record access.');
authorization_expect(substr_count($controller, 'require_sales_manage();') >= 4, 'Sales mutations are not consistently guarded.');

echo "Sales record authorization checks passed.\n";
