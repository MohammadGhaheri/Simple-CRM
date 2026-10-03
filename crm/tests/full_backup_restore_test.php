<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/services/BackupService.php';

function backup_expect(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function backup_rejects(string $path, string $message): void
{
    try {
        BackupService::validateFullBackup($path);
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException($message);
}

function backup_root_manifest(array $roots): array
{
    return array_map(static fn($key, $root): array => ['key' => $key, 'prefix' => $root['prefix'], 'exists' => true], array_keys($roots), $roots);
}

function write_test_zip(string $path, array $manifest, array $entries, array $externalAttributes = []): void
{
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
        if (isset($externalAttributes[$name])) {
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $externalAttributes[$name] << 16);
        }
    }
    if (!array_key_exists('manifest.json', $entries)) {
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    $zip->close();
}

backup_expect(class_exists('ZipArchive'), 'ZipArchive is required for the local full-backup tests.');
$base = sys_get_temp_dir() . '/simple-crm-full-backup-' . bin2hex(random_bytes(6));
mkdir($base, 0700, true);
$prefixes = [
    'activity-attachments' => 'files/private/activity-attachments',
    'announcement-attachments' => 'files/private/announcement-attachments',
    'contract-documents' => 'files/private/contract-documents',
    'public-uploads' => 'files/public/uploads',
];
$roots = [];
foreach ($prefixes as $key => $prefix) {
    $path = $base . '/source/' . $key;
    mkdir($path, 0700, true);
    $roots[$key] = ['path' => $path, 'prefix' => $prefix];
}
file_put_contents($roots['activity-attachments']['path'] . '/activity.pdf', 'activity-data');
file_put_contents($roots['announcement-attachments']['path'] . '/announcement.png', 'announcement-data');
file_put_contents($roots['contract-documents']['path'] . '/contract.docx', 'contract-data');
mkdir($roots['public-uploads']['path'] . '/tickets', 0700, true);
mkdir($roots['public-uploads']['path'] . '/avatars', 0700, true);
mkdir($roots['public-uploads']['path'] . '/settings', 0700, true);
file_put_contents($roots['public-uploads']['path'] . '/tickets/ticket.png', 'ticket-data');
file_put_contents($roots['public-uploads']['path'] . '/avatars/profile.jpg', 'avatar-data');
file_put_contents($roots['public-uploads']['path'] . '/settings/icon.png', 'settings-data');
file_put_contents($roots['public-uploads']['path'] . '/.htaccess', 'deny');
file_put_contents($roots['public-uploads']['path'] . '/payload.php', '<?php');
file_put_contents($base . '/outside-source.txt', 'must-not-be-backed-up');
mkdir($base . '/source/sessions', 0700, true);
file_put_contents($base . '/source/sessions/sess_test', 'live-session-data');
$sql = $base . '/database.sql';
file_put_contents($sql, "-- Elm Simple CRM Backup\nCREATE TABLE app_settings (id INT);\nCREATE TABLE users (id INT);\nCREATE TABLE customers (id INT);\nCREATE TABLE contracts (id INT);\nCREATE TABLE tickets (id INT);\nCREATE TABLE contract_documents (id INT);\n");
$archive = $base . '/full.zip';
$manifest = BackupService::createFullBackup($archive, $roots, $sql);
$validated = BackupService::validateFullBackup($archive);
backup_expect($validated['format'] === BackupService::FORMAT && $validated['format_version'] === 1, 'Manifest format/version must be valid.');
backup_expect(count($validated['files']) === 6, 'Private attachments and recursive public uploads must be included.');
backup_expect(count($validated['roots']) === 4, 'Empty/non-empty roots must all be represented.');
backup_expect(!in_array('files/public/uploads/.htaccess', array_column($validated['files'], 'path'), true), '.htaccess must be excluded.');
backup_expect(!in_array('files/public/uploads/payload.php', array_column($validated['files'], 'path'), true), 'PHP source must be excluded.');
backup_expect(!in_array('outside-source.txt', array_column($validated['files'], 'path'), true), 'Files outside managed roots must not be included.');
backup_expect(!array_filter($validated['files'], static fn(array $file): bool => str_contains($file['path'], 'sessions')), 'Session files must not appear in the backup manifest.');
foreach (['files/public/uploads/tickets/ticket.png', 'files/public/uploads/avatars/profile.jpg', 'files/public/uploads/settings/icon.png'] as $publicFile) {
    backup_expect(in_array($publicFile, array_column($validated['files'], 'path'), true), 'Public user upload missing: ' . $publicFile);
}
foreach (array_merge([$validated['database']], $validated['files']) as $metadata) {
    backup_expect(strlen($metadata['sha256']) === 64 && $metadata['size'] >= 0, 'Every manifest file needs size and SHA-256.');
}
$zip = new ZipArchive();
$zip->open($archive);
backup_expect($zip->locateName('manifest.json') !== false && $zip->locateName('database.sql') !== false, 'ZIP must contain manifest and database dump.');
for ($zipIndex = 0; $zipIndex < $zip->numFiles; $zipIndex++) {
    backup_expect(!str_contains((string) $zip->getNameIndex($zipIndex), 'sessions'), 'Session files must not appear in ZIP entries.');
}
$zip->close();

$emptyRoots = $roots;
array_map('unlink', glob($roots['contract-documents']['path'] . '/*') ?: []);
$emptyArchive = $base . '/empty-root.zip';
$emptyManifest = BackupService::createFullBackup($emptyArchive, $emptyRoots, $sql);
$contractRoot = array_values(array_filter($emptyManifest['roots'], static fn(array $root): bool => $root['key'] === 'contract-documents'))[0];
backup_expect($contractRoot['exists'] === true && !array_filter($emptyManifest['files'], static fn(array $file): bool => str_starts_with($file['path'], 'files/private/contract-documents/')), 'An empty root must remain explicit in manifest.');

$emptyStage = $base . '/empty-stage';
$methodForEmpty = new ReflectionMethod(BackupService::class, 'extractVerified');
$methodForEmpty->invoke(null, $emptyArchive, $emptyManifest, $emptyStage);
$emptyLiveMap = [];
foreach ($prefixes as $key => $prefix) {
    $live = $base . '/empty-live/' . $key;
    mkdir($live, 0700, true);
    file_put_contents($live . '/old.txt', 'old');
    $emptyLiveMap[$key] = ['path' => $live, 'prefix' => $prefix];
}
$emptyPromote = new ReflectionMethod(BackupService::class, 'promoteRoots');
$emptySwaps = $emptyPromote->invoke(null, $emptyStage, $base . '/empty-old', $emptyManifest, $emptyLiveMap);
backup_expect(iterator_count(new FilesystemIterator($emptyLiveMap['contract-documents']['path'])) === 0, 'An empty root must restore as empty, not merge with live files.');
(new ReflectionMethod(BackupService::class, 'rollbackRoots'))->invoke(null, $emptySwaps);

$method = new ReflectionMethod(BackupService::class, 'extractVerified');
$stage = $base . '/stage';
$method->invoke(null, $archive, $manifest, $stage);
backup_expect(file_get_contents($stage . '/files/private/activity-attachments/activity.pdf') === 'activity-data', 'Verified staged extraction must preserve data.');

$verifyStage = $base . '/verify-stage';
$method->invoke(null, $archive, $manifest, $verifyStage);
$verifyMap = [];
foreach ($prefixes as $key => $prefix) {
    $live = $base . '/verify-live/' . $key;
    mkdir($live, 0700, true);
    file_put_contents($live . '/obsolete.txt', 'obsolete');
    $verifyMap[$key] = ['path' => $live, 'prefix' => $prefix];
}
$promoteForVerify = new ReflectionMethod(BackupService::class, 'promoteRoots');
$verifySwaps = $promoteForVerify->invoke(null, $verifyStage, $base . '/verify-old', $manifest, $verifyMap);
$verifyLive = new ReflectionMethod(BackupService::class, 'verifyLiveFiles');
$verifyLive->invoke(null, $manifest, $verifyMap);
backup_expect(!file_exists($verifyMap['public-uploads']['path'] . '/obsolete.txt'), 'Exact restore must remove files absent from backup.');
(new ReflectionMethod(BackupService::class, 'rollbackRoots'))->invoke(null, $verifySwaps);

$badBase = ['format' => BackupService::FORMAT, 'format_version' => 1, 'generated_at' => date(DATE_ATOM), 'app_version' => 'test', 'database' => ['path' => 'database.sql', 'size' => strlen(file_get_contents($sql)), 'sha256' => hash_file('sha256', $sql)], 'roots' => backup_root_manifest($roots), 'files' => []];
$missingManifest = $base . '/missing-manifest.zip';
write_test_zip($missingManifest, $badBase, ['manifest.json' => '', 'database.sql' => file_get_contents($sql)]);
backup_rejects($missingManifest, 'Missing/invalid manifest must be rejected.');
$missingDatabase = $base . '/missing-database.zip';
write_test_zip($missingDatabase, $badBase, ['note.txt' => 'x']);
backup_rejects($missingDatabase, 'Missing database.sql must be rejected.');
foreach ([['format' => 'wrong'], ['format_version' => 99]] as $change) {
    $bad = array_replace($badBase, $change);
    $path = $base . '/bad-' . array_key_first($change) . '.zip';
    write_test_zip($path, $bad, ['database.sql' => file_get_contents($sql)]);
    backup_rejects($path, 'Wrong manifest identity must be rejected.');
}
$hashBad = $badBase;
$hashBad['database']['sha256'] = str_repeat('0', 64);
write_test_zip($base . '/hash.zip', $hashBad, ['database.sql' => file_get_contents($sql)]);
backup_rejects($base . '/hash.zip', 'Hash mismatch must be rejected.');
$sizeBad = $badBase;
$sizeBad['database']['size']++;
write_test_zip($base . '/size.zip', $sizeBad, ['database.sql' => file_get_contents($sql)]);
backup_rejects($base . '/size.zip', 'Size mismatch must be rejected.');
write_test_zip($base . '/unexpected.zip', $badBase, ['database.sql' => file_get_contents($sql), 'files/public/uploads/unlisted.txt' => 'x']);
backup_rejects($base . '/unexpected.zip', 'Unexpected archive file must be rejected.');
foreach (['../evil.txt', '/absolute.txt', 'C:/drive.txt', 'files\\public\\uploads\\bad.txt'] as $index => $dangerous) {
    $path = $base . '/path-' . $index . '.zip';
    write_test_zip($path, $badBase, ['database.sql' => file_get_contents($sql), $dangerous => 'x']);
    backup_rejects($path, 'Dangerous path must be rejected: ' . $dangerous);
}
$duplicateManifest = $badBase;
$duplicateManifest['files'] = [['path' => 'files/public/uploads/a.txt', 'size' => 1, 'sha256' => hash('sha256', 'a')], ['path' => 'files/public/uploads/a.txt', 'size' => 1, 'sha256' => hash('sha256', 'a')]];
write_test_zip($base . '/duplicate.zip', $duplicateManifest, ['database.sql' => file_get_contents($sql), 'files/public/uploads/a.txt' => 'a']);
backup_rejects($base . '/duplicate.zip', 'Duplicate manifest paths must be rejected.');
$symlinkManifest = $badBase;
$symlinkManifest['files'] = [['path' => 'files/public/uploads/link', 'size' => 6, 'sha256' => hash('sha256', 'target')]];
write_test_zip($base . '/symlink.zip', $symlinkManifest, ['database.sql' => file_get_contents($sql), 'files/public/uploads/link' => 'target'], ['files/public/uploads/link' => 0120777]);
backup_rejects($base . '/symlink.zip', 'ZIP symlink must be rejected.');
$bomb = str_repeat('A', 2 * 1024 * 1024);
$bombManifest = $badBase;
$bombManifest['files'] = [['path' => 'files/public/uploads/bomb.txt', 'size' => strlen($bomb), 'sha256' => hash('sha256', $bomb)]];
write_test_zip($base . '/bomb.zip', $bombManifest, ['database.sql' => file_get_contents($sql), 'files/public/uploads/bomb.txt' => $bomb]);
backup_rejects($base . '/bomb.zip', 'Suspicious compression ratio must be rejected.');

if (function_exists('symlink')) {
    $outside = $base . '/outside.txt';
    file_put_contents($outside, 'outside');
    $link = $roots['activity-attachments']['path'] . '/outside-link';
    if (@symlink($outside, $link)) {
        try {
            BackupService::createFullBackup($base . '/symlink-source.zip', $roots, $sql);
            backup_expect(false, 'Source symlink must fail backup.');
        } catch (RuntimeException) {
        }
        @unlink($link);
    }
}

$promote = new ReflectionMethod(BackupService::class, 'promoteRoots');
$rollback = new ReflectionMethod(BackupService::class, 'rollbackRoots');
$liveMap = [];
$restoreManifest = ['roots' => []];
$promoteStage = $base . '/promote-stage';
foreach ($prefixes as $key => $prefix) {
    $live = $base . '/live/' . $key;
    mkdir($live, 0700, true);
    file_put_contents($live . '/extra-old.txt', 'old');
    mkdir($promoteStage . '/' . $prefix, 0700, true);
    file_put_contents($promoteStage . '/' . $prefix . '/restored.txt', $key);
    $liveMap[$key] = ['path' => $live, 'prefix' => $prefix];
    $restoreManifest['roots'][] = ['key' => $key, 'prefix' => $prefix, 'exists' => true];
}
$swapped = $promote->invoke(null, $promoteStage, $base . '/promote-old', $restoreManifest, $liveMap);
backup_expect(!file_exists($liveMap['public-uploads']['path'] . '/extra-old.txt') && is_file($liveMap['public-uploads']['path'] . '/restored.txt'), 'Full restore must replace roots exactly.');
$rollback->invoke(null, $swapped);
backup_expect(is_file($liveMap['public-uploads']['path'] . '/extra-old.txt'), 'File rollback must restore the previous root.');

$failureStage = $base . '/failure-stage';
$failureOld = $base . '/failure-old';
$failureMap = array_slice($liveMap, 0, 2, true);
$failureManifest = ['roots' => []];
$first = true;
foreach ($failureMap as $key => $root) {
    $failureManifest['roots'][] = ['key' => $key, 'prefix' => $root['prefix'], 'exists' => true];
    if ($first) { mkdir($failureStage . '/' . $root['prefix'], 0700, true); file_put_contents($failureStage . '/' . $root['prefix'] . '/new.txt', 'new'); $first = false; }
}
try { $promote->invoke(null, $failureStage, $failureOld, $failureManifest, $failureMap); } catch (Throwable) {}
foreach ($failureMap as $root) { backup_expect(is_file($root['path'] . '/extra-old.txt'), 'Promotion failure must roll back every moved live root.'); }

$serviceSource = file_get_contents(__DIR__ . '/../app/services/BackupService.php');
$controllerSource = file_get_contents(__DIR__ . '/../public/index.php');
$portalSource = file_get_contents(__DIR__ . '/../public/portal.php');
$loginSource = file_get_contents(__DIR__ . '/../public/login.php');
$settingsSource = file_get_contents(__DIR__ . '/../app/views/settings/index.php');
backup_expect(strpos($serviceSource, 'self::validateFullBackup($archivePath)') < strpos($serviceSource, 'self::createSafetySnapshot()'), 'Archive validation must happen before safety snapshot or mutation.');
backup_expect(strpos($serviceSource, 'self::createSafetySnapshot()') < strpos($serviceSource, 'self::executeSqlFile($stage'), 'Safety snapshot must precede DB mutation.');
backup_expect(str_contains($serviceSource, 'restoreDatabaseFromSnapshot') && str_contains($serviceSource, 'rollbackRoots'), 'DB and file rollback paths must exist.');
backup_expect(str_contains($serviceSource, "'type' => 'sql'") && str_contains($serviceSource, "'file_count' => 0"), 'Legacy SQL restore must preserve live files.');
backup_expect(!str_contains(substr($serviceSource, strpos($serviceSource, 'private static function restoreLegacySql'), strpos($serviceSource, 'private static function createSafetySnapshot') - strpos($serviceSource, 'private static function restoreLegacySql')), 'promoteRoots('), 'Legacy SQL restore must never replace managed file roots.');
backup_expect(!str_contains(json_encode(BackupService::managedRoots(), JSON_THROW_ON_ERROR), 'restore-snapshots'), 'Safety snapshots must not be included in managed backup roots.');
backup_expect(!str_contains(json_encode(BackupService::managedRoots(), JSON_THROW_ON_ERROR), 'sessions'), 'Runtime session files must not be included in managed backup roots.');
backup_expect(str_contains($controllerSource, 'require_admin()') && str_contains($controllerSource, 'verify_csrf()'), 'Backup/restore must remain admin and CSRF protected.');
backup_expect(str_contains($controllerSource, 'downloadFull') && str_contains($controllerSource, 'downloadSql'), 'Both full and SQL downloads must remain available.');
backup_expect(str_contains($controllerSource, 'BackupService::denyIfRestoreLocked()') && str_contains($portalSource, 'BackupService::denyIfRestoreLocked()'), 'Internal CRM and Portal must honor restore lock.');
backup_expect(str_contains($loginSource, 'BackupService::denyIfRestoreLocked()'), 'Internal login must honor restore lock.');
backup_expect(str_contains($settingsSource, 'accept=".zip,.sql"'), 'Restore UI must only advertise ZIP and SQL.');
backup_expect(!str_contains($serviceSource, 'extractTo('), 'Restore must never blindly extract ZIP archives.');

$legacyCheck = new ReflectionMethod(BackupService::class, 'assertLegacySql');
$legacyCheck->invoke(null, $sql);
$invalidSql = $base . '/invalid.sql';
file_put_contents($invalidSql, 'DROP TABLE users;');
try { $legacyCheck->invoke(null, $invalidSql); backup_expect(false, 'SQL without backup marker must be rejected.'); } catch (ReflectionException) { throw new RuntimeException('Legacy SQL validation reflection failed.'); } catch (Throwable) {}

$lockPathMethod = new ReflectionMethod(BackupService::class, 'restoreLockPath');
$lockPath = $lockPathMethod->invoke(null);
if (!file_exists($lockPath)) {
    $acquireLock = new ReflectionMethod(BackupService::class, 'acquireRestoreLock');
    $releaseLock = new ReflectionMethod(BackupService::class, 'releaseRestoreLock');
    $acquireLock->invoke(null);
    backup_expect(BackupService::isRestoreLocked(), 'Fresh restore lock must block requests.');
    $releaseLock->invoke(null);
    backup_expect(!BackupService::isRestoreLocked(), 'Restore lock must be removed after completion.');
    file_put_contents($lockPath, (string) (time() - 3600));
    backup_expect(!BackupService::isRestoreLocked() && !file_exists($lockPath), 'Stale restore lock must be cleared.');
}

$cleanup = new ReflectionMethod(BackupService::class, 'removeTree');
$cleanup->invoke(null, $base);
echo "Full backup and restore security tests passed." . PHP_EOL;
