<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/services/BackupService.php';
require __DIR__ . '/../app/services/AutomatedBackupService.php';

function auto_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function auto_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function auto_settings(array $overrides = []): array
{
    return array_replace([
        'backup_auto_enabled' => '1',
        'backup_auto_type' => 'full',
        'backup_auto_interval_hours' => '24',
        'backup_retention_count' => '14',
        'backup_retention_days' => '30',
    ], $overrides);
}

auto_expect(class_exists('ZipArchive'), 'ZipArchive is required for automated Full Backup tests.');
$base = sys_get_temp_dir() . '/simple-crm-auto-backup-' . bin2hex(random_bytes(6));
$fixture = $base . '/fixture';
$roots = [];
$prefixes = [
    'activity-attachments' => 'files/private/activity-attachments',
    'announcement-attachments' => 'files/private/announcement-attachments',
    'contract-documents' => 'files/private/contract-documents',
    'public-uploads' => 'files/public/uploads',
];
foreach ($prefixes as $key => $prefix) {
    $path = $fixture . '/' . $key;
    mkdir($path, 0700, true);
    file_put_contents($path . '/' . $key . '.dat', $key);
    $roots[$key] = ['path' => $path, 'prefix' => $prefix];
}
$sqlFixture = $fixture . '/database.sql';
file_put_contents($sqlFixture, "-- Elm Simple CRM Backup\nCREATE TABLE app_settings (id INT);\nCREATE TABLE users (id INT);\nCREATE TABLE customers (id INT);\nCREATE TABLE contracts (id INT);\nCREATE TABLE tickets (id INT);\n");
$fullCreator = static fn(string $path): array => BackupService::createFullBackup($path, $roots, $sqlFixture);
$sqlCreator = static function (string $path) use ($sqlFixture): array {
    copy($sqlFixture, $path);
    return BackupService::validateSqlBackup($path);
};
$now = 1710000000;
$clock = static function () use (&$now): int {
    return $now;
};

$disabledPath = $base . '/disabled';
$disabled = new AutomatedBackupService($disabledPath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false);
$disabledResult = $disabled->run(auto_settings(['backup_auto_enabled' => '0']));
auto_expect($disabledResult['message'] === 'Automated backup disabled' && !is_dir($disabledPath), 'Disabled automated backup must skip without creating storage.');

$fullPath = $base . '/full';
$full = new AutomatedBackupService($fullPath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false);
$first = $full->run(auto_settings());
auto_expect($first['status'] === 'success' && $first['type'] === 'full', 'Enabled backup without state must run immediately.');
$firstFile = $fullPath . '/' . $first['filename'];
auto_expect(is_file($firstFile), 'Successful Full Backup must be promoted to its final filename.');
$manifest = BackupService::validateFullBackup($firstFile);
foreach (array_values($prefixes) as $prefix) {
    auto_expect((bool) array_filter($manifest['files'], static fn(array $file): bool => str_starts_with($file['path'], $prefix . '/')), 'Full Backup must contain managed root: ' . $prefix);
}
auto_expect(!glob($fullPath . '/.backup-*.tmp'), 'Successful backup must not leave temporary files.');
$firstState = json_decode((string) file_get_contents($fullPath . '/state.json'), true, 32, JSON_THROW_ON_ERROR);
auto_expect($firstState['last_success_file'] === $first['filename'] && strlen($firstState['last_success_sha256']) === 64, 'Success state must be written after validation.');
auto_expect(!str_contains(json_encode($firstState, JSON_THROW_ON_ERROR), str_replace('\\', '/', $base)), 'State must not contain absolute filesystem paths.');

$notDue = $full->run(auto_settings());
auto_expect($notDue['message'] === 'Backup not due yet', 'Backup must skip until the configured interval passes.');
$now += 24 * 3600 + 1;
$second = $full->run(auto_settings());
auto_expect($second['status'] === 'success' && $second['filename'] !== $first['filename'], 'Backup must run after the interval passes.');

$sqlPath = $base . '/sql';
$sql = new AutomatedBackupService($sqlPath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false);
$sqlResult = $sql->run(auto_settings(['backup_auto_type' => 'sql']));
$sqlFile = $sqlPath . '/' . $sqlResult['filename'];
$sqlMetadata = BackupService::validateSqlBackup($sqlFile);
auto_expect($sqlResult['type'] === 'sql' && $sqlMetadata['size'] > 0 && strlen($sqlMetadata['sha256']) === 64, 'SQL mode must validate marker, size and SHA-256.');

foreach (['full', 'sql'] as $invalidType) {
    $invalidPath = $base . '/invalid-' . $invalidType;
    $invalidCreator = static function (string $path): array {
        file_put_contents($path, 'invalid backup output');
        return [];
    };
    $invalidService = new AutomatedBackupService(
        $invalidPath,
        $clock,
        $invalidType === 'full' ? $invalidCreator : $fullCreator,
        $invalidType === 'sql' ? $invalidCreator : $sqlCreator,
        static fn(): bool => false
    );
    try {
        $invalidService->run(auto_settings(['backup_auto_type' => $invalidType]));
        auto_expect(false, 'Invalid ' . $invalidType . ' output must fail validation.');
    } catch (RuntimeException) {
    }
    auto_expect(!glob($invalidPath . '/elm-simple-crm-auto-*') && !glob($invalidPath . '/.backup-*.tmp'), 'Invalid ' . $invalidType . ' output must not leave final or temporary files.');
}

$failurePath = $base . '/failure';
$failure = new AutomatedBackupService($failurePath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false);
$initialSuccess = $failure->run(auto_settings(['backup_auto_type' => 'sql']));
$successBeforeFailure = $failure->status()['last_success_at'];
$now += 24 * 3600 + 1;
$failureCalls = 0;
$failingCreator = static function (string $path) use (&$failureCalls): array {
    $failureCalls++;
    file_put_contents($path, 'partial');
    throw new RuntimeException('simulated generation failure');
};
$failing = new AutomatedBackupService($failurePath, $clock, $fullCreator, $failingCreator, static fn(): bool => false);
for ($attempt = 1; $attempt <= 2; $attempt++) {
    try {
        $failing->run(auto_settings(['backup_auto_type' => 'sql']));
        auto_expect(false, 'A generator failure must be reported.');
    } catch (RuntimeException $error) {
        auto_expect($error->getMessage() === 'Automated backup failed.', 'Failure output must remain short and safe.');
    }
}
$failureState = $failing->status();
auto_expect($failureCalls === 2, 'A failed backup must remain due on the next Cron run.');
auto_expect($failureState['last_success_at'] === $successBeforeFailure && $failureState['last_failure_at'] === $now, 'Failure must not alter last_success metadata.');
auto_expect(!glob($failurePath . '/.backup-*.tmp') && is_file($failurePath . '/' . $initialSuccess['filename']), 'Failure must remove temp files and preserve previous final backups.');
auto_expect(!glob($failurePath . '/.state-*.tmp'), 'Atomic state writes must not leave temporary files.');

$restorePath = $base . '/restore';
$restore = new AutomatedBackupService($restorePath, $clock, $fullCreator, $sqlCreator, static fn(): bool => true);
$restoreResult = $restore->run(auto_settings());
auto_expect($restoreResult['message'] === 'Backup skipped: restore in progress', 'Restore lock must skip automated backup.');
auto_expect(!glob($restorePath . '/elm-simple-crm-auto-*'), 'Restore skip must not create a backup or success state.');

$malformedPath = $base . '/malformed';
mkdir($malformedPath, 0700, true);
file_put_contents($malformedPath . '/state.json', '{broken');
$malformed = new AutomatedBackupService($malformedPath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false);
auto_expect($malformed->status()['last_success_at'] === 0, 'Malformed state must be handled without a permanent crash.');
$malformedResult = $malformed->run(auto_settings(['backup_auto_type' => 'sql']));
auto_expect($malformedResult['status'] === 'success', 'Malformed state must allow a fresh due backup.');

$outsideDirectory = $base . '/outside-automated-backups';
$symlinkPath = $base . '/symlink-automated-backups';
mkdir($outsideDirectory, 0700, true);
if (function_exists('symlink') && @symlink($outsideDirectory, $symlinkPath)) {
    try {
        (new AutomatedBackupService($symlinkPath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false))->run(auto_settings());
        auto_expect(false, 'A symlinked automated-backup root must be rejected.');
    } catch (RuntimeException) {
    }
    unlink($symlinkPath);
}

$retentionPath = $base . '/retention';
mkdir($retentionPath, 0700, true);
$retention = new AutomatedBackupService($retentionPath, $clock, $fullCreator, $sqlCreator, static fn(): bool => false);
$retentionFiles = [
    'elm-simple-crm-auto-full-20240101-000000.zip' => $now - 80 * 86400,
    'elm-simple-crm-auto-database-20240201-000000.sql' => $now - 60 * 86400,
    'elm-simple-crm-auto-full-20240301-000000.zip' => $now - 10 * 86400,
    'elm-simple-crm-auto-database-20240302-000000.sql' => $now - 5 * 86400,
    'elm-simple-crm-auto-full-20240303-000000.zip' => $now,
];
foreach ($retentionFiles as $name => $mtime) {
    file_put_contents($retentionPath . '/' . $name, 'backup');
    touch($retentionPath . '/' . $name, $mtime);
}
file_put_contents($retentionPath . '/notes.txt', 'keep');
file_put_contents($retentionPath . '/state.json', '{}');
file_put_contents($retentionPath . '/backup.lock', '');
$retention->applyRetention(2, 30, 'elm-simple-crm-auto-full-20240303-000000.zip');
$remainingBackups = array_values(array_filter(scandir($retentionPath), static fn(string $name): bool => str_starts_with($name, 'elm-simple-crm-auto-')));
auto_expect(count($remainingBackups) === 2, 'Retention must apply age cleanup and a combined Full/SQL count limit.');
auto_expect(in_array('elm-simple-crm-auto-full-20240303-000000.zip', $remainingBackups, true), 'Retention must always preserve the newest protected backup.');
foreach (['notes.txt', 'state.json', 'backup.lock'] as $safeFile) {
    auto_expect(is_file($retentionPath . '/' . $safeFile), 'Retention must not delete arbitrary or control files: ' . $safeFile);
}

if (function_exists('proc_open')) {
    $concurrencyPath = $base . '/concurrency';
    $workerPath = $base . '/backup-worker.php';
    $backupServicePath = realpath(__DIR__ . '/../app/services/BackupService.php');
    $automatedServicePath = realpath(__DIR__ . '/../app/services/AutomatedBackupService.php');
    $workerSource = '<?php require ' . var_export($backupServicePath, true) . '; require ' . var_export($automatedServicePath, true) . '; '
        . '$creator = static function(string $path): array { usleep(1200000); file_put_contents($path, "-- Elm Simple CRM Backup\\nCREATE TABLE users (id INT);\\n"); return BackupService::validateSqlBackup($path); }; '
        . '$settings = ["backup_auto_enabled"=>"1","backup_auto_type"=>"sql","backup_auto_interval_hours"=>"24","backup_retention_count"=>"14","backup_retention_days"=>"30"]; '
        . '$result = (new AutomatedBackupService($argv[1], null, null, $creator, static fn(): bool => false))->run($settings); echo json_encode($result);';
    file_put_contents($workerPath, $workerSource);
    $firstProcess = proc_open([PHP_BINARY, $workerPath, $concurrencyPath], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $firstPipes);
    auto_expect(is_resource($firstProcess), 'First concurrent backup worker must start.');
    fclose($firstPipes[0]);
    for ($wait = 0; $wait < 40 && !is_file($concurrencyPath . '/backup.lock'); $wait++) {
        usleep(50000);
    }
    $secondProcess = proc_open([PHP_BINARY, $workerPath, $concurrencyPath], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $secondPipes);
    auto_expect(is_resource($secondProcess), 'Second concurrent backup worker must start.');
    fclose($secondPipes[0]);
    $secondOutput = stream_get_contents($secondPipes[1]);
    $secondError = stream_get_contents($secondPipes[2]);
    fclose($secondPipes[1]);
    fclose($secondPipes[2]);
    auto_expect(proc_close($secondProcess) === 0, 'Second worker failed: ' . $secondError);
    $firstOutput = stream_get_contents($firstPipes[1]);
    $firstError = stream_get_contents($firstPipes[2]);
    fclose($firstPipes[1]);
    fclose($firstPipes[2]);
    auto_expect(proc_close($firstProcess) === 0, 'First worker failed: ' . $firstError);
    $outputs = [json_decode($firstOutput, true), json_decode($secondOutput, true)];
    auto_expect(count(array_filter($outputs, static fn($result): bool => ($result['status'] ?? '') === 'success')) === 1, 'Concurrent workers must create exactly one backup.');
    auto_expect(count(array_filter($outputs, static fn($result): bool => ($result['message'] ?? '') === 'Backup skipped: another backup is running')) === 1, 'Second process must observe the non-blocking lock.');
    $afterLock = (new AutomatedBackupService($concurrencyPath, null, null, $sqlCreator, static fn(): bool => false))->run(auto_settings(['backup_auto_type' => 'sql']));
    auto_expect($afterLock['message'] === 'Backup not due yet', 'Backup lock must be released after the first process finishes.');
}

$defaults = Setting::defaults();
foreach (['backup_auto_enabled' => '0', 'backup_auto_type' => 'full', 'backup_auto_interval_hours' => '24', 'backup_retention_count' => '14', 'backup_retention_days' => '30'] as $key => $value) {
    auto_expect(($defaults[$key] ?? null) === $value, 'Setting default is missing: ' . $key);
}
foreach ([
    ['backup_auto_type' => 'invalid'],
    ['backup_auto_interval_hours' => '10'],
    ['backup_retention_count' => '0'],
    ['backup_retention_days' => '366'],
] as $invalid) {
    try {
        AutomatedBackupService::validateSettings(array_replace(auto_settings(), $invalid));
        auto_expect(false, 'Invalid automated backup settings must be rejected.');
    } catch (RuntimeException) {
    }
}

$viewSource = file_get_contents(__DIR__ . '/../app/views/settings/index.php');
$controllerSource = file_get_contents(__DIR__ . '/../public/index.php');
$installSource = file_get_contents(__DIR__ . '/../public/install.php');
$seedSource = file_get_contents(__DIR__ . '/../database/seed.sql');
$cronSource = file_get_contents(__DIR__ . '/../cron/backup.php');
$serviceSource = file_get_contents(__DIR__ . '/../app/services/AutomatedBackupService.php');
$backupServiceSource = file_get_contents(__DIR__ . '/../app/services/BackupService.php');
auto_expect(str_contains($viewSource, 'پشتیبان‌گیری خودکار') && str_contains($viewSource, 'هنوز بکاپ خودکاری ثبت نشده است.'), 'Settings UI must include automated backup controls and empty status.');
auto_expect(str_contains($controllerSource, 'AutomatedBackupService::validateSettings($_POST)'), 'Settings save must use server-side validation.');
foreach (['backup_auto_enabled', 'backup_auto_type', 'backup_auto_interval_hours', 'backup_retention_count', 'backup_retention_days'] as $key) {
    auto_expect(str_contains($installSource, "'$key'") && str_contains($seedSource, "'$key'"), 'Fresh install and seed defaults must include ' . $key);
}
auto_expect(str_contains($cronSource, "PHP_SAPI !== 'cli'"), 'Automated backup Cron must be CLI-only.');
auto_expect(str_contains($cronSource, 'AutomatedBackupService') && !str_contains($cronSource, 'createFullBackup('), 'Cron must delegate backup logic to the service.');
auto_expect(str_contains($serviceSource, "flock(\$handle, LOCK_EX | LOCK_NB)") && str_contains($serviceSource, "'/backup.lock'"), 'Automated backups must use the fixed non-blocking OS lock.');
auto_expect(str_contains($serviceSource, "'/state.json'") && str_contains($serviceSource, "@rename(\$temporary, \$path)"), 'State writes must use the fixed filename and atomic temp promotion.');
auto_expect(!str_contains($serviceSource, 'exec(') && !str_contains($serviceSource, 'shell_exec(') && !str_contains($backupServiceSource, 'mysqldump'), 'Automated backups must not add shell or mysqldump dependencies.');
$sqlMethodStart = strpos($backupServiceSource, 'public static function createSqlBackup');
$sqlMethodEnd = strpos($backupServiceSource, 'public static function validateSqlBackup', $sqlMethodStart);
auto_expect(!str_contains(substr($backupServiceSource, $sqlMethodStart, $sqlMethodEnd - $sqlMethodStart), 'requireZip'), 'SQL backup creation must not depend on ZipArchive.');

auto_remove_tree($base);
echo "Automated backup tests passed." . PHP_EOL;
