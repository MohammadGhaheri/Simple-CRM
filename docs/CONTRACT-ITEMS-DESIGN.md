# Contract items and invoice references — staged delivery

This feature branch adds **atomic sale creation with multiple line items, editable quantity amendments, and external invoice reference allocation/reversal workflows** to existing formal contracts and direct sales.

## Availability and security

- The contract **create** form supports entering multiple item lines at once. The contract and all entered lines commit together; malformed data, exceeding the amount ceiling, or a DB insertion error rolls back the entire sale.
- Contract details support adding more items, referencing invoices, allocating invoice quantities, correcting quantity with a recorded reason, and reversing an incorrect invoice allocation with a mandatory reason.
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
- Invoice allocation reversal is **not a physical deletion**: it records the timestamp, responsible user and mandatory reason on the original allocation row, and excludes that row from the active invoiced quantity. A corrected allocation may then be entered, including for the same invoice/item.
- Historical allocations are never modified automatically by the migration. No past reversal is inferred.

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
3. `crm/database/add_contract_invoice_reversals.sql`
4. Deploy compatible PHP code **after PR #6 authorization rules are integrated**.

Fresh installs use the updated `crm/database/schema.sql`. These migrations are additive and do not rewrite existing sales or payments. The reversal migration changes the invoice-line index and must be run **exactly once** after the first schema migration. Do not apply it on an already-updated fresh schema.

## Verification and release gates

- `php crm/tests/contract_items_test.php`: precise money multiplication, static FK and input checks.
- `php crm/tests/contract_items_mysql_test.php`: live MariaDB transaction/rollback tests for line-item creation, ceiling enforcement, invoice isolation, allocation correction/reversal, amendments, and two-process allocation concurrency.
- `php crm/tests/contract_sale_atomic_mysql_test.php`: verifies creation of a sale with multiple lines in a single transaction and full rollback when a DB trigger rejects the second line.
- These tests use temporary isolated databases and never target production.
- PHP lint on all PHP files.
- Before merge/deploy: regression testing with the complete realistic CRM schema, end-to-end authorization after PR #6 integration, backup/restore, and financial settlement regression tests. The isolated two-writer invoice allocation concurrency test is already part of the MariaDB CI suite.

## Follow-on work

- Editing or cancellation of existing item title/model with a complete audit (current UI permits audited quantity corrections only).
- Invoice-reference header editing, correcting mistaken dates, and an approved cancellation flow.
- Explicit reconciliation of line totals to contract totals (discounts, taxes, non-itemized portions).
- Invoice digital document attachment workflow, if needed.
- Mammut-specific installation fulfillment keyed by `contract_items.id`, device serials and customer delivery receipts.

Tracking: Issue #3 and PR #7; Mammut roadmap in `mammut-connect/docs/ROADMAP-MAMMUT-CONNECT.md`.

## Integration order with the security PR

PR #6 and PR #7 both modify these three files:
- `.github/workflows/php-smoke.yml`
- `crm/app/models/Contract.php`
- `crm/public/index.php`

Do not simply overwrite the later branch with an older version of these files. Integrate both feature sets and retest all HTTP routes: new sale creation; invoice line add, amend, allocate and reverse; sale-owner restrictions; Finance read-only access; non-sales access denial; contract document downloads; existing payment operations. Add the isolated MariaDB tests from both PRs to the final CI job before deployment.

The integration is **not** complete merely because each PR passes CI independently.
