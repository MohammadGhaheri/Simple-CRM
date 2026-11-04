<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--worker') {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $argv[2], (int) $argv[3], $argv[4]),
        $argv[5],
        base64_decode($argv[6], true) ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $GLOBALS['deal_test_pdo'] = $pdo;
    $GLOBALS['deal_test_user_id'] = (int) $argv[7];
    require __DIR__ . '/../app/models/Deal.php';
    $row = $pdo->query('SELECT * FROM deals WHERE id=1')->fetch();
    $row['deal_stage'] = $argv[8];
    $row['expected_stage'] = $argv[9];
    try {
        Deal::update(1, $row);
        exit(0);
    } catch (DomainException) {
        exit(2);
    }
}

function deal_mysql_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function db(): PDO
{
    return $GLOBALS['deal_test_pdo'];
}

function current_user_id(): int
{
    return $GLOBALS['deal_test_user_id'];
}

function db_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

$host = getenv('CRM_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('CRM_TEST_DB_PORT') ?: 3306);
$username = getenv('CRM_TEST_DB_USER') ?: 'root';
$password = (string) (getenv('CRM_TEST_DB_PASSWORD') ?: '');
$database = 'simple_crm_deal_stage_' . bin2hex(random_bytes(4));
$server = null;

try {
    $server = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $GLOBALS['deal_test_pdo'] = $pdo;
    $GLOBALS['deal_test_user_id'] = 1;

    $pdo->exec("CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(120) NOT NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE customers (id INT UNSIGNED PRIMARY KEY, customer_name VARCHAR(190) NOT NULL, deleted_at DATETIME NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE deals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        deal_name VARCHAR(190) NOT NULL, customer_id INT UNSIGNED NOT NULL, product VARCHAR(80) NOT NULL,
        vehicle_count INT NOT NULL DEFAULT 0, estimated_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        probability INT NOT NULL DEFAULT 0, weighted_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        deal_stage VARCHAR(80) NOT NULL, expected_close_date DATE NULL, owner_user_id INT UNSIGNED NULL,
        win_loss_reason TEXT NULL, notes TEXT NULL, deleted_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_test_deal_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
        CONSTRAINT fk_test_deal_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
    $migration = file_get_contents(__DIR__ . '/../database/add_deal_stage_events.sql');
    foreach (array_filter(array_map('trim', explode(';', $migration))) as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec("INSERT INTO users (id,name) VALUES (1,'User One'),(2,'User Two')");
    $pdo->exec("INSERT INTO customers (id,customer_name) VALUES (1,'Test Customer')");

    require __DIR__ . '/../app/models/Deal.php';
    $base = [
        'deal_name' => 'Integration Deal', 'customer_id' => 1, 'product' => 'Other',
        'vehicle_count' => 1, 'estimated_amount' => 1000, 'probability' => 20,
        'deal_stage' => 'Lead', 'expected_close_date' => null, 'owner_user_id' => 1,
        'win_loss_reason' => '', 'notes' => '',
    ];

    $id = Deal::create($base);
    deal_mysql_expect($id === 1, 'Deal creation failed.');
    deal_mysql_expect((int) $pdo->query('SELECT COUNT(*) FROM deal_stage_events')->fetchColumn() === 1, 'Initial event was not recorded.');
    $initial = $pdo->query('SELECT from_stage,to_stage,changed_by_user_id FROM deal_stage_events WHERE deal_id=1')->fetch();
    deal_mysql_expect($initial['from_stage'] === null && $initial['to_stage'] === 'Lead' && (int) $initial['changed_by_user_id'] === 1, 'Initial event data is incorrect.');

    Deal::update($id, $base + ['expected_stage' => 'Lead', 'notes' => 'No stage change']);
    deal_mysql_expect((int) $pdo->query('SELECT COUNT(*) FROM deal_stage_events')->fetchColumn() === 1, 'Non-stage edit created a duplicate event.');

    $GLOBALS['deal_test_user_id'] = 2;
    Deal::update($id, array_merge($base, ['expected_stage' => 'Lead', 'deal_stage' => 'Qualified']));
    $transition = $pdo->query('SELECT from_stage,to_stage,changed_by_user_id FROM deal_stage_events ORDER BY id DESC LIMIT 1')->fetch();
    deal_mysql_expect($transition['from_stage'] === 'Lead' && $transition['to_stage'] === 'Qualified' && (int) $transition['changed_by_user_id'] === 2, 'Transition actor or stages are incorrect.');

    $GLOBALS['deal_test_user_id'] = 999;
    try {
        Deal::update($id, array_merge($base, ['expected_stage' => 'Qualified', 'deal_stage' => 'Proposal']));
        throw new RuntimeException('Event foreign-key failure did not abort the update.');
    } catch (PDOException) {
    }
    deal_mysql_expect($pdo->query('SELECT deal_stage FROM deals WHERE id=1')->fetchColumn() === 'Qualified', 'Failed event did not roll back the deal stage.');

    $pdo->exec("UPDATE deals SET deal_stage='Lead' WHERE id=1");
    $pdo->exec('DELETE FROM deal_stage_events WHERE id > 1');
    $commandBase = [PHP_BINARY, __FILE__, '--worker', $host, (string) $port, $database, $username, base64_encode($password)];
    $p1 = proc_open([...$commandBase, '1', 'Qualified', 'Lead'], [], $pipes1, null, null, ['bypass_shell' => true]);
    $p2 = proc_open([...$commandBase, '2', 'Proposal', 'Lead'], [], $pipes2, null, null, ['bypass_shell' => true]);
    $exitCodes = [proc_close($p1), proc_close($p2)];
    sort($exitCodes);
    deal_mysql_expect($exitCodes === [0, 2], 'Concurrent stale updates were not serialized/rejected.');
    deal_mysql_expect((int) $pdo->query('SELECT COUNT(*) FROM deal_stage_events')->fetchColumn() === 2, 'Concurrency lost or duplicated a transition event.');

    echo "Deal stage history MySQL integration tests passed.\n";
} catch (PDOException $e) {
    if ($server === null) {
        fwrite(STDERR, "MYSQL_INTEGRATION_SKIPPED: {$e->getMessage()}\n");
        exit(77);
    }
    throw $e;
} finally {
    if ($server instanceof PDO) {
        $server->exec("DROP DATABASE IF EXISTS `$database`");
    }
}
