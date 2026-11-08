<?php

declare(strict_types=1);

/**
 * Rehearse six ordered migrations on disposable MariaDB created from the
 * pre-PR#8 Mammut Connect schema in actual Git history.
 * This test never accesses the deployment database or its credentials.
 */
$root = dirname(__DIR__, 2);
$process = proc_open(['git', 'show', '8861fcf:crm/database/schema.sql'], [
    0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Could not launch Git to inspect original schema.');
}
fclose($pipes[0]);
$oldSchema = stream_get_contents($pipes[1]);
$gitError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0 || !is_string($oldSchema)
    || !str_contains($oldSchema, 'CREATE TABLE contracts (')
    || str_contains($oldSchema, 'CREATE TABLE contract_items (')) {
    throw new RuntimeException('Original schema is unavailable or unexpected: ' . substr((string) $gitError, 0, 300));
}

function upgrade_check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('Mammut migration rehearsal: ' . $message);
    }
}

function upgrade_sql(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/^\h*--[^\r\n]*(?:\r?\n|\z)/m', '', $sql) ?? '';
    $sql = preg_replace('/^\h*CREATE DATABASE IF NOT EXISTS simple_crm[^;]*;\h*(?:\r?\n|\z)/mi', '', $sql) ?? '';
    $sql = preg_replace('/^\h*USE\h+simple_crm\h*;\h*(?:\r?\n|\z)/mi', '', $sql) ?? '';
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_TEST_PORT') ?: 3306);
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = (string) (getenv('MYSQL_TEST_PASSWORD') ?: '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$dbName = 'crm_mammut_upgrade_' . bin2hex(random_bytes(5));
$server->exec("CREATE DATABASE {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    upgrade_sql($pdo, $oldSchema);
    $pdo->exec("INSERT INTO users (id,name,email,password_hash,role)
        VALUES (1,'Legacy Owner','legacy-mammut@example.test','TEST','sales')");
    $pdo->exec("INSERT INTO customers (id,customer_code,customer_name,owner_user_id)
        VALUES (1,'LEGACY-2026','Legacy account',1)");
    $pdo->exec("INSERT INTO deals (id,deal_name,customer_id,deal_stage,owner_user_id)
        VALUES (1,'Historical deal',1,'Negotiation',1)");
    $pdo->exec("INSERT INTO contracts
        (id,contract_type,contract_number,contract_title,customer_id,vehicle_count,contract_amount,owner_user_id,status)
        VALUES (1,'formal','OLD-2026','Historical sale',1,12,9876543.21,1,'Active')");
    $pdo->exec("INSERT INTO contract_payments (id,contract_id,amount,payment_date,payment_method)
        VALUES (1,1,345678.90,'2026-04-02','Transfer')");

    $migrations = [
        'add_deal_stage_events.sql',
        'add_contract_items.sql',
        'add_contract_item_amendments.sql',
        'add_contract_invoice_reversals.sql',
        'add_contract_change_audits.sql',
        'add_mammut_installation_fulfillment.sql',
    ];
    foreach ($migrations as $migration) {
        $contents = (string) file_get_contents($root . '/crm/database/' . $migration);
        upgrade_check($contents !== '', 'Required migration is missing: ' . $migration);
        upgrade_sql($pdo, $contents);
    }

    $deal = $pdo->query('SELECT deal_name, deal_stage, owner_user_id FROM deals WHERE id=1')->fetch();
    upgrade_check($deal && $deal['deal_name'] === 'Historical deal'
        && $deal['deal_stage'] === 'Negotiation' && (int) $deal['owner_user_id'] === 1,
        'Existing sales opportunity was modified.');
    upgrade_check((int) $pdo->query('SELECT COUNT(*) FROM deal_stage_events')->fetchColumn() === 0,
        'Historical stage events were fabricated.');
    $contract = $pdo->query('SELECT vehicle_count, contract_amount, owner_user_id FROM contracts WHERE id=1')->fetch();
    upgrade_check($contract && (int) $contract['vehicle_count'] === 12
        && (string) $contract['contract_amount'] === '9876543.21'
        && (int) $contract['owner_user_id'] === 1,
        'Legacy contracted quantity, amount, or owner changed.');
    upgrade_check((string) $pdo->query('SELECT amount FROM contract_payments WHERE id=1')->fetchColumn() === '345678.90',
        'Legacy payment amount changed.');
    foreach (['contract_items', 'contract_item_amendments', 'contract_item_change_events',
        'contract_invoice_refs', 'contract_invoice_lines', 'contract_invoice_ref_events',
        'installation_records', 'installation_status_events', 'delivery_receipts',
        'delivery_receipt_items', 'fulfillment_events'] as $table) {
        upgrade_check((int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0,
            'Upgrade created guessed historical data in ' . $table);
    }
    $checkColumn = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='contract_invoice_lines'
        AND column_name='reversed_at'")->fetchColumn();
    upgrade_check((int) $checkColumn === 1, 'Invoice reversal schema upgrade failed.');

    $pdo->exec("INSERT INTO contract_items
        (contract_id,line_number,item_type,product,item_title,quantity,unit_amount,line_amount,requires_installation)
        VALUES (1,1,'device','IoTruck','Post-upgrade IoTruck',2,100.00,200.00,1)");
    $itemId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO installation_records
        (contract_id,contract_item_id,customer_id,device_model,serial_number,status,installation_kind)
        VALUES (1,?,1,'IoTruck 4G','UPGRADE-TEST-001','scheduled','sale')");
    $stmt->execute([$itemId]);
    upgrade_check((int) $pdo->query('SELECT COUNT(*) FROM installation_records')->fetchColumn() === 1,
        'Post-upgrade device installation cannot be stored.');
    upgrade_check((string) $pdo->query('SELECT amount FROM contract_payments WHERE id=1')->fetchColumn() === '345678.90',
        'New installation unexpectedly changed the historical payment.');

    echo "Legacy-to-core-and-Mammut migration rehearsal passed (6 steps; legacy finance preserved).\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS {$dbName}");
}
