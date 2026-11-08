<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--transition-worker') {
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/core/auth.php';
    require __DIR__ . '/../app/models/InstallationRecord.php';
    $_SESSION['user'] = ['id' => 3, 'role' => 'operations'];
    try {
        InstallationRecord::transition((int) $argv[2], 'installed', 'concurrent capacity test', 3);
        exit(0);
    } catch (DomainException) {
        exit(2);
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(3);
    }
}

function mammut_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function mammut_reject(callable $action, string $message): void
{
    try {
        $action();
    } catch (InvalidArgumentException | DomainException | RuntimeException | PDOException) {
        return;
    }
    throw new RuntimeException($message);
}

function mammut_apply(PDO $pdo, string $path): void
{
    $source = (string) file_get_contents($path);
    $source = preg_replace('/^\h*--[^\r\n]*(?:\r?\n|\z)/m', '', $source);
    foreach (array_filter(array_map('trim', explode(';', $source))) as $statement) {
        $pdo->exec($statement);
    }
}

$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_TEST_PORT') ?: 3306);
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = (string) (getenv('MYSQL_TEST_PASSWORD') ?: '');
$configPath = __DIR__ . '/../app/config/database.php';
if (is_file($configPath)) {
    throw new RuntimeException('Refusing to overwrite an existing database config.');
}
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'mammut_installation_' . bin2hex(random_bytes(5));
$server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    file_put_contents($configPath, '<?php return ' . var_export([
        'host' => $host, 'port' => $port, 'dbname' => $database, 'username' => $user,
        'password' => $password, 'charset' => 'utf8mb4',
    ], true) . ';');
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/core/auth.php';
    require __DIR__ . '/../app/models/ContractItem.php';
    require __DIR__ . '/../app/models/InstallationObligation.php';
    require __DIR__ . '/../app/models/InstallationRecord.php';
    require __DIR__ . '/../app/models/DeliveryReceipt.php';
    $pdo = db();
    $pdo->exec("CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(120), role VARCHAR(30), is_active TINYINT DEFAULT 1) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE customers (id INT UNSIGNED PRIMARY KEY, customer_name VARCHAR(190), deleted_at DATETIME NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE contracts (id INT UNSIGNED PRIMARY KEY, contract_number VARCHAR(80), contract_title VARCHAR(190), customer_id INT UNSIGNED, owner_user_id INT UNSIGNED, contract_amount DECIMAL(18,2), deleted_at DATETIME NULL) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO users VALUES (1,'Admin','admin',1),(2,'Sales','sales',1),(3,'Operations','operations',1),(4,'Finance','finance',1),(5,'Support','support',1),(6,'Other Sales','sales',1)");
    $pdo->exec("INSERT INTO customers VALUES (1,'Customer A',NULL),(2,'Customer B',NULL),(3,'Legacy Customer',NULL)");
    $pdo->exec("INSERT INTO contracts VALUES (1,'C-1','Device sale',1,2,10000,NULL),(2,'C-2','Other sale',2,2,10000,NULL),(3,'OLD','Legacy sale',3,2,5000,NULL)");
    mammut_apply($pdo, __DIR__ . '/../database/add_contract_items.sql');
    mammut_apply($pdo, __DIR__ . '/../database/add_contract_item_amendments.sql');
    mammut_apply($pdo, __DIR__ . '/../database/add_contract_invoice_reversals.sql');
    mammut_apply($pdo, __DIR__ . '/../database/add_contract_change_audits.sql');
    mammut_apply($pdo, __DIR__ . '/../database/add_mammut_installation_fulfillment.sql');

    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    $itemA = ContractItem::add(['contract_id' => 1, 'item_type' => 'device', 'product' => 'IoTruck', 'item_title' => 'IoTruck 4G', 'quantity' => 2, 'unit_amount' => '1000', 'requires_installation' => 1]);
    $itemB = ContractItem::add(['contract_id' => 2, 'item_type' => 'device', 'product' => 'IoTrailer', 'item_title' => 'IoTrailer 4G', 'quantity' => 1, 'unit_amount' => '1000', 'requires_installation' => 1]);
    ContractItem::add(['contract_id' => 1, 'item_type' => 'service', 'product' => 'Other', 'item_title' => 'Software service', 'quantity' => 1, 'unit_amount' => '100', 'requires_installation' => 0]);

    $_SESSION['user'] = ['id' => 3, 'role' => 'operations'];
    $obligations = InstallationObligation::search();
    mammut_expect(count($obligations) === 2, 'Service or legacy contract entered the installation queue.');
    mammut_expect(!array_key_exists('contract_amount', $obligations[0]), 'Operations query leaked contract amount.');

    $r1 = InstallationRecord::create($itemA, ['imei' => ' 123-456 ', 'device_model' => 'IoTruck 4G'], 3);
    mammut_reject(static fn() => InstallationRecord::create($itemA, ['imei' => '123456'], 3), 'Duplicate active IMEI was accepted.');
    $r2 = InstallationRecord::create($itemA, ['serial_number' => 'SER-001'], 3);
    mammut_reject(static fn() => InstallationRecord::create($itemB, ['serial_number' => 'SER001'], 3), 'Duplicate active serial was accepted across contracts.');
    mammut_expect((int) $pdo->query("SELECT contract_id FROM installation_records WHERE id={$r1}")->fetchColumn() === 1, 'Installation crossed its contract item boundary.');
    // A replacement reference must never point to another contract/customer.
    mammut_reject(
        static fn() => InstallationRecord::create($itemB, [
            'serial_number' => 'CROSS-REPLACEMENT',
            'installation_kind' => 'warranty_replacement',
            'replaces_installation_id' => $r1,
        ], 3),
        'Replacement accepted installation from a different contract.'
    );
    mammut_reject(
        static fn() => InstallationRecord::create($itemA, [
            'serial_number' => 'BAD-SALE-REFERENCE',
            'installation_kind' => 'sale',
            'replaces_installation_id' => $r1,
        ], 3),
        'Fresh sale installation incorrectly accepted replacement pointer.'
    );

    foreach ([$r1, $r2] as $recordId) {
        InstallationRecord::transition($recordId, 'in_progress', 'start', 3);
        InstallationRecord::transition($recordId, 'installed', 'technical install complete', 3);
        InstallationRecord::transition($recordId, 'pending_approval', 'ready for review', 3);
        InstallationRecord::transition($recordId, 'approved', 'technical approval', 3);
    }
    $failed = InstallationRecord::create($itemA, ['serial_number' => 'FAILED-1'], 3);
    InstallationRecord::transition($failed, 'in_progress', 'attempt', 3);
    InstallationRecord::transition($failed, 'failed', 'device test failed', 3);
    mammut_expect((int) InstallationObligation::find($itemA)['approved_quantity'] === 2, 'Failed installation changed approved quantity.');

    $over = InstallationRecord::create($itemA, ['serial_number' => 'OVER-1'], 3);
    InstallationRecord::transition($over, 'in_progress', 'attempt over capacity', 3);
    mammut_reject(static fn() => InstallationRecord::transition($over, 'installed', 'must exceed capacity', 3), 'Installation exceeded committed quantity.');

    $receipt1 = DeliveryReceipt::createApproved([$r1], [
        'receipt_number' => 'R-1', 'receiver_name' => 'Customer', 'delivered_at' => '2026-11-08 10:00',
        'evidence_path' => '2026/11/receipt-one.pdf', 'evidence_name' => 'receipt-one.pdf',
    ], 3);
    // Read access must be enforced inside the receipt model, not only in the HTTP controller.
    $_SESSION['user'] = ['id' => 6, 'role' => 'sales'];
    mammut_expect(DeliveryReceipt::byContractItem($itemA) === [], 'Foreign Sales user can read another owner receipt list.');
    mammut_expect(DeliveryReceipt::evidenceForViewer($receipt1) === null, 'Foreign Sales user can resolve receipt evidence.');
    $_SESSION['user'] = ['id' => 2, 'role' => 'sales'];
    mammut_expect(count(DeliveryReceipt::byContractItem($itemA)) === 1, 'Owning Sales user cannot read their receipt.');
    mammut_expect(DeliveryReceipt::evidenceForViewer($receipt1) !== null, 'Owning Sales user cannot read authorized evidence.');
    $_SESSION['user'] = ['id' => 5, 'role' => 'support'];
    mammut_expect(count(DeliveryReceipt::byContractItem($itemA)) === 1, 'Support must see approved delivered device receipts.');
    mammut_expect(DeliveryReceipt::evidenceForViewer($receipt1) !== null, 'Support cannot access authorized active receipt evidence.');
    $_SESSION['user'] = ['id' => 3, 'role' => 'operations'];
    mammut_expect((int) InstallationObligation::find($itemA)['remaining_quantity'] === 1, 'Partial delivery remaining quantity is wrong.');
    mammut_reject(static fn() => DeliveryReceipt::createApproved([$r1], ['receipt_number' => 'R-DUP', 'receiver_name' => 'Customer', 'delivered_at' => '2026-11-08 10:00'], 3), 'Installation was delivered twice.');
    mammut_reject(static fn() => DeliveryReceipt::createApproved([$r1, InstallationRecord::create($itemB, ['serial_number' => 'CROSS-1'], 3)], ['receipt_number' => 'R-CROSS', 'receiver_name' => 'Customer', 'delivered_at' => '2026-11-08 10:00'], 3), 'Cross-contract receipt was accepted.');
    $receipt2 = DeliveryReceipt::createApproved([$r2], [
        'receipt_number' => 'R-2', 'receiver_name' => 'Customer', 'delivered_at' => '2026-11-08 11:00',
        'evidence_path' => '2026/11/receipt-two.pdf', 'evidence_name' => 'receipt-two.pdf',
    ], 3);
    mammut_expect((int) InstallationObligation::find($itemA)['remaining_quantity'] === 0, 'Completed obligation did not reach zero.');
    mammut_expect((int) $pdo->query("SELECT COUNT(*) FROM fulfillment_events WHERE contract_item_id={$itemA} AND event_type='obligation_completed'")->fetchColumn() === 1, 'Completion event/finance follow-up was not created once.');
    DeliveryReceipt::cancel($receipt2, 'Customer rejected second handover', 3);
    $_SESSION['user'] = ['id' => 5, 'role' => 'support'];
    mammut_expect(count(DeliveryReceipt::byContractItem($itemA)) === 1,
        'Support can see cancelled receipts that are no longer an active handover.');
    mammut_expect(DeliveryReceipt::evidenceForViewer($receipt2) === null,
        'Support can download evidence for a cancelled receipt.');
    mammut_expect(DeliveryReceipt::evidenceForViewer($receipt1) !== null,
        'Support lost access to still-valid handover evidence.');
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    mammut_expect(DeliveryReceipt::evidenceForViewer($receipt2) !== null,
        'Admin lost the audit reference to a cancelled receipt.');
    $_SESSION['user'] = ['id' => 3, 'role' => 'operations'];
    mammut_expect((int) InstallationObligation::find($itemA)['remaining_quantity'] === 1, 'Receipt cancellation did not reopen remaining quantity.');
    mammut_expect((int) $pdo->query("SELECT COUNT(*) FROM fulfillment_events
        WHERE contract_item_id={$itemA} AND event_type='obligation_reopened'")->fetchColumn() === 1,
        'Reopened obligation audit event missing.');
    mammut_expect((int) $pdo->query("SELECT COUNT(*) FROM fulfillment_events
        WHERE contract_item_id={$itemA} AND event_type='obligation_completed'
          AND finance_followup_status='pending'")->fetchColumn() === 0,
        'Cancelled handover left an invalid pending finance task.');
    $receipt3 = DeliveryReceipt::createApproved(
        [$r2],
        ['receipt_number' => 'R-3', 'receiver_name' => 'Customer', 'delivered_at' => '2026-11-08 12:00'],
        3
    );
    mammut_expect((int) InstallationObligation::find($itemA)['remaining_quantity'] === 0,
        'Re-accepted customer handover did not re-complete obligation.');
    mammut_expect((int) $pdo->query("SELECT COUNT(*) FROM fulfillment_events
        WHERE contract_item_id={$itemA} AND event_type='obligation_completed'")->fetchColumn() === 2,
        'Re-completion should record a new financial follow-up cycle.');
    mammut_expect((int) $pdo->query("SELECT COUNT(*) FROM fulfillment_events
        WHERE contract_item_id={$itemA} AND event_type='obligation_completed'
          AND finance_followup_status='pending'")->fetchColumn() === 1,
        'Re-completion must create exactly one active finance follow-up.');
    // The HTTP controller must ignore attacker-supplied receipt file paths.
    $routerSource = (string) file_get_contents(__DIR__ . '/../public/index.php');
    mammut_expect(str_contains($routerSource, "unset(\$_POST['evidence_path'], \$_POST['evidence_name']);"),
        'Client-provided private receipt paths are not discarded by the router.');
    mammut_expect(str_contains($routerSource, 'DeliveryReceipt::evidenceForViewer('),
        'Receipt evidence route does not use model-level authorization.');
    mammut_expect(str_contains($routerSource, 'if (!$canMutateInstallations)'),
        'Mutation route fails to reject unauthorized users before processing uploads.');

    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    mammut_reject(static fn() => ContractItem::amendQuantity(1, $itemA, 1, 'invalid reduction', 1), 'Commitment was reduced below active installations.');
    mammut_reject(static fn() => ContractItem::cancel(1, $itemA, 'invalid cancellation with installs', 1),
        'Contract item with installation records was soft-deleted.');
    mammut_expect(InstallationObligation::find($itemA) !== null,
        'Obligation disappeared after a rejected cancellation.');


    $_SESSION['user'] = ['id' => 2, 'role' => 'sales'];
    mammut_reject(static fn() => InstallationRecord::create($itemA, ['serial_number' => 'ROLE-SALES'], 2), 'Sales mutated installation records.');
    $_SESSION['user'] = ['id' => 4, 'role' => 'finance'];
    mammut_reject(static fn() => InstallationRecord::transition($r1, 'failed', 'finance attempt', 4), 'Finance mutated installation records.');
    $_SESSION['user'] = ['id' => 5, 'role' => 'support'];
    mammut_reject(static fn() => DeliveryReceipt::cancel($receipt1, 'support attempt', 5), 'Support cancelled delivery receipt.');

    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    $raceItem = ContractItem::add(['contract_id' => 2, 'item_type' => 'device', 'product' => 'IoTrailer', 'item_title' => 'Race device', 'quantity' => 1, 'unit_amount' => '10', 'requires_installation' => 1]);
    $_SESSION['user'] = ['id' => 3, 'role' => 'operations'];
    $raceA = InstallationRecord::create($raceItem, ['serial_number' => 'RACE-A', 'status' => 'in_progress'], 3);
    $raceB = InstallationRecord::create($raceItem, ['serial_number' => 'RACE-B', 'status' => 'in_progress'], 3);
    $worker = __FILE__;
    $p1 = proc_open([PHP_BINARY, $worker, '--transition-worker', (string) $raceA], [], $pipes1, null, null, ['bypass_shell' => true]);
    $p2 = proc_open([PHP_BINARY, $worker, '--transition-worker', (string) $raceB], [], $pipes2, null, null, ['bypass_shell' => true]);
    $outcomes = [proc_close($p1), proc_close($p2)];
    sort($outcomes);
    mammut_expect($outcomes === [0, 2], 'Concurrent last-capacity requests did not serialize.');
    mammut_expect((int) $pdo->query("SELECT COUNT(*) FROM installation_records WHERE contract_item_id={$raceItem} AND status='installed'")->fetchColumn() === 1, 'Concurrent installs exceeded commitment.');

    echo "Mammut installation and delivery MariaDB tests passed.\n";
} finally {
    if (is_file($configPath)) {
        unlink($configPath);
    }
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
}
