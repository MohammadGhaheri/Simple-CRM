# Contract items: additive data foundation (draft)

This branch establishes the **database and model foundation** for multiple items under an existing `contracts` record (both formal contracts and direct sales). It does not yet modify the production form or turn on installation workflows.

## Records and relationships

- `contracts`: sales transaction / formal contract, unchanged; existing status, payments, documents and amounts remain authoritative.
- `contract_items`: item category (`device`, `product`, `service`), product, SKU/variant, description, quantity, unit and line amount, requires-installation indicator.
- `contract_invoice_refs`: reference to an external invoice, optional number/date, linked to a single sale.
- `contract_invoice_lines`: quantities from a particular contract item referenced by a particular invoice. Two **composite foreign keys** prevent mistakenly attaching an invoice line to an item from a different contract.

One sale may have many items and invoices. One item may appear on multiple invoices. Invoiced quantity must be checked against contract item quantity in the business layer when editing invoices (not yet delivered in this PR).

## Non-destructive transition

1. Backup and stage on a copy of the production database.
2. Run `crm/database/add_contract_items.sql` once **before** activating functionality that references the tables.
3. Existing `contracts.vehicle_count`, `contracts.product` and `contracts.contract_amount` stay unchanged. The migration creates **no synthetic items**, SKU, device type, or invoice references.
4. When the item-editing UI is implemented, old sales must be itemized by an authorized human. Do not infer item types from legacy data.
5. Financial balance continues to derive from the contract amount and actual receipts. Line amounts do not automatically overwrite the contract amount.
6. Once installations or deliveries are recorded, amendments and quantity corrections need an audit trail and approval flow; this first PR intentionally does not offer editing/deleting committed items.

## What's explicitly not in this PR

- Multi-line contract UI and save validation.
- Invoice reference UI, invoice line allocation and cross-invoice maximum quantity validation.
- Installation jobs, serials, customer handover, financial milestones.
- Migration to make `vehicle_count` a derived number.
- Backfilling or rewriting any existing financial data.

## Verification

- `php crm/tests/contract_items_test.php` checks static FK safety and invalid input.
- `php -l crm/app/models/ContractItem.php` checks PHP syntax.
- MySQL integration test with multiple items/invoices, concurrent writes, FK checks, legacy records and rollback is required before merge/deployment.

See issue #3 and `mammut-connect/docs/ROADMAP-MAMMUT-CONNECT.md`.
