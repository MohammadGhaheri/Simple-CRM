<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$helpers = file_get_contents($root . '/app/core/helpers.php');
$controller = file_get_contents($root . '/public/index.php');
$portal = file_get_contents($root . '/public/portal.php');
$internalView = file_get_contents($root . '/app/views/tickets/edit.php');
$legacyProtection = file_get_contents($root . '/public/uploads/tickets/.htaccess');

function ticket_attachment_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ticket_attachment_expect(str_contains($helpers, "ticket_attachment_root() . '/' . \$relativeDir"), 'New ticket uploads are not private.');
ticket_attachment_expect(!str_contains($helpers, "public_upload_root() . '/uploads/tickets/'"), 'Ticket uploads still target the public directory.');
ticket_attachment_expect(str_contains($controller, "\$action === 'attachment'"), 'Authenticated ticket attachment route is missing.');
ticket_attachment_expect(str_contains($controller, "Ticket::find((int) \$message['ticket_id'])"), 'Internal download does not authorize the parent ticket.');
ticket_attachment_expect(str_contains($portal, "\$action === 'ticket_attachment'"), 'Portal attachment route is missing.');
ticket_attachment_expect(str_contains($portal, 'Ticket::findForContact'), 'Portal download does not authorize the contact.');
ticket_attachment_expect(!str_contains($internalView, "href=\"<?= e(\$message['attachment_path'])"), 'Internal view still exposes raw storage paths.');
ticket_attachment_expect(!str_contains($portal, "href=\"<?= e(\$message['attachment_path'])"), 'Portal view still exposes raw storage paths.');
ticket_attachment_expect(str_contains($legacyProtection, 'Require all denied'), 'Legacy public ticket attachments are not blocked on Apache.');

echo "Ticket attachment security checks passed.\n";
