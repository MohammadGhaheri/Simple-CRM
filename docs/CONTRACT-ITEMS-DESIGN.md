# Contract items and invoice references — staged delivery

This feature branch adds **editable quantity amendments and external invoice reference workflows** to existing formal contracts and direct sales.

## Availability and security

- After creating a contract, open its detail page to add multiple item lines, add invoice references, allocate quantities and amend quantities. This avoids mutating the original create/edit contract flow.
- Items may be device, product or service. Only device items can require installation.
- Sales users can mutate lines and invoices **only for their own contracts**. Admin can manage all; Finance can view but not edit. Support/Operations cannot see item prices through this UI. The additional contract visibility and access hardening in PR #6 must be integrated before production release.
- This branch does **not** provide an operations installation view. That is Mammut-only work, after the core PR is approved.
- All mutations are POST-only with CSRF checks; parent ownership is checked before accepting child IDs.

## Pricing and quantity rules

- Line amount = exact decimal unit price × quantity, computed on the server without floats.
- Sum of active line amounts cannot exceed the authoritative `contracts.contract_amount` total. It may be lower for legacy/unitemized portions and policy-based adjustments; no tax/discount fields exist yet.
- The parent contract amount cannot be reduced below the itemized total or total payment receipts.
- Quantities are positive integers and use bounded ranges; no zero-quantity or over-limit lines.
- Quantity changes require a reason and write an immutable `contract_item_amendments` history event with actor/time and previous/new values.
- A quantity amendment cannot reduce the quantity below existing invoice allocations.
- Invoice headers are references to external invoices, not accounting documents. Multiple invoices may reference the same contract and item.
- An invoice allocation cannot cross contract boundaries, or cause cumulative invoiced quantity for an item to exceed committed item quantity.
- Allocations and amendments serialize through a `SELECT ... FOR UPDATE` lock on the parent contract.
- Invoice allocations are append-only in this stage. Corrections to invoice allocation require a future, explicitly audited cancellation/reversal workflow; avoid manual DB edits.

## Backward compatibility

Legacy fields remain untouched:
- `contracts.product`
- `contracts.vehicle_count`
- `contracts.contract_amount`

Existing sales remain valid without any contract_items rows. No product types, invoices, prices or quantities are inferred from old data. Itemization happens manually under an authorized sales/admin account.

## Database migration order for EXISTING installations

**Do not run on production until approved.** Take a verified backup and test on a copy.

1. `crm/database/add_contract_items.sql`
2. `crm/database/add_contract_item_amendments.sql`
3. Deploy compatible PHP code.

Fresh installs use the updated `crm/database/schema.sql`. These migrations are additive and do not rewrite existing sales or payments.

## Verification and release gates

- `php crm/tests/contract_items_test.php`: precise money multiplication, static FK and input checks.
- `php crm/tests/contract_items_mysql_test.php`: live MariaDB transaction/rollback tests for multi-item sale, over-budget rejection, cross-contract invoice isolation, over-allocation, amended quantities, amendment history and legacy records. This test uses an isolated ephemeral database, not production.
- PHP lint on all PHP files.
- Before merge/deploy: regression testing with complete realistic CRM schema, end-to-end authorization after PR #6 integration, concurrency tests for two simultaneous invoice allocations, backup/restore, and financial settlement regression tests.

## Follow-on work

- Single-form atomic creation of a contract **with** all line items. Current UI uses create-contract-then-add-items.
- UI for adjusting item title/model, item cancel and invoice allocation reversals, with proper immutable audit.
- Explicit reconciliation of line totals to contract totals (discounts, taxes, non-itemized portions).
- Invoice digital document attachment workflow, if needed.
- Mammut-specific installation fulfillment keyed by `contract_items.id`, device serials and customer delivery receipts.

Tracking: Issue #3 and PR #7; Mammut roadmap in `mammut-connect/docs/ROADMAP-MAMMUT-CONNECT.md`.
