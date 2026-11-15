# Mammut Connect CRM — MSI development handover

Status: **Development host migration in progress; Production deployment incomplete** (15 Nov 2026).

## Machines and sources
- **Primary developer workstation:** MSI / `DESKTOP-KLJENKJ` (Windows), repository `D:\MammutConnectCRM`.
- **Source of truth:** `https://github.com/MohammadGhaheri/Simple-CRM`; product branch `mammut-connect` (currently `fb3c57cf354cd36eeabde32986880c4ad654ad92` at handover).
- **Local-only state:** `D:\CRM-Local\private`, `backups`, `staging`, `artifacts`, `logs` — never inside Git.
- **Previous machine:** Majid / Mohammad-Laptop. Its Windows DPAPI-encrypted files and FTPS credentials are intentionally retained only there until the unresolved Production incident is reconciled. DPAPI credentials and data are *not portable* by copying files to MSI.
- PyRunner is **not** part of this workflow.
- Six modified tracked files from old laptop's **older** `main` commit `aaf6a512` are preserved for review only in `D:\CRM-Local\staging\legacy-laptop-uncommitted-20261115.patch`. **Do not apply this patch directly** to the current product branch; changes require selective porting and regression tests.
- MSI FTPS setup helpers are prepared but disabled until Issue #11 is resolved: `scripts\msi\Configure-FTPS-Local.ps1`, `Run-FTPS-ReadOnly.ps1`, `FTPS-ReadOnly.py`. Do not import old DPAPI files.

## Verified changes versus deployment
PRs #8, #9, and #10 have been merged in GitHub. The Production DB's six incremental migrations ran on Nov 9: 23 → 35 tables, and an isolated rehearsal preserved existing row counts. But the code package was not fully deployed. The second FTPS publication was interrupted. Historical recovery checkpoint was `manual_rollback_required` (31 applied `/database` paths and one uncertain in-flight), without an independent successful post-retry rollback hash audit. Last attempts at the old account saw FTPS `531` intermittent login errors. **Do not infer actual current remote hashes from the checkpoint.**

## Everyday development on MSI
1. In PowerShell, navigate to `D:\MammutConnectCRM`.
2. Run `powershell -ExecutionPolicy Bypass -File scripts\msi\Test-Environment.ps1`.
3. For new work, run `powershell -ExecutionPolicy Bypass -File scripts\msi\New-Feature.ps1 -Name descriptive-slug` (requires clean worktree and fast-forward base).
4. Edit and test on the feature branch. Run `powershell -ExecutionPolicy Bypass -File scripts\msi\Run-Checks.ps1`.
5. Open a PR into `mammut-connect`; wait for `.github/workflows/php-smoke.yml` to pass, including SQL migration, RBAC, HTTP, and browser checks.
6. Merge only after review; never run `crm/database/schema.sql` against an already deployed DB.
7. Build a local **non-deployed** ZIP from the approved commit with `scripts\msi\Build-Release.ps1`.

## Running locally
Production compatibility is PHP 8.1 + MySQL/MariaDB. GitHub CI runs PHP 8.3 and MariaDB 10.11. A local newer PHP version is fine for fast lint/tests but does not prove 8.1 compatibility. Node + Playwright enable browser checks; full DB tests require isolated local MariaDB and test-only credentials. Never run integration tests against Production or a shared database.

## Mandatory incident resolution before any future deployment
1. Obtain stable access to the *existing hosting account* and verify authorization. Do not guess at FTPS credentials.
2. Check the server's actual hashes against the previous Windows DPAPI checkpoint for **all 132 managed paths**, including the 31 applied `/database` entries and in-flight path.
3. Finish rollback (or reconcile deliberate retained code) and re-check **all** remote paths. Check signed-in role journeys and attachments. Do not re-run the already-applied six SQL migrations.
4. Retain verified fresh Production SQL + file backups and independent recovery proof. Confirm hosting rules for private attachments and runtime config.
5. Only then select a new reviewed commit/CI artifact and release process. No overwrite of `/app/config/database.php`, runtime `/storage`, legacy uploads, or customer data. Python FTPS + TLS + `PROT P`, per-file checksums, short retryable batches, atomic staging, and recorded rollback.
6. Publish must be marked **deployed** only after version/hash verification, authenticated operational smoke tests, and rollback readiness. Track gate in Issue #11.

## Secret handling
The old machine's `*.dpapi` and `*.clixml` backups cannot simply be copied. If MSI takes over deployments, recreate authorized credentials using the Windows secure prompt *on MSI*, and keep them in `D:\CRM-Local\private` with user-scoped protection. Do not commit, paste or sync plaintext credentials. Transfer only verified **non-secret** release metadata through GitHub and never place full customer database backups in the repo.

## Administrative note
Git push access on MSI is deliberately not assumed. Setting up GitHub authentication may require completing a device/browser authorization locally. Until then, use MSI for code editing and tests and GitHub's connected app for reviewing remote changes.
