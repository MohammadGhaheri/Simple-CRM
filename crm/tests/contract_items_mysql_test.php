<?php

declare(strict_types=1);

// Isolated MariaDB integration test. Never runs migrations on a production DB.
// Requires MYSQL_TEST_HOST, MYSQL_TEST_USER and MYSQL_TEST_PASSWORD.
$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = (string) (getenv('MYSQL_TEST_PASSWORD') ?: '');
$port = (int) (getenv('MYSQL_TEST_PORT') ?: 3306);
$configPath = __DIR__ . '/../app/config/database.php';
if (is_file($configPath)) {
    throw new RuntimeException('Refusing to replace existing local database.php in integration test.');
}
$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$dbName = 'crm_contract_items_' . bin2hex(random_bytes(5));
$server->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

function item_sql_expect(bool $value, string $error): void
{
    if (!$value) {
        throw new RuntimeException($error);
    }
}

function item_sql_expect_rejection(callable $action, string $error): void
{
    try {
        $action();
    } catch (InvalidArgumentException | DomainException | RuntimeException | PDOException $exception) {
        return;
    }
    throw new RuntimeException($error);
}

function apply_item_migration(PDO $pdo, string $path): void
{
    $source = (string) file_get_contents($path);
    // Remove full-line comments before splitting statements; comments may contain semicolons.
    $source = preg_replace('/^\\h*--[^\\r\\n]*(?:\\r?\\n|\\z)/m', '', $source);
    foreach (array_filter(array_map('trim', explode(';', $source))) as $statement) {
        // MariaDB accepts SQL comments preceding the CREATE TABLE statement.
        $pdo->exec($statement);
    }
}

try {
    $config = [
        'host' => $host, 'port' => $port, 'dbname' => $dbName,
        'username' => $user, 'password' => $password, 'charset' => 'utf8mb4'
    ];
    file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';');
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/core/auth.php';
    require __DIR__ . '/../app/models/ContractItem.php';
    require __DIR__ . '/../app/models/ContractInvoiceRef.php';
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

    $pdo = db();
    $pdo->exec("CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE contracts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contract_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        owner_user_id INT UNSIGNED NOT NULL,
        deleted_at DATETIME NULL
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Test Admin'), (2, 'Other owner')");
    $pdo->exec("INSERT INTO contracts (id, contract_amount, owner_user_id) VALUES
        (1, '1000.00', 1),(2, '1000.00', 1),(3, '0.00', 1),(4, '1000.00', 2)");

    apply_item_migration($pdo, __DIR__ . '/../database/add_contract_items.sql');
    apply_item_migration($pdo, __DIR__ . '/../database/add_contract_item_amendments.sql');
    apply_item_migration($pdo, __DIR__ . '/../database/add_contract_invoice_reversals.sql');
    apply_item_migration($pdo, __DIR__ . '/../database/add_contract_change_audits.sql');

    $_SESSION['user'] = ['id' => 1, 'role' => 'sales'];
    item_sql_expect_rejection(static function (): void {
        ContractItem::add([
            'contract_id' => 4, 'item_type' => 'device', 'item_title' => 'IDOR attempt',
            'quantity' => 1, 'unit_amount' => '10.00',
        ]);
    }, 'Sales user added an item to another owner contract.');
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::create(4, 'IDOR-INVOICE', '2026-11-02'),
        'Sales user added an invoice to another owner contract.'
    );
    $_SESSION['user'] = ['id' => 4, 'role' => 'finance'];
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::create(1, 'FINANCE-WRITE', '2026-11-02'),
        'Finance user mutated a contract invoice.'
    );
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

    $device = ContractItem::add([
        'contract_id' => 1, 'item_type' => 'device', 'product' => 'IoTruck',
        'sku' => 'TEST-TRUCK', 'item_title' => 'Test vehicle device',
        'quantity' => 2, 'unit_amount' => '100.00', 'requires_installation' => 1,
    ]);
    $service = ContractItem::add([
        'contract_id' => 1, 'item_type' => 'service', 'item_title' => 'Test service',
        'quantity' => 2, 'unit_amount' => '150.00',
    ]);
    item_sql_expect($device > 0 && $service > 0, 'Failed to create multiple lines.');
    item_sql_expect(count(ContractItem::byContract(1)) === 2, 'Multi-item sale not stored.');
    item_sql_expect(count(ContractItem::byContract(3)) === 0, 'Legacy sale was unexpectedly itemized.');
    $_SESSION['user'] = ['id' => 2, 'role' => 'sales'];
    item_sql_expect(ContractItem::byContract(1) === [], 'Sales read another owner contract items.');
    $_SESSION['user'] = ['id' => 4, 'role' => 'finance'];
    item_sql_expect(count(ContractItem::byContract(1)) === 2, 'Finance read-only item visibility failed.');
    $_SESSION['user'] = ['id' => 5, 'role' => 'operations'];
    item_sql_expect(ContractItem::byContract(1) === [], 'Operations received financial item rows.');
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

    ContractItem::amendIdentity(1, $device, [
        'item_title' => 'Renamed test device', 'product' => 'IoTruck', 'sku' => 'TEST-TRUCK-V2',
    ], 'Correct catalogue identity', 1);
    item_sql_expect(ContractItem::byContract(1)[0]['item_title'] === 'Renamed test device', 'Item identity amendment not persisted.');
    item_sql_expect(count(ContractItem::changeEventsForContract(1)) === 1, 'Item identity audit event missing.');

    $before = count(ContractItem::byContract(1));
    item_sql_expect_rejection(static function (): void {
        ContractItem::add([
            'contract_id' => 1, 'item_type' => 'device', 'item_title' => 'Bad',
            'quantity' => 1, 'unit_amount' => '600.00'
        ]);
    }, 'Over-budget line was accepted.');
    item_sql_expect(count(ContractItem::byContract(1)) === $before, 'Rejected line was partially persisted.');

    $invoice1 = ContractInvoiceRef::create(1, 'INV-100', '2026-11-02');
    $invoice2 = ContractInvoiceRef::create(1, 'INV-101', '2026-11-02');
    $invoiceOther = ContractInvoiceRef::create(2, 'INV-200', '2026-11-02');
    ContractInvoiceRef::update(1, $invoice1, 'INV-100-A', '2026-11-03', 'Corrected reference', 'Finance system correction', 1);
    $editedInvoice = array_values(array_filter(
        ContractInvoiceRef::byContract(1),
        static fn(array $invoice): bool => (int) $invoice['id'] === $invoice1
    ));
    item_sql_expect(($editedInvoice[0]['invoice_number'] ?? '') === 'INV-100-A', 'Invoice reference edit not persisted.');
    item_sql_expect(count(ContractInvoiceRef::changeEventsForContract(1)) === 1, 'Invoice reference history missing.');
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::create(1, 'INV-100-A', '2026-11-02'),
        'Duplicate invoice number allowed.'
    );
    ContractInvoiceRef::allocate(1, $invoice1, $device, 2);
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::allocate(1, $invoice2, $device, 1),
        'Invoice allocation exceeded contract line quantity.'
    );
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::allocate(1, $invoiceOther, $device, 1),
        'Cross-contract invoice allocation allowed.'
    );
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::allocate(2, $invoiceOther, $device, 1),
        'Cross-contract item allocation allowed.'
    );
    item_sql_expect(count(ContractInvoiceRef::allocationsByContract(1)) === 1, 'Rejected invoice allocation persisted.');

    ContractItem::amendQuantity(1, $device, 3, 'Approved sales adjustment', 1);
    item_sql_expect((int) ContractItem::byContract(1)[0]['quantity'] === 3, 'Amended line quantity was not persisted.');
    item_sql_expect(count(ContractItem::amendmentsForContract(1)) === 1, 'Amendment audit event not recorded.');
    $secondAllocation = ContractInvoiceRef::allocate(1, $invoice2, $device, 1);
    item_sql_expect_rejection(
        static fn() => ContractItem::amendQuantity(1, $device, 2, 'Reduce below invoice', 1),
        'Quantity reduced below already invoiced amount.'
    );
    item_sql_expect_rejection(
        static fn() => ContractItem::amendQuantity(1, $device, 8, 'Over contract total', 1),
        'Amendment exceeded contract amount.'
    );
    item_sql_expect(count(ContractItem::amendmentsForContract(1)) === 1, 'Rejected amendment created a ghost audit event.');
    item_sql_expect((int) ContractItem::byContract(1)[0]['quantity'] === 3, 'Rejected amendment mutated actual quantity.');

    ContractInvoiceRef::reverseAllocation(1, $secondAllocation, 'Incorrect invoice allocation', 1);
    $allocationHistory = ContractInvoiceRef::allocationsByContract(1);
    item_sql_expect(count($allocationHistory) === 2, 'Reversal deleted the allocation audit record.');
    $reversedRows = array_values(array_filter($allocationHistory, static fn(array $line): bool => !empty($line['reversed_at'])));
    item_sql_expect(count($reversedRows) === 1
        && $reversedRows[0]['reversal_reason'] === 'Incorrect invoice allocation'
        && (int) $reversedRows[0]['reversed_by_user_id'] === 1,
        'Reversal attribution or reason is missing.');
    item_sql_expect_rejection(
        static fn() => ContractInvoiceRef::reverseAllocation(1, $secondAllocation, 'Double reverse', 1),
        'The same allocation was reversed twice.'
    );
    // After reversal, allocation capacity is released; a corrected row may be inserted.
    ContractInvoiceRef::allocate(1, $invoice2, $device, 1);
    item_sql_expect(count(ContractInvoiceRef::allocationsByContract(1)) === 3, 'Corrected allocation not persisted.');

    ContractItem::assertAmountCoversItems(1, '600.00', $pdo);
    item_sql_expect_rejection(
        static fn() => ContractItem::assertAmountCoversItems(1, '599.99', db()),
        'Contract can be reduced below line-item total.'
    );
    item_sql_expect((int) $pdo->query('SELECT COUNT(*) FROM contract_items')->fetchColumn() === 2, 'Unexpected item records.');
    ContractItem::cancel(1, $service, 'Service removed before invoicing', 1);
    item_sql_expect(count(ContractItem::byContract(1)) === 1, 'Cancelled item remains active.');
    item_sql_expect_rejection(
        static fn() => ContractItem::cancel(1, $device, 'Must fail while allocated', 1),
        'Item with active invoice allocations was cancelled.'
    );
    // Race two independent workers allocating 2 units each from one 3-unit item.
    // Only one may succeed, even with simultaneous requests.
    $raceItem = ContractItem::add([
        'contract_id' => 2, 'item_type' => 'device',
        'item_title' => 'Parallel allocation test device',
        'quantity' => 3, 'unit_amount' => '10.00',
    ]);
    $raceInvoice2 = ContractInvoiceRef::create(2, 'INV-RACE-2', '2026-11-02');
    $workerFile = __DIR__ . '/contract_invoice_allocation_worker.php';
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $processA = proc_open([PHP_BINARY, $workerFile, '2', (string) $invoiceOther, (string) $raceItem, '2'], $descriptors, $pipesA);
    $processB = proc_open([PHP_BINARY, $workerFile, '2', (string) $raceInvoice2, (string) $raceItem, '2'], $descriptors, $pipesB);
    item_sql_expect(is_resource($processA) && is_resource($processB), 'Concurrency workers did not start.');
    foreach ([$pipesA, $pipesB] as $pipes) {
        fclose($pipes[1]);
        fclose($pipes[2]);
    }
    $workerOutcomes = [proc_close($processA), proc_close($processB)];
    sort($workerOutcomes);
    item_sql_expect($workerOutcomes === [0, 2], 'Concurrent allocation must allow only one successful writer.');
    $allocatedQuantity = $pdo->prepare('SELECT COALESCE(SUM(invoiced_quantity),0)
        FROM contract_invoice_lines WHERE contract_id = ? AND contract_item_id = ?');
    $allocatedQuantity->execute([2, $raceItem]);
    item_sql_expect((int) $allocatedQuantity->fetchColumn() === 2, 'Concurrent requests exceeded commitment.');

    echo "Contract items MariaDB integration tests passed.\n";
} finally {
    if (is_file($configPath)) {
        unlink($configPath);
    }
    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
