# Release readiness and controlled rollout — Simple CRM + Mammut Connect

**Status:** procedure only. This file does not authorize a merge, change a server,
access a live customer database or perform a deployment.

## 1. Branch and PR dependencies

| Change set | PR / head at review | Base | Required outcome |
| --- | --- | --- | --- |
| Generic security + deal history + contracts | #8 / current GitHub head | `main` | separately reviewed and explicitly approved |
| Mammut installation/acceptance MVP | #9 / `codex/mammut-installations` | `mammut-connect` | only after generic code is established |

The original integrated-core commit `57f2c94` is an ancestor of PR #9,
but the later generic ticket-backup/attachment fixes were committed
**independently to both PR #8 and PR #9**. Those updated branch heads therefore
do not have the same ancestry, even though their shared fix files are
content-identical. Before merging either PR, reconcile Git ancestry using the
latest actual heads and verify there are no lost/duplicate semantic changes.
Do not merge PRs #5–#7 independently: PR #8 already integrates them.

**Intended source control sequence**, only after an explicit user approval for
the named merge action:

1. Review and approve PR #8. Prefer preserving PR #8's ancestry when merging
   to `main`; do not implicitly deploy that merge.
2. Sync `mammut-connect` with the resulting `main` using a dedicated reviewed
   integration change, preserving `docs/ROADMAP-MAMMUT-CONNECT.md`. Do not
   overwrite the Mammut base branch from an old checkout.
3. Recheck PR #9 against the updated Mammut base. Confirm its diff now contains
   **only Mammut-specific changes** plus any intentionally documented shared
   security guard, not a second divergent copy of PR #8.
4. Rerun complete GitHub CI; approve PR #9 for merge separately.
5. Neither a merged PR nor green CI constitutes deployment approval. The
   deployment window and migration authorization are independent decisions.

Before changing a branch, verify the current heads again; the above SHAs are
historical references, not guaranteed current heads.

## 2. Mandatory release gates (Go / No-Go)

- [ ] PR #8 receives final human review; CI green against current head; merge
      explicitly approved.
- [ ] PR #9 receives final human review; CI green against updated
      `mammut-connect` base; merge explicitly approved.
- [ ] Capture a **verified and restorable backup** of the existing database,
      secure file storage (including ticket/contract/receipt files), application
      configuration and the exact running code revision.
- [ ] Establish a separate non-public staging environment using a **controlled,
      access-restricted copy of the actual database**, with customer-identifiable
      content protected or appropriately anonymized. Never upload this copy to CI.
- [ ] Rehearse the full six-step migration on that copy; record duration,
      schema preflight results, errors, record-count/reconciliation checks and
      the tested rollback/restore procedure.
- [ ] Confirm the exact document root and web-server type; on Nginx,
      `crm/public/uploads/tickets/` must be denied for all publicly served
      URL layouts (see `docs/SECURE-TICKET-ATTACHMENTS.md`). An Apache
      `.htaccess` is **not** effective on Nginx.
- [ ] Verify `crm/storage/` is outside every public document root; restrict
      file permissions. Authorized private-file routes must succeed; direct
      public paths and users without appropriate parent-record access must fail.
- [ ] Run role-based staging acceptance for Admin, Sales owner, unrelated
      Sales, Operations, Finance, Support, and customer portal where applicable.
      Verify amount redaction, no unauthorized IDOR, CSRF, receipt cancellation,
      failed installation and correction flow.
- [ ] Get Operations and Finance sign-off on quantity formulas, installation
      approval, receipt handover and re-opened finance follow-up semantics.
      MVP approval is **internal**, not a digital customer signature.
- [ ] Check mobile RTL layout, keyboard navigation, file-upload/download
      browser behavior, backups, cron jobs, email/SMS sending and monitoring.
- [ ] Confirm a maintenance window, database upgrade/rollback owner, deployed
      version identifiers, planned smoke tests and an explicit final Go decision.

**No-Go if:** any of the above are unverified, backup restoration fails, the
schema differs from rehearsed production copy, migration step 4 appears already
applied or half-applied, or private attachments remain reachable directly.

## 2A. Full backup coverage — private ticket evidence

The release review uncovered two related preexisting defects in the generic
CRM backup and attachment layers:

- `ticket_attachment_root()` referred to an undefined
  `private_storage_root()` helper, preventing new private ticket uploads.
  It now resolves to `crm/storage/ticket-attachments`, outside the public root.
- Full ZIP backups omitted that private ticket directory. Backups created with
  the corrected code use archive format **v2** and include
  `files/private/ticket-attachments` plus SHA-256 file metadata. The restore
  validator still accepts historical **v1** archives with the original four
  roots; it rejects incomplete v2 archives lacking the ticket root.

`php crm/tests/private_ticket_backup_test.php` exercises real ZIP creation,
validation, checksum preservation, historical v1 compatibility and v2
incompleteness rejection using **synthetic files only**. This checks archive
integrity, not a live system recovery.

**Release gate:** take a fresh full v2 backup after deployment, verify the ZIP
manifest includes private ticket evidence, and perform a **controlled restore
rehearsal** on staging. Historical v1 backups cannot contain newly uploaded
private ticket files; an old ZIP alone may therefore be insufficient to
recover the current site. Preserve current private storage in the backup
inventory alongside existing public attachments until a new v2 archive and
its restore are verified.

## 3. Read-only schema preflight (copy of the deployed database)

A read-only utility now exists at
`crm/tools/release_schema_preflight.php`. Use a dedicated read-only MariaDB
account and a configuration file **outside the repository**. Example:

```bash
php crm/tools/release_schema_preflight.php --config=/secure/path/staging-db-readonly.php
```

The tool only queries `information_schema` and `SELECT DATABASE()`. It
prints whether the schema is untouched, partially upgraded, needs manual
inspection or contains all six migration artifacts, along with the next
migration. It **never** runs migrations, prints connection secrets, reads
customer data rows or declares a database production-ready.

If it reports `manual_review_required`, stop and inspect; do **not** retry
the last SQL file blindly. In particular, `add_contract_invoice_reversals.sql`
drops an existing index, is **not idempotent**, and must run exactly once.

CI separately runs the migration-order test
`crm/tests/mammut_legacy_upgrade_mysql_test.php` against a temporary database
built from the repository's original `8861fcf` schema. **That test is not a
substitute for staging with the actual deployed schema and its real history.**

## 4. Exact SQL sequence (only after snapshot / explicit authorization)

1. `crm/database/add_deal_stage_events.sql`
2. `crm/database/add_contract_items.sql`
3. `crm/database/add_contract_item_amendments.sql`
4. `crm/database/add_contract_invoice_reversals.sql` **once**
5. `crm/database/add_contract_change_audits.sql`
6. `crm/database/add_mammut_installation_fulfillment.sql`

Run the schema preflight **before** and **after** each stage, recording results
without exposing database contents. Migrations use MySQL/MariaDB DDL, which is
generally not rolled back as one ordinary SQL transaction. If a step fails,
**stop**, preserve logs, examine exact schema state and use the approved recovery
procedure rather than assuming `ROLLBACK` reversed all DDL.

Never run `crm/database/schema.sql` as an upgrade migration; it is the
fresh-install schema and contains drop/create statements.

## 5. Technical staging acceptance

Validate an existing legacy contract with zero item rows and an existing Deal
and payment; make sure these values are unchanged. Create a **new synthetic
staging contract** with two installable device lines and one non-installable
service line. Test registration, active IMEI/Serial uniqueness, prevention of
over-capacity, valid status transitions, refusal of invalid technician roles,
partial delivery and final acceptance. Revoke the second receipt and verify
the commitment reopens and the stale pending finance follow-up is retired;
approve a replacement receipt and ensure just one current pending follow-up
exists. Verify authorized download and rejection for unauthorized roles and
an already-cancelled receipt.

Verify the following separately: no autofilled invented legacy item details;
finance amounts never appear in the Operations page or HTTP responses; Finance
may read appropriate delivery status but cannot mutate installation; only the
owning Sales user may read the relevant commitments. CI now includes a real multipart PDF upload through the PHP web front controller,\nincluding MIME rejection and private download authorization. A separate\n**interactive browser upload acceptance** on staging is still required.

## 6. Exit criteria / rollback

Capture evidence for each gate in the PR/release record; include exact commit
IDs, anonymized checks, timestamps and a named approver. Do not log SQL
credentials, real IMEI values or personally identifiable records.

If staging fails, leave production unchanged. If an approved production
rollout later fails, stop application writes and invoke the previously
tested **consistent database+file restore plan** with its owner, paying attention
to writes since the snapshot. Never claim a code-only rollback automatically
reverses new schema or private-file storage changes.

**Known deferred scope:** structured discount/tax ledger, customer digital
signature, full warranty/inventory workflow, production copy rehearsal,
Nginx configuration evidence and full accessibility/file-upload acceptance.
