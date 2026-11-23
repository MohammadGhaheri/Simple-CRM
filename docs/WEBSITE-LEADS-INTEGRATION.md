# Mammut Connect — secure website lead intake

Status: development branch. **Do not deploy** until GitHub Issue #11 release incident is reconciled and a verified backup + rollback plan exists.

## Architecture

1. Existing Contact Form 7 #13 and Flamingo remain in place.
2. \`wpcf7_submit\` at priority 20 accepts only \`mail_sent\` or \`mail_failed\` with explicit \`privacy-consent\`.
3. WordPress inserts the original JSON payload and a UUID-based request ID into \`wp_mc_crm_queue\` **before** attempting delivery.
4. Plugin sends signed HTTPS JSON server-to-server. It never exposes the HMAC secret to browser JS.
5. CRM validates the HMAC, 300-second timestamp, nonce, rate limit, schema and consent. Database uniqueness of \`request_id\` prevents duplicates.
6. Raw leads are separate from customers and deals. Admin/sales can assign, review, follow up and convert within an atomic DB transaction.
7. A WordPress cron worker retries unsuccessful requests with exponential backoff; the original request ID and body are preserved. WordPress Cron requires periodic site traffic or an external cron hit to \`wp-cron.php\`.
8. Email, Flamingo and CRM states are independent. The Flamingo state in this plugin is explicitly \`unverified\` until verified in Flamingo; the plugin does **not** claim to confirm Flamingo writes.

## API path and contract

Under document root \`crm/public\`, endpoint path is \`/api/v1/website-leads/\` (directory index); adjust only the WordPress server-side configured endpoint if production document root or rewrite rules differ. Do not claim a live URL until a production HTTPS probe succeeds.

\`POST /api/v1/website-leads/\`

Required headers:
- \`Content-Type: application/json\`
- \`X-MC-Timestamp: <unix-seconds>\`
- \`X-MC-Nonce: <32-64 lowercase hex characters>\` — unique for **every HTTP attempt**
- \`X-MC-Signature: sha256=<hex(HMAC_SHA256(secret, timestamp + "\\n" + nonce + "\\n" + RAW_REQUEST_BODY))>\`

Secrets: provision same random >=32-byte secret on CRM as environment variable \`MAMMUT_WEBSITE_HMAC_SECRET\` and on WordPress via \`MC_CRM_HMAC_SECRET\` in wp-config.php, or the protected plugin admin settings. Never commit the secret or send it in browser output. Rotate by coordinated configuration change.

Input JSON fields: \`request_id, source, submitted_at, name, mobile, email, company, fleet_type, fleet_type_label, service, service_label, vehicle_count, message, consent, page_url, utm_source, utm_medium, utm_campaign\`. The first-level fields are as specified in the project request; the two optional *_label fields preserve original Persian labels. \`source\` must be \`mammutconnect.com\`. Phone is normalized to \`09xxxxxxxxx\`. \`submitted_at\` requires ISO-8601; page_url must use HTTPS on mammutconnect.com. Maximum body 16 KiB.

Allowed fleet codes: \`truck,trailer,van,car,bus,mixed,other\`.
Allowed service codes: \`ifms,iotruck,iotrailer,dsp,tracking,consultation,other\`.

Responses:
- **201** \`{"status":"created","lead_id":123,"request_id":"..."}\`
- **200** \`{"status":"duplicate","lead_id":123,"request_id":"..."}\` on repeat ID + identical original JSON body, with a **new nonce**.
- **401** invalid signature or stale timestamp.
- **409** reused nonce or \`request_id\` with a different payload (conflict).
- **422** invalid payload / consent missing.
- **429** >60 authenticated requests per minute from the website integration.
- **503** missing server secret or storage temporarily unavailable.

## WordPress setup

- Package \`wordpress/mammut-connect-crm-integration/\` as a standard WordPress ZIP.
- Install/activate only **after** the CRM migration + API pass independent preflight.
- Configure HTTPS endpoint and shared key at Tools → اتصال CRM ماموت, or in wp-config.php:
  \`define('MC_CRM_ENDPOINT', 'https://CRM-HOST/api/v1/website-leads/');\`
  \`define('MC_CRM_HMAC_SECRET', 'use-a-secret-generated-outside-git');\`
- Existing CF7 form ID 13 is unchanged. To auto-populate product from links, use \`/contact-us-2/?mc_product=iotruck\`, \`?mc_product=iotrailer\`, \`?mc_product=ifms\`. Add these to product CTA buttons **without changing indexed URLs, slugs, canonical URLs or Elementor layouts**; first back up Elementor page data and audit navigation.
- Existing CF7 recipient \`info@mammutconnect.com\` and Mail(2) recipient \`a.fazel@mammutconnect.com\` remain unchanged.
- In Tools → اتصال CRM ماموت inspect pending/failed requests and mail-error codes. Failed requests remain in the queue and may be retried manually. Never store secrets in logs or admin table.

## Required production checks

1. **Release gate**: reconcile GitHub Issue #11 with read-only remote 132-path hash inventory and verified rollback completion. Current repo AGENTS.md blocks all production uploads/migrations until then.
2. Backup CRM code, database and WordPress form/Elementor metadata to secure storage outside Git. Record original file hashes and restore procedure.
3. Apply \`crm/database/add_website_leads.sql\` on an isolated synthetic MariaDB clone first. Then in production **only** after the gate.
4. Deploy API/models/views and perform signing tests (401 invalid sig, 422 invalid input, 201 new, 200 same ID, 409 changed payload/nonce).
5. Activate WP plugin only after API is known healthy; then perform a labelled synthetic \`TEST\` submission, trace CRM lead ID, WordPress queue, Flamingo record and independent mail status.
6. Force temporary CRM unavailability and verify durable retry. Check mobile and desktop UX and RBAC for admin/sales/other roles.
7. Check CF7 \`mail_failed\` with email transport logs and \`wp_mail_failed\`, DNS SPF/DKIM/DMARC, sender domain and SMTP authorization. The actual cause cannot be claimed from generic CF7 status alone. Do not switch recipients without approval.
8. Record post-release sample evidence and clean up only synthetic TEST records once traceable audit is saved.

## Rollback

- Deactivate the new WordPress integration plugin. Leave CF7 #13, Flamingo, SEO, URLs, and existing mail settings intact.
- Restore the prior known-good CRM code from its **verified** release checkpoint.
- Keep new \`website_leads\` tables in place when returning to the previous app version to avoid loss of already-accepted leads. Only remove newly created tables after explicit data reconciliation and separate backup.
- For failed queue items, retain WP table and JSON payloads until reconciliation, even after deactivation.
