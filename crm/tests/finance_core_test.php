<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/User.php';

final class Setting
{
    public static function get(string $key): string
    {
        return $key === 'options_payment_methods' ? "Bank Transfer|واریز بانکی\nCheque|چک\nCash|نقدی\nPOS|کارت‌خوان\nOther|سایر" : '';
    }
}

require __DIR__ . '/../app/models/ContractPayment.php';
require __DIR__ . '/../app/models/Contract.php';

function finance_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (($argv[1] ?? '') === '--worker') {
    $pdo = new PDO('mysql:host=' . $argv[2] . ';port=' . $argv[3] . ';dbname=' . $argv[4] . ';charset=utf8mb4', $argv[5], base64_decode($argv[6]), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    try {
        ContractPayment::create(['contract_id' => (int) $argv[7], 'amount' => '8.00', 'payment_date' => '2026-10-03', 'payment_method' => 'Cash'], 1, $pdo);
        exit(0);
    } catch (DomainException) {
        exit(2);
    }
}

finance_expect(User::validRole('finance') === 'finance', 'Finance role must be valid.');
finance_expect(User::roleLabel('finance') === 'کارشناس مالی', 'Finance label is missing.');
foreach (['admin' => [true, true], 'finance' => [true, true], 'sales' => [true, false], 'support' => [false, false], 'operations' => [false, false]] as $role => [$view, $manage]) {
    $_SESSION = ['user' => ['role' => $role]];
    finance_expect(can_view_finance() === $view && can_manage_finance() === $manage, "Permission matrix failed for $role.");
    finance_expect(is_admin() === ($role === 'admin'), "Admin semantics failed for $role.");
}

foreach (['0', '-1', 'abc', '1e3', '1.234'] as $invalid) {
    try {
        normalize_money_decimal($invalid, false);
        throw new RuntimeException("Invalid amount accepted: $invalid");
    } catch (InvalidArgumentException) {
    }
}
finance_expect(normalize_money_decimal('9999999999999999.99', false) === '9999999999999999.99', 'Maximum DECIMAL value lost precision.');
finance_expect(money_add('9999999999999990.99', '9.00') === '9999999999999999.99', 'String addition lost precision.');
finance_expect(money_subtract('100000000.00', '30000000.00') === '70000000.00', 'String subtraction failed.');
finance_expect(str_starts_with(format_money('1234567.89'), '1,234,567.89'), 'Money formatting regressed.');

$migration = file_get_contents(__DIR__ . '/../database/add_finance_core.sql');
foreach (['finance', 'contract_payments', 'DECIMAL(18,2)', 'deleted_at', 'deleted_by_user_id', 'ON DELETE CASCADE', 'ON DELETE SET NULL', 'idx_contract_payments_contract'] as $needle) {
    finance_expect(str_contains($migration, $needle), "Migration is missing $needle.");
}
finance_expect(!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration), 'Migration contains destructive SQL.');

$controller = file_get_contents(__DIR__ . '/../public/index.php');
$sidebar = file_get_contents(__DIR__ . '/../app/views/layouts/sidebar.php');
$contractView = file_get_contents(__DIR__ . '/../app/views/contracts/show.php');
$backupService = file_get_contents(__DIR__ . '/../app/services/BackupService.php');
finance_expect(str_contains($controller, "action === 'payment_create'") && str_contains($controller, 'require_finance_manage()'), 'Payment create lacks server-side permission enforcement.');
finance_expect(str_contains($controller, "action === 'payment_edit'") && str_contains($controller, "action === 'payment_delete'"), 'Payment mutations are incomplete.');
finance_expect(str_contains($sidebar, 'can_view_finance()'), 'Finance sidebar visibility is not permission-aware.');
finance_expect(str_contains($contractView, 'can_view_finance()') && str_contains($contractView, 'can_manage_finance()'), 'Contract finance UI visibility is incorrect.');
finance_expect(str_contains($backupService, "sqlDefinesTable(\$sqlPath, 'contract_payments')"), 'Full restore verification must include finance data when present.');

$config = require __DIR__ . '/../app/config/database.php';
$database = 'simple_crm_finance_test_' . bin2hex(random_bytes(4));
try {
    $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port'] ?? 3306), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'] ?? 3306, $database), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120), role ENUM('admin','sales','support','operations') NOT NULL DEFAULT 'sales') ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE contracts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contract_type VARCHAR(40) NOT NULL DEFAULT 'formal', contract_amount DECIMAL(18,2) NOT NULL, deleted_at DATETIME NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE app_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NULL) ENGINE=InnoDB");
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^SET NAMES utf8mb4;\s*/', '', $migration)))) as $statement) {
        $pdo->exec($statement);
    }
    $enum = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch()['Type'];
    finance_expect(str_contains($enum, "'finance'"), 'Migration did not extend role enum.');
    $columns = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=" . $pdo->quote($database) . " AND TABLE_NAME='contract_payments'")->fetchAll(PDO::FETCH_KEY_PAIR);
    finance_expect(($columns['amount'] ?? '') === 'decimal(18,2)' && isset($columns['deleted_at'], $columns['deleted_by_user_id']), 'Payment schema does not match the expected audit/decimal structure.');
    $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=" . $pdo->quote($database) . " AND TABLE_NAME='contract_payments'")->fetchAll(PDO::FETCH_COLUMN);
    finance_expect(in_array('idx_contract_payments_contract', $indexes, true) && in_array('idx_contract_payments_creator', $indexes, true), 'Payment indexes were not created.');
    $foreignKeys = $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=" . $pdo->quote($database) . " AND TABLE_NAME='contract_payments'")->fetchColumn();
    finance_expect((int) $foreignKeys === 4, 'Payment foreign keys were not created.');
    $pdo->exec("INSERT INTO users (id,name,role) VALUES (1,'Admin','admin')");
    $pdo->exec("INSERT INTO contracts (id,contract_type,contract_amount) VALUES (1,'formal',100000000.00),(2,'direct_sale',10.00),(3,'direct_sale',100.00)");
    $first = ContractPayment::create(['contract_id' => 1, 'amount' => '30000000', 'payment_date' => '1405/07/11', 'payment_method' => 'Bank Transfer'], 1, $pdo);
    finance_expect((int) ContractPayment::find($first, $pdo)['created_by_user_id'] === 1, 'Creator audit failed.');
    finance_expect(ContractPayment::summaryForContract(1, $pdo)['balance'] === '70000000.00', 'Initial balance failed.');
    try {
        ContractPayment::create(['contract_id' => 1, 'amount' => '70000001', 'payment_date' => '1405/07/11', 'payment_method' => 'Cash'], 1, $pdo);
        throw new RuntimeException('Overpayment create was accepted.');
    } catch (DomainException) {
    }
    $second = ContractPayment::create(['contract_id' => 1, 'amount' => '20000000', 'payment_date' => '1405/07/11', 'payment_method' => 'Cash'], 1, $pdo);
    finance_expect(ContractPayment::summaryForContract(1, $pdo)['received'] === '50000000.00', 'Received total failed.');
    ContractPayment::update($second, ['amount' => '25000000', 'payment_date' => '1405/07/11', 'payment_method' => 'Cheque'], 1, $pdo);
    finance_expect((int) ContractPayment::find($second, $pdo)['updated_by_user_id'] === 1, 'Updater audit failed.');
    try {
        ContractPayment::update($second, ['amount' => '71000000', 'payment_date' => '1405/07/11', 'payment_method' => 'Cash'], 1, $pdo);
        throw new RuntimeException('Overpayment update was accepted.');
    } catch (DomainException) {
    }
    ContractPayment::softDelete($second, 1, $pdo);
    finance_expect(count(ContractPayment::byContract(1, $pdo)) === 1, 'Soft-deleted payment remained active.');
    finance_expect((int) $pdo->query("SELECT deleted_by_user_id FROM contract_payments WHERE id=$second")->fetchColumn() === 1, 'Delete actor audit failed.');
    finance_expect(ContractPayment::summaryForContract(1, $pdo)['balance'] === '70000000.00', 'Soft delete did not restore balance.');
    ContractPayment::create(['contract_id' => 3, 'amount' => '80.00', 'payment_date' => '1405/07/11', 'payment_method' => 'Cash'], 1, $pdo);
    finance_expect(ContractPayment::summaryForContract(3, $pdo)['balance'] === '20.00', 'Direct-sale balance calculation failed.');
    Contract::assertAmountCoversPayments(3, '90.00', $pdo);
    Contract::assertAmountCoversPayments(3, '80.00', $pdo);
    try {
        Contract::assertAmountCoversPayments(3, '79.00', $pdo);
        throw new RuntimeException('Contract amount was reduced below received total.');
    } catch (DomainException) {
    }

    $command = [PHP_BINARY, __FILE__, '--worker', $config['host'], (string) ($config['port'] ?? 3306), $database, $config['username'], base64_encode($config['password']), '2'];
    $options = ['bypass_shell' => true];
    $p1 = proc_open($command, [], $pipes1, null, null, $options);
    $p2 = proc_open($command, [], $pipes2, null, null, $options);
    $exitCodes = [proc_close($p1), proc_close($p2)];
    sort($exitCodes);
    finance_expect($exitCodes === [0, 2], 'Concurrent payments did not serialize correctly.');
    finance_expect(ContractPayment::receivedForContract(2, $pdo) === '8.00', 'Concurrency allowed overpayment.');
    echo "Finance core MySQL integration and concurrency tests passed.\n";
} catch (PDOException $e) {
    if (isset($pdo)) {
        throw $e;
    }
    fwrite(STDERR, "MYSQL_INTEGRATION_SKIPPED: {$e->getMessage()}\n");
} finally {
    if (isset($server)) {
        $server->exec("DROP DATABASE IF EXISTS `$database`");
    }
}

echo "Finance core tests passed.\n";
