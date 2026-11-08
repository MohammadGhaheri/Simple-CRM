# Release checklist: ticket attachment isolation (PR #6)

This is an **operator guide**, not a completed server configuration. The code moves **new** ticket images to `crm/storage/ticket-attachments`, outside the web root, and serves them only via authenticated/authorized PHP routes. Legacy images may still physically reside at `crm/public/uploads/tickets/`.

## 1. Block direct HTTP access BEFORE enabling the new code

Apache: the PR includes a `.htaccess` in `crm/public/uploads/tickets`. Confirm `AllowOverride` is enabled and the deny rule is honored.

Nginx: `.htaccess` does nothing. If the site's document root is `crm/public`, place this inside the application's `server` block:

```nginx
location ^~ /uploads/tickets/ {
    return 404;
}
```

If the app is instead exposed under `/crm/public/`, also adapt the location to the actual public URL (for example):

```nginx
location ^~ /crm/public/uploads/tickets/ {
    return 404;
}
```

Ensure neither alias locations nor another virtual host can serve the same directory without the deny rule. Check the full Nginx config for conflicting `location` rules, run `nginx -t`, and only then reload the server under an approved change window.

**Verify:** An anonymous request to the real URL of one legacy attachment returns 403/404, while an authorized internal-user or customer-portal attachment route returns the image; a user with no ticket permission gets 403/404. Repeat for both served URL layouts, where applicable.

## 2. Copy legacy files to private storage (after backup)

The legacy DB path is typically `uploads/tickets/YYYY/MM/<random-name>.jpg`. The new authorized path resolver checks private storage first and then a legacy compatibility fallback.

A DB-preserving migration can **copy** each old file without changing its stored relative path:

```text
Source:      crm/public/uploads/tickets/YYYY/MM/<file>
Destination: crm/storage/ticket-attachments/uploads/tickets/YYYY/MM/<file>
DB column:   ticket_messages.attachment_path (unchanged)
```

Example for a controlled Unix/Linux deployment from the project root (adjust ownership and paths; preview first):

```bash
mkdir -p crm/storage/ticket-attachments/uploads/tickets
rsync -av --dry-run crm/public/uploads/tickets/ crm/storage/ticket-attachments/uploads/tickets/
rsync -av crm/public/uploads/tickets/ crm/storage/ticket-attachments/uploads/tickets/
```

Private storage must **not** be accessible by direct HTTP. Ensure the PHP runtime user has read permission. Back up both the database and attachment files, verify copied file counts/checksums, and spot-check access via authorized routes.

Only after successful verification and an agreed retention/rollback plan should operators consider removing the old public physical copies. Denying direct HTTP access is mandatory even if old copies are temporarily retained.

## 3. Restore and rollback considerations

- Deploy with DB, uploaded-file and configuration backups already verified.
- Do not roll back to the old publicly linked UI without reviewing the consequences: post-upgrade images are stored privately and the old UI may not know their paths.
- If Nginx cannot deny access reliably, do **not** release the feature to that host.
- Maintain `Content-Type` safeguards and review `inline` attachment serving headers before production acceptance.
- Test account-specific access with Admin, Support, other staff roles and unrelated customer portal contacts.

## 4. Private ticket evidence in full backups

The original CRM private ticket-storage helper referenced a nonexistent
`private_storage_root()` function, and the full ZIP backup omitted
`crm/storage/ticket-attachments`. Both defects are fixed in this PR.

New full ZIP backups use **manifest format v2**, which includes the private
ticket directory and checksum metadata. The restore validator continues to
accept pre-change **v1** backups containing the four original storage roots.
Version 2 archives missing the ticket root are rejected.

`php crm/tests/private_ticket_backup_test.php` verifies a real synthetic ZIP
containing private ticket evidence, its checksum, v1 backward compatibility
and rejection of a truncated v2 manifest. It requires the PHP `zip` extension.

**Critical:** old v1 ZIP archives do **not** contain the new private ticket
attachments. Do not treat an old v1 archive as a complete backup of a site
that already has private tickets. Before production rollout, separately back
up those files, verify a fresh v2 full backup, and test an actual restore on
a disconnected staging copy. Do not restore to production as a test.

## 5. Release status

These are **pre-deployment requirements**, not actions performed by GitHub CI. This PR is not merged or deployed just because unit and integration tests pass.
