<?php

declare(strict_types=1);

/**
 * Isolated archive-format regression test. Uses fake SQL and fixture files,
 * never an application's real database or attachment directories.
 *
 * v2 must preserve private ticket images. v1 archives created before that
 * directory existed must still validate for controlled legacy restore.
 */
require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/services/BackupService.php';

function backup_ticket_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Ticket backup regression: ' . $message);
    }
}

function backup_ticket_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            backup_ticket_remove_tree($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

if (!extension_loaded('zip')) {
    throw new RuntimeException('PHP zip extension is required to verify full backups.');
}

$workspace = sys_get_temp_dir() . '/crm-ticket-backup-' . bin2hex(random_bytes(8));
if (!mkdir($workspace, 0700) && !is_dir($workspace)) {
    throw new RuntimeException('Cannot create isolated ticket backup test workspace.');
}
try {
    $roots = [];
    $manifestRoots = BackupService::managedRoots();
    backup_ticket_check(isset($manifestRoots['ticket-attachments']), 'Private ticket root missing.');
    backup_ticket_check($manifestRoots['ticket-attachments']['path'] === ticket_attachment_root(),
        'Private ticket root points to an incorrect directory.');

    foreach ($manifestRoots as $key => $root) {
        $fixture = $workspace . '/fixture-' . $key;
        mkdir($fixture, 0700);
        $roots[$key] = ['path' => $fixture, 'prefix' => $root['prefix']];
    }
    $ticketFixture = $roots['ticket-attachments']['path'] . '/uploads/tickets/2026/11';
    mkdir($ticketFixture, 0700, true);
    $ticketContent = 'Synthetic private ticket evidence ' . bin2hex(random_bytes(12));
    file_put_contents($ticketFixture . '/private-ticket.jpg', $ticketContent);

    $fakeSql = $workspace . '/database-fixture.sql';
    file_put_contents($fakeSql, "-- TEST FIXTURE ONLY; NO CUSTOMER DATA\nSELECT 1;\n");
    $modernZip = $workspace . '/modern-v2.zip';

    $written = BackupService::createFullBackup($modernZip, $roots, $fakeSql);
    backup_ticket_check((int) $written['format_version'] === 2, 'New backup must be v2.');
    $ticketEntries = array_values(array_filter($written['files'],
        static fn(array $file): bool => str_starts_with((string) $file['path'], 'files/private/ticket-attachments/')
    ));
    backup_ticket_check(count($ticketEntries) === 1, 'The private ticket file is not included exactly once.');
    backup_ticket_check($ticketEntries[0]['sha256'] === hash('sha256', $ticketContent),
        'Ticket attachment checksum was not preserved.');

    $verified = BackupService::validateFullBackup($modernZip);
    backup_ticket_check((int) $verified['format_version'] === 2, 'Valid v2 backup was rejected.');

    // Simulate a real pre-change backup: old v1 format has exactly the four
    // original roots, without the newly introduced private ticket directory.
    $oldZip = $workspace . '/historical-v1.zip';
    copy($modernZip, $oldZip);
    $zip = new ZipArchive();
    backup_ticket_check($zip->open($oldZip) === true, 'Could not open synthetic legacy archive.');
    $historical = json_decode((string) $zip->getFromName('manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $historical['format_version'] = 1;
    $historical['roots'] = array_values(array_filter($historical['roots'],
        static fn(array $root): bool => $root['key'] !== 'ticket-attachments'
    ));
    $historical['files'] = array_values(array_filter($historical['files'],
        static fn(array $file): bool => !str_starts_with((string) $file['path'], 'files/private/ticket-attachments/')
    ));
    foreach ($ticketEntries as $file) {
        backup_ticket_check($zip->deleteName((string) $file['path']),
            'Could not remove newer ticket fixture from historic archive.');
    }
    backup_ticket_check($zip->addFromString('manifest.json',
        json_encode($historical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'Could not rewrite legacy manifest.');
    backup_ticket_check($zip->close(), 'Could not finish synthetic v1 archive.');

    $oldVerified = BackupService::validateFullBackup($oldZip);
    backup_ticket_check((int) $oldVerified['format_version'] === 1,
        'A valid historical v1 archive is no longer compatible.');

    // Strong version-boundary assertion: v2 cannot omit the mandatory private
    // ticket root, even when a forged manifest has otherwise valid checksums.
    $incompleteZip = $workspace . '/incomplete-v2.zip';
    copy($oldZip, $incompleteZip);
    $zip = new ZipArchive();
    backup_ticket_check($zip->open($incompleteZip) === true, 'Could not open tampered archive.');
    $historical['format_version'] = 2;
    $zip->addFromString('manifest.json', json_encode($historical, JSON_THROW_ON_ERROR));
    $zip->close();
    $rejected = false;
    try {
        BackupService::validateFullBackup($incompleteZip);
    } catch (RuntimeException) {
        $rejected = true;
    }
    backup_ticket_check($rejected, 'v2 manifest missing ticket attachments root was accepted.');

    echo "Full backup private-ticket inclusion and legacy v1/v2 format compatibility passed.\n";
} finally {
    backup_ticket_remove_tree($workspace);
}
