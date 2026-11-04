<?php

declare(strict_types=1);

// Contract + item transaction integration test: isolated temporary MariaDB schema.
// Does not touch production or local persistent databases.
$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = (string) (getenv('MYSQL_TEST_PASSWORD') ?: '');
$port = (int) (getenv('MYSQL_TEST_PORT') ?: 3306);
$configPath = __DIR__ . '/../app/config/database.php';
if (is_file($configPath)) {
    throw new RuntimeException('Refusing to overwrite existing local database configuration.');
}

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$dbName = 'crm_atomic_sale_' . bin2hex(random_bytes(5));
$server->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

function atomic_expect(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException($description);
    }
}

function atomic_apply(PDO $pdo, string $filename): void
{
    $sql = (string) file_get_contents($filename);
    $sql = preg_replace('/^\\s*--[^\\r\\n]*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

// Contract model compatibility mocks: no external messaging or reminders.
class Activity
{
    public static function createOrUpdateContractRenewal(array $contract): void {}
}
class Setting
{
    public static function get(string $key): string { return ''; }
}

try {
    $config = [
        'host' => $host, 'port' => $port, 'dbname' => $dbName, 'username' => $user,
        'password' => $password, 'charset' => 'utf8mb4',
    ];
    file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';');
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/models/Contract.php';
    require __DIR__ . '/../app/models/ContractItem.php';
    require __DIR__ . '/../app/models/ContractSaleService.php';

    $_SESSION['user'] = ['id' => 1, 'role' => 'sales'];
    $pdo = db();
    $pdo->exec('CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(120)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE customers (id INT UNSIGNED PRIMARY KEY, customer_name VARCHAR(190), deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE deals (id INT UNSIGNED PRIMARY KEY, deal_name VARCHAR(190)) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE contracts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contract_type VARCHAR(40) NOT NULL DEFAULT 'formal',
        contract_number VARCHAR(80) NULL,
        contract_title VARCHAR(190) NOT NULL,
        customer_id INT UNSIGNED NOT NULL,
        deal_id INT UNSIGNED NULL,
        product VARCHAR(120) NOT NULL DEFAULT 'Other',
        vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
        contract_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        start_date DATE NULL,
        end_date DATE NULL,
        renewal_reminder_date DATE NULL,
        owner_user_id INT UNSIGNED NULL,
        status VARCHAR(80) NOT NULL DEFAULT 'Active',
        notes TEXT NULL,
        deleted_at DATETIME NULL
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO users (id,name) VALUES (1,'Sales')");
    $pdo->exec("INSERT INTO customers (id,customer_name) VALUES (1,'Test customer')");
    atomic_apply($pdo, __DIR__ . '/../database/add_contract_items.sql');

    $base = [
        'contract_type' => 'direct_sale', 'contract_title' => 'Atomic sale',
        'customer_id' => 1, 'owner_user_id' => 1,
        'contract_amount' => '500.00', 'status' => 'Active',
    ];
    $sale = ContractSaleService::create($base, [
        ['item_type' => 'device', 'item_title' => 'Tracking device', 'quantity' => '2', 'unit_amount' => '100.00', 'requires_installation' => '1'],
        ['item_type' => 'service', 'item_title' => 'Software service', 'quantity' => '1', 'unit_amount' => '100.00'],
    ]);
    atomic_expect($sale > 0, 'Atomic sale ID missing.');
    atomic_expect(count(ContractItem::byContract($sale)) === 2, 'Initial item lines not committed with contract.');
    atomic_expect((string) Contract::find($sale)['contract_amount'] === '500.00', 'Contract amount changed unexpectedly.');
    atomic_expect(!$pdo->inTransaction(), 'Transaction not closed after success.');

    $legacy = ContractSaleService::create($base + [], []);
    atomic_expect(count(ContractItem::byContract($legacy)) === 0, 'Legacy no-item sale must not be auto-itemized.');

    $before = (int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn();
    try {
        ContractSaleService::create($base, [
            ['item_type' => 'device', 'item_title' => 'Partially entered', 'quantity' => '', 'unit_amount' => '100.00']
        ]);
        throw new RuntimeException('Incomplete row was accepted.');
    } catch (InvalidArgumentException) {}
    atomic_expect((int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn() === $before, 'Invalid form created an orphan contract.');

    // Force a DB error after the parent and the FIRST line are inserted.
    $pdo->exec("CREATE TRIGGER fail_atomic_sale_item BEFORE INSERT ON contract_items
        FOR EACH ROW
        BEGIN
            IF NEW.item_title = 'TRIGGER_REJECT' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced integration test failure';
            END IF;
        END");
    try {
        ContractSaleService::create($base, [
            ['item_type' => 'device', 'item_title' => 'Valid first row', 'quantity' => '1', 'unit_amount' => '100.00'],
            ['item_type' => 'device', 'item_title' => 'TRIGGER_REJECT', 'quantity' => '1', 'unit_amount' => '100.00'],
        ]);
        throw new RuntimeException('Trigger failure did not abort sale.');
    } catch (PDOException) {}
    atomic_expect((int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn() === $before, 'DB failure persisted parent contract.');
    atomic_expect((int) $pdo->query('SELECT COUNT(*) FROM contract_items')->fetchColumn() === 2, 'DB failure persisted partial line items.');
    atomic_expect(!$pdo->inTransaction(), 'Open transaction leaked after failure.');

    echo "Atomic contract with multiple items MariaDB tests passed.\n";
} finally {
    if (is_file($configPath)) {
        unlink($configPath);
    }
    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
