<?php

declare(strict_types=1);

// Launched by contract_items_mysql_test.php with an isolated temporary DB.
// This worker never creates, drops, or migrates databases.
if (PHP_SAPI !== 'cli' || count($argv) !== 5) {
    fwrite(STDERR, "Expected: worker.php <contract_id> <invoice_id> <item_id> <quantity>\n");
    exit(3);
}
require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/ContractInvoiceRef.php';
$_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
try {
    ContractInvoiceRef::allocate((int) $argv[1], (int) $argv[2], (int) $argv[3], (int) $argv[4]);
    exit(0);
} catch (DomainException) {
    // Expected for the losing writer after the first allocation is committed.
    exit(2);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(3);
}
