<?php

declare(strict_types=1);

require __DIR__ . '/../app/models/ContractItem.php';

function contract_item_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$source = file_get_contents($root . '/app/models/ContractItem.php');
$schema = file_get_contents($root . '/database/schema.sql');
$migration = file_get_contents($root . '/database/add_contract_items.sql');

foreach (['contract_items', 'contract_invoice_refs', 'contract_invoice_lines'] as $table) {
    contract_item_expect(str_contains($schema, "CREATE TABLE $table"), "$table missing from fresh-install schema.");
    contract_item_expect(str_contains($migration, "CREATE TABLE IF NOT EXISTS $table"), "$table missing from additive migration.");
}
contract_item_expect(!preg_match('/\\b(DROP|TRUNCATE|DELETE\\s+FROM|UPDATE\\s+contracts)\\b/i', $migration), 'Additive migration must not mutate existing contracts.');
contract_item_expect(str_contains($migration, 'UNIQUE KEY uq_contract_items_parent'), 'Items must expose composite contract/key identity.');
contract_item_expect(str_contains($migration, 'UNIQUE KEY uq_contract_invoice_refs_parent'), 'Invoices must expose composite contract/key identity.');
contract_item_expect(str_contains($migration, 'FOREIGN KEY (contract_id, invoice_ref_id)'), 'Invoice line must enforce same-contract invoice.');
contract_item_expect(str_contains($migration, 'FOREIGN KEY (contract_id, contract_item_id)'), 'Invoice line must enforce same-contract item.');
contract_item_expect(str_contains($source, "SELECT id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE"), 'ContractItem writes must lock the parent contract.');
contract_item_expect(str_contains($source, '$pdo->rollBack();'), 'ContractItem must roll back on error.');
contract_item_expect(str_contains($source, 'FILTER_VALIDATE_INT'), 'Quantity must be validated before insert.');

// Invalid input must be rejected without requiring a test database.
foreach ([
    ['contract_id' => 0, 'quantity' => 1, 'item_title' => 'Test'],
    ['contract_id' => 1, 'quantity' => 0, 'item_title' => 'Test'],
    ['contract_id' => 1, 'quantity' => 1, 'item_title' => 'Test', 'item_type' => 'invalid'],
    ['contract_id' => 1, 'quantity' => 1, 'item_title' => ''],
] as $payload) {
    try {
        ContractItem::add($payload);
        contract_item_expect(false, 'Invalid contract item input was accepted.');
    } catch (InvalidArgumentException $expected) {
        // Expected before any DB access.
    }
}

echo "Contract item schema and validation checks passed." . PHP_EOL;
