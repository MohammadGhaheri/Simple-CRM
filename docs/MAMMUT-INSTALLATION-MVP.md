# Mammut Connect installation and delivery MVP

This module is specific to the `mammut-connect` branch. It must not be merged back into the public CRM branch as a general feature without a separate review.

## Data model

- Installation obligations are derived from active `contract_items` where `item_type = device` and `requires_installation = 1`. Committed and remaining quantities are never copied into another table.
- `installation_records` tracks one physical device/install attempt. IMEI is optional when a serial number exists. Active normalized IMEI and serial values are independently unique.
- `installation_status_events` is the immutable transition audit trail.
- `delivery_receipts` and `delivery_receipt_items` support partial and grouped customer handover. Cancelling a receipt revokes its item links; it does not delete history.
- `fulfillment_events` records completion once per contract item and opens a pending finance follow-up marker. It never records a payment or changes contract financial status.
- `installation_kind` and `replaces_installation_id` distinguish sale fulfillment from future warranty replacement/reinstallation. Only `sale` records consume or complete the sold quantity.

## Workflow and authorization

Allowed status flow:

`scheduled → in_progress → installed → pending_approval → approved`

Failure/cancellation branches are explicit. Failed and cancelled records do not consume fulfilled capacity. Only approved installations may enter an approved delivery receipt.

- Admin and Operations can mutate installation and receipt records.
- Sales can read progress only for contracts they own.
- Finance can read operational delivery status but cannot change installations.
- Support can read only devices covered by an active approved delivery receipt.
- Operations queries never select contract amounts, payments or balances.

Capacity checks and delivery checks run in transactions using row locks. Composite foreign keys prevent linking installation/receipt rows across contracts.

## Deployment order for an existing Mammut Connect installation

Do not run on production without explicit approval, a verified backup and a successful rehearsal on a copy.

1. Apply the five approved core migrations documented in `CONTRACT-ITEMS-DESIGN.md`.
2. Deploy the compatible core code from PR #8.
3. Apply `crm/database/add_mammut_installation_fulfillment.sql`.
4. Deploy the Mammut Connect application code.
5. Run the MariaDB integration suite and verify web-server private-file rules before enabling receipt evidence uploads.

Fresh installations use `crm/database/schema.sql`.

## Verification

`php crm/tests/mammut_installation_mysql_test.php` covers derived obligations, legacy contracts, financial redaction, duplicate IMEI/serial, cross-contract rejection, over-capacity writes, concurrent last-capacity requests, failure status, technical approval, partial/double delivery, receipt cancellation, commitment reduction, role mutations and rollback behavior.

## MVP limitations and operational risks

- Receipt confirmation is an internal Admin/Operations approval, not a digital customer signature.
- Technician is an existing CRM user reference; there is no separate technician authentication system.
- Replacement/reinstallation linkage is modeled, but a full warranty approval workflow and inventory movement are deferred.
- Finance follow-up is an auditable pending event, not an automatic receivable or payment.
- Evidence files reuse the private contract-document storage controls; Nginx/private-storage verification remains a release gate.
- Existing legacy contracts without item rows are intentionally not inferred or placed in the installation queue.
