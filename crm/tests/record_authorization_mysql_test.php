<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--activity-worker') {
    $pdo = new PDO($argv[2], $argv[3], base64_decode($argv[4], true) ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $GLOBALS['authorization_mysql_pdo'] = $pdo;
    $_SESSION['user'] = ['id' => 1, 'role' => 'sales'];
    require __DIR__ . '/../app/core/auth.php';
    require __DIR__ . '/../app/models/Activity.php';
    Activity::update(1, [
        'customer_id' => 1, 'activity_date' => '2026-11-02', 'activity_type' => 'Follow-up',
        'summary' => 'stale update', 'owner_user_id' => 1, 'status' => 'Open',
    ]);
    exit(0);
}

function db(): PDO
{
    return $GLOBALS['authorization_mysql_pdo'];
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

function authorization_mysql_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$host = getenv('CRM_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('CRM_TEST_DB_PORT') ?: 3306);
$username = getenv('CRM_TEST_DB_USER') ?: 'root';
$password = (string) (getenv('CRM_TEST_DB_PASSWORD') ?: '');
$database = 'simple_crm_auth_' . bin2hex(random_bytes(4));
$server = null;

try {
    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $GLOBALS['authorization_mysql_pdo'] = $pdo;

    $pdo->exec('CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(120), role VARCHAR(30), is_active TINYINT DEFAULT 1) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE customers (id INT UNSIGNED PRIMARY KEY, customer_name VARCHAR(190), customer_code VARCHAR(50), city VARCHAR(80), owner_user_id INT UNSIGNED, estimated_contract_value DECIMAL(18,2), is_vip TINYINT DEFAULT 0, last_followup_date DATE NULL, next_followup_date DATE NULL, deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE deals (id INT UNSIGNED PRIMARY KEY, deal_name VARCHAR(190), customer_id INT UNSIGNED, owner_user_id INT UNSIGNED, deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE contracts (id INT UNSIGNED PRIMARY KEY, contract_title VARCHAR(190), customer_id INT UNSIGNED, deal_id INT UNSIGNED NULL, owner_user_id INT UNSIGNED, deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE contacts (id INT UNSIGNED PRIMARY KEY, customer_id INT UNSIGNED, contact_name VARCHAR(190), default_support_user_id INT UNSIGNED NULL, deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE activities (id INT UNSIGNED PRIMARY KEY, customer_id INT UNSIGNED, deal_id INT UNSIGNED NULL, contract_id INT UNSIGNED NULL, activity_date DATE, activity_type VARCHAR(80), summary VARCHAR(190), next_action TEXT NULL, next_followup_date DATE NULL, owner_user_id INT UNSIGNED, created_by_user_id INT UNSIGNED NULL, status VARCHAR(30), is_internal_task TINYINT DEFAULT 0, attachment_path VARCHAR(255) NULL, attachment_name VARCHAR(255) NULL, attachment_mime VARCHAR(100) NULL, attachment_size INT NULL, notes TEXT NULL, deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE tickets (id INT UNSIGNED PRIMARY KEY, customer_id INT UNSIGNED, contact_id INT UNSIGNED, assigned_user_id INT UNSIGNED NULL, status VARCHAR(30), deleted_at DATETIME NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE ticket_messages (id INT UNSIGNED PRIMARY KEY, ticket_id INT UNSIGNED, attachment_path VARCHAR(255) NULL, attachment_name VARCHAR(255) NULL, attachment_mime VARCHAR(100) NULL, attachment_size INT NULL) ENGINE=InnoDB');

    $pdo->exec("INSERT INTO users VALUES (1,'Sales One','sales',1),(2,'Sales Two','sales',1),(3,'Support One','support',1),(4,'Finance','finance',1),(5,'Operations','operations',1),(9,'Admin','admin',1)");
    $pdo->exec("INSERT INTO customers VALUES (1,'Own','C1','Tehran',1,1000,0,NULL,NULL,NULL),(2,'Foreign','C2','Qom',2,2000,0,NULL,NULL,NULL)");
    $pdo->exec("INSERT INTO deals VALUES (1,'Own Deal',1,1,NULL),(2,'Foreign Deal',2,2,NULL)");
    $pdo->exec("INSERT INTO contracts VALUES (1,'Own Contract',1,1,1,NULL),(2,'Foreign Contract',2,2,2,NULL)");
    $pdo->exec("INSERT INTO contacts VALUES (1,1,'Own Contact',3,NULL),(2,2,'Foreign Contact',3,NULL)");
    $pdo->exec("INSERT INTO activities VALUES (1,1,1,1,'2026-11-02','Follow-up','original','',NULL,1,1,'Open',0,NULL,NULL,NULL,NULL,'',NULL),(2,2,2,2,'2026-11-02','Follow-up','operations','',NULL,5,5,'Open',0,NULL,NULL,NULL,NULL,'',NULL)");
    $pdo->exec("INSERT INTO tickets VALUES (1,1,1,3,'Open',NULL),(2,2,2,2,'Open',NULL),(3,2,2,NULL,'Open',NULL)");
    $pdo->exec("INSERT INTO ticket_messages VALUES (1,1,'2026/11/a.png','a.png','image/png',10),(2,2,'2026/11/b.png','b.png','image/png',10)");

    require __DIR__ . '/../app/core/auth.php';
    require __DIR__ . '/../app/models/Customer.php';
    require __DIR__ . '/../app/models/Contact.php';
    require __DIR__ . '/../app/models/Contract.php';
    require __DIR__ . '/../app/models/Activity.php';
    require __DIR__ . '/../app/models/Ticket.php';
    require __DIR__ . '/../app/models/TicketMessage.php';

    $_SESSION['user'] = ['id' => 1, 'role' => 'sales'];
    authorization_mysql_expect(Customer::find(1) !== null && Customer::find(2) === null, 'Sales customer ownership scope failed.');
    authorization_mysql_expect(Contact::find(1) !== null && Contact::find(2) === null, 'Sales contact ownership scope failed.');
    authorization_mysql_expect(Activity::find(1) !== null && Activity::find(2) === null, 'Sales activity ownership scope failed.');

    $_SESSION['user'] = ['id' => 3, 'role' => 'support'];
    $supportCustomer = Customer::find(1);
    authorization_mysql_expect($supportCustomer !== null && $supportCustomer['estimated_contract_value'] === null, 'Support financial redaction failed.');
    authorization_mysql_expect(Ticket::find(1) !== null && Ticket::find(2) === null && Ticket::find(3) !== null, 'Support ticket assignment scope failed.');

    $_SESSION['user'] = ['id' => 4, 'role' => 'finance'];
    authorization_mysql_expect(Customer::find(1) !== null && Contact::find(1) === null && Ticket::find(1) === null, 'Finance scope failed.');
    try {
        Contract::delete(1);
        throw new RuntimeException('Finance mutated a contract through a direct model call.');
    } catch (RuntimeException $expected) {
        authorization_mysql_expect($expected->getMessage() !== 'Finance mutated a contract through a direct model call.', $expected->getMessage());
    }

    $_SESSION['user'] = ['id' => 1, 'role' => 'sales'];
    try {
        Contract::delete(2);
        throw new RuntimeException('Sales deleted another owner contract by changing its ID.');
    } catch (RuntimeException $expected) {
        authorization_mysql_expect($expected->getMessage() !== 'Sales deleted another owner contract by changing its ID.', $expected->getMessage());
    }

    $_SESSION['user'] = ['id' => 5, 'role' => 'operations'];
    $operationsCustomer = Customer::find(2);
    authorization_mysql_expect($operationsCustomer !== null && $operationsCustomer['estimated_contract_value'] === null, 'Operations customer scope/redaction failed.');
    authorization_mysql_expect(Activity::find(2) !== null && Activity::find(1) === null, 'Operations activity ownership scope failed.');

    $_SESSION['user'] = ['id' => 9, 'role' => 'admin'];
    authorization_mysql_expect(TicketMessage::attachment(1)['ticket_id'] === 1, 'Ticket attachment lookup failed.');

    $pdo->beginTransaction();
    $pdo->query('SELECT id FROM activities WHERE id = 1 FOR UPDATE')->fetchColumn();
    $worker = proc_open([PHP_BINARY, __FILE__, '--activity-worker', $dsn, $username, base64_encode($password)], [], $pipes, null, null, ['bypass_shell' => true]);
    usleep(200000);
    $pdo->exec('UPDATE activities SET owner_user_id = 2 WHERE id = 1');
    $pdo->commit();
    authorization_mysql_expect(proc_close($worker) === 0, 'Concurrent activity worker failed.');
    $row = $pdo->query('SELECT owner_user_id, summary FROM activities WHERE id = 1')->fetch();
    authorization_mysql_expect((int) $row['owner_user_id'] === 2 && $row['summary'] === 'original', 'Stale activity update crossed the ownership boundary.');
    authorization_mysql_expect($pdo->query('SELECT last_followup_date FROM customers WHERE id = 1')->fetchColumn() === null, 'Rejected activity update changed customer follow-up state.');

    echo "Record authorization MySQL integration tests passed.\n";
} catch (PDOException $error) {
    if ($server === null) {
        fwrite(STDERR, "MYSQL_INTEGRATION_SKIPPED: {$error->getMessage()}\n");
        exit(77);
    }
    throw $error;
} finally {
    if ($server instanceof PDO) {
        $server->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
}
