<?php

declare(strict_types=1);

function db(): PDO
{
    return $GLOBALS['authorization_test_pdo'];
}

function current_user_id(): int
{
    return (int) ($_SESSION['user']['id'] ?? 0);
}

function db_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function authorization_behavior_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/Deal.php';
require __DIR__ . '/../app/models/Contract.php';

$pdo = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$GLOBALS['authorization_test_pdo'] = $pdo;
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, customer_name TEXT, deleted_at TEXT)');
$pdo->exec('CREATE TABLE deals (
    id INTEGER PRIMARY KEY AUTOINCREMENT, deal_name TEXT, customer_id INTEGER, product TEXT,
    vehicle_count INTEGER, estimated_amount REAL, probability INTEGER, weighted_amount REAL,
    deal_stage TEXT, expected_close_date TEXT, owner_user_id INTEGER, win_loss_reason TEXT,
    notes TEXT, deleted_at TEXT, updated_at TEXT
)');
$pdo->exec('CREATE TABLE contracts (
    id INTEGER PRIMARY KEY, contract_type TEXT, contract_number TEXT, contract_title TEXT,
    customer_id INTEGER, deal_id INTEGER, product TEXT, vehicle_count INTEGER,
    contract_amount REAL, start_date TEXT, end_date TEXT, renewal_reminder_date TEXT,
    owner_user_id INTEGER, status TEXT, notes TEXT, deleted_at TEXT
)');
$pdo->exec("INSERT INTO users VALUES (1, 'Sales One'), (2, 'Sales Two')");
$pdo->exec("INSERT INTO customers VALUES (1, 'Customer', NULL)");
$pdo->exec("INSERT INTO deals VALUES
    (1, 'Own deal', 1, 'Other', 1, 10, 20, 2, 'Lead', NULL, 1, '', '', NULL, '2026-01-01'),
    (2, 'Foreign deal', 1, 'Other', 1, 10, 20, 2, 'Lead', NULL, 2, '', '', NULL, '2026-01-01')");
$pdo->exec("INSERT INTO contracts VALUES
    (1, 'formal', 'A', 'Own contract', 1, 1, 'Other', 1, 10, NULL, NULL, NULL, 1, 'Active', '', NULL),
    (2, 'formal', 'B', 'Foreign contract', 1, 2, 'Other', 1, 10, NULL, NULL, NULL, 2, 'Active', '', NULL)");

$_SESSION['user'] = ['id' => 1, 'role' => 'sales'];
authorization_behavior_expect(count(Deal::search()) === 1, 'Sales user can list another owner deal.');
authorization_behavior_expect(Deal::find(2) === null, 'Sales user can open another owner deal by URL.');
authorization_behavior_expect(count(Contract::search()) === 1, 'Sales user can list another owner contract.');
authorization_behavior_expect(Contract::find(2) === null, 'Sales user can open another owner contract by URL.');

$createdId = Deal::create([
    'deal_name' => 'Tampered owner', 'customer_id' => 1, 'product' => 'Other',
    'vehicle_count' => 1, 'estimated_amount' => 10, 'probability' => 20,
    'deal_stage' => 'Lead', 'owner_user_id' => 2,
]);
authorization_behavior_expect((int) $pdo->query("SELECT owner_user_id FROM deals WHERE id = {$createdId}")->fetchColumn() === 1, 'Sales user changed owner_user_id.');

$_SESSION['user'] = ['id' => 9, 'role' => 'finance'];
authorization_behavior_expect(Deal::search() === [], 'Finance can see sales pipeline records.');
authorization_behavior_expect(count(Contract::search()) === 2, 'Finance cannot see organization contracts.');

$_SESSION['user'] = ['id' => 9, 'role' => 'operations'];
authorization_behavior_expect(Deal::search() === [], 'Operations can see sales pipeline records.');
authorization_behavior_expect(Contract::search() === [], 'Operations can see confidential contracts.');

$_SESSION['user'] = ['id' => 9, 'role' => 'admin'];
authorization_behavior_expect(count(Deal::search()) === 3, 'Admin cannot see all deals.');
authorization_behavior_expect(count(Contract::search()) === 2, 'Admin cannot see all contracts.');

echo "Sales record authorization behavior tests passed.\n";
