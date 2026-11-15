# CRM Development Agent Guidelines — MSI

Primary development host: Ragna machine **MSI** (`DESKTOP-KLJENKJ`).
Primary repository: `D:\MammutConnectCRM`; canonical upstream: `MohammadGhaheri/Simple-CRM`, branch `mammut-connect`.

## Allowed development workflow
1. Read the current branch/status and requirements before editing. Start a dedicated feature/fix branch from the latest `origin/mammut-connect`; never overwrite a dirty worktree.
2. Treat PHP 8.1 and MariaDB as Production compatibility targets; CI uses PHP 8.3 + MariaDB 10.11. Run local lint/focused tests and GitHub Actions CI. For DB changes, rehearse on an isolated **synthetic or anonymized** clone, not the live DB.
3. Use role-based authorization/IDOR/CSRF checks for Sales, Finance, Operations and Support. Test ticket and contract attachment access, invoice and installment invariants, and installation approvals.
4. Commit only reviewed code, tests, docs and migrations. Store real customer data, environment variables, dumps, runtime uploads, credentials and release checkpoints **outside** the repository under `D:\CRM-Local`.
5. Use pull requests and green CI; no force push, no direct Production changes as part of ordinary development.

## Critical Production incident
The 2026-11-09 database migration completed (23 to 35 tables). File publication `fb3c57c` did **not** complete and rollback was not proven complete. Historical checkpoint had 31 applied files and one in-flight file under `/database`; on 2026-11-15 this has NOT been reconciled by a remote 132-path hash audit. See GitHub Issue #11.

**Hard gate:** Do NOT re-run any Production migration, auto-resume interrupted deployment, upload to Production, or clean old backups without a fresh verified read-only inventory, corrected rollback and explicit release approval. Public HTTP 200 is NOT proof of a complete deployment.

## Release design
Prepare release ZIPs offline from an approved commit. Any authorized transport must be Python + explicit FTPS with certificate checking, `PROT P`, short resumable batches and per-file SHA-256 verification. Store rollback checkpoints locally encrypted and restore/reconcile before announcing success. Never expose FTPS secrets in chat, source, CLI arguments or Git.
