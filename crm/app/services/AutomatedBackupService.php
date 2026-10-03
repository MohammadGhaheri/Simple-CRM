<?php

declare(strict_types=1);

class AutomatedBackupService
{
    public const STATE_VERSION = 1;
    private const FULL_PATTERN = '/^elm-simple-crm-auto-full-\d{8}-\d{6}\.zip$/';
    private const SQL_PATTERN = '/^elm-simple-crm-auto-database-\d{8}-\d{6}\.sql$/';

    private string $storagePath;
    private bool $usesDefaultStorage;
    private $clock;
    private $fullCreator;
    private $sqlCreator;
    private $restoreChecker;

    public function __construct(
        ?string $storagePath = null,
        ?callable $clock = null,
        ?callable $fullCreator = null,
        ?callable $sqlCreator = null,
        ?callable $restoreChecker = null
    ) {
        $this->usesDefaultStorage = $storagePath === null;
        $this->storagePath = $storagePath ?? dirname(__DIR__, 2) . '/storage/automated-backups';
        $this->clock = $clock ?? static fn(): int => time();
        $this->fullCreator = $fullCreator ?? static fn(string $path): array => BackupService::createFullBackup($path);
        $this->sqlCreator = $sqlCreator ?? static fn(string $path): array => BackupService::createSqlBackup($path);
        $this->restoreChecker = $restoreChecker ?? static fn(): bool => BackupService::isRestoreLocked();
    }

    public static function validateSettings(array $input): array
    {
        $type = (string) ($input['backup_auto_type'] ?? 'full');
        if (!in_array($type, ['full', 'sql'], true)) {
            throw new RuntimeException('نوع بکاپ خودکار معتبر نیست.');
        }
        $interval = (int) ($input['backup_auto_interval_hours'] ?? 24);
        if (!in_array($interval, [6, 12, 24, 168], true)) {
            throw new RuntimeException('فاصله اجرای بکاپ خودکار معتبر نیست.');
        }
        $retentionCount = filter_var($input['backup_retention_count'] ?? 14, FILTER_VALIDATE_INT);
        $retentionDays = filter_var($input['backup_retention_days'] ?? 30, FILTER_VALIDATE_INT);
        if ($retentionCount === false || $retentionCount < 1 || $retentionCount > 100) {
            throw new RuntimeException('تعداد نسخه‌های قابل نگهداری باید بین ۱ تا ۱۰۰ باشد.');
        }
        if ($retentionDays === false || $retentionDays < 1 || $retentionDays > 365) {
            throw new RuntimeException('حداکثر عمر نسخه‌ها باید بین ۱ تا ۳۶۵ روز باشد.');
        }
        return [
            'backup_auto_enabled' => isset($input['backup_auto_enabled']) && (string) $input['backup_auto_enabled'] !== '0' ? '1' : '0',
            'backup_auto_type' => $type,
            'backup_auto_interval_hours' => (string) $interval,
            'backup_retention_count' => (string) $retentionCount,
            'backup_retention_days' => (string) $retentionDays,
        ];
    }

    public function run(array $settings): array
    {
        $settings = self::validateSettings($settings);
        if ($settings['backup_auto_enabled'] !== '1') {
            return ['status' => 'skipped', 'message' => 'Automated backup disabled'];
        }

        $directory = $this->prepareStorage();
        $lockHandle = $this->acquireLock($directory);
        if ($lockHandle === null) {
            return ['status' => 'skipped', 'message' => 'Backup skipped: another backup is running'];
        }

        try {
            if (($this->restoreChecker)()) {
                return ['status' => 'skipped', 'message' => 'Backup skipped: restore in progress'];
            }

            $state = $this->readState($directory);
            $now = ($this->clock)();
            $lastSuccess = (int) ($state['last_success_at'] ?? 0);
            $intervalSeconds = (int) $settings['backup_auto_interval_hours'] * 3600;
            if ($lastSuccess > 0 && $now - $lastSuccess < $intervalSeconds) {
                return ['status' => 'skipped', 'message' => 'Backup not due yet'];
            }

            $state['last_attempt_at'] = $now;
            $this->writeState($directory, $state);
            return $this->createDueBackup($directory, $settings, $state, $now);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function status(): array
    {
        if (!is_dir($this->storagePath) || is_link($this->storagePath)) {
            return $this->emptyState();
        }
        try {
            return $this->readState(rtrim((string) realpath($this->storagePath), '/\\'));
        } catch (Throwable) {
            return $this->emptyState();
        }
    }

    public function applyRetention(int $retentionCount, int $retentionDays, string $protectedFilename = ''): void
    {
        $directory = $this->prepareStorage();
        $backups = $this->backupFiles($directory);
        if (!$backups) {
            return;
        }
        usort($backups, static fn(array $left, array $right): int => $right['mtime'] <=> $left['mtime']);
        $newest = $backups[0]['name'];
        $protected = array_filter([$newest, basename($protectedFilename)]);
        $cutoff = ($this->clock)() - max(1, min(365, $retentionDays)) * 86400;

        foreach ($backups as $backup) {
            if ($backup['mtime'] < $cutoff && !in_array($backup['name'], $protected, true)) {
                @unlink($backup['path']);
            }
        }

        $remaining = $this->backupFiles($directory);
        usort($remaining, static fn(array $left, array $right): int => $right['mtime'] <=> $left['mtime']);
        $keep = max(1, min(100, $retentionCount));
        foreach (array_slice($remaining, $keep) as $backup) {
            if (!in_array($backup['name'], $protected, true)) {
                @unlink($backup['path']);
            }
        }
    }

    private function createDueBackup(string $directory, array $settings, array $state, int $now): array
    {
        $type = $settings['backup_auto_type'];
        $stamp = date('Ymd-His', $now);
        $filename = $type === 'full'
            ? 'elm-simple-crm-auto-full-' . $stamp . '.zip'
            : 'elm-simple-crm-auto-database-' . $stamp . '.sql';
        $finalPath = $directory . '/' . $filename;
        $temporaryPath = $directory . '/.backup-' . bin2hex(random_bytes(12)) . '.tmp';
        $promoted = false;
        $committed = false;

        try {
            if (is_file($finalPath) || is_link($finalPath)) {
                throw new RuntimeException('نام فایل بکاپ خودکار تکراری است.');
            }
            if ($type === 'full') {
                ($this->fullCreator)($temporaryPath);
                BackupService::validateFullBackup($temporaryPath);
            } else {
                ($this->sqlCreator)($temporaryPath);
                BackupService::validateSqlBackup($temporaryPath);
            }
            if (DIRECTORY_SEPARATOR !== '\\') {
                @chmod($temporaryPath, 0600);
            }

            $size = (int) filesize($temporaryPath);
            $sha256 = hash_file('sha256', $temporaryPath);
            if ($size <= 0 || !is_string($sha256) || strlen($sha256) !== 64) {
                throw new RuntimeException('اعتبارسنجی خروجی بکاپ خودکار ناموفق بود.');
            }
            if (!@rename($temporaryPath, $finalPath)) {
                throw new RuntimeException('نهایی‌سازی فایل بکاپ خودکار ناموفق بود.');
            }
            $promoted = true;

            $state['last_success_at'] = $now;
            $state['last_success_type'] = $type;
            $state['last_success_file'] = $filename;
            $state['last_success_size'] = $size;
            $state['last_success_sha256'] = $sha256;
            $state['last_failure_at'] = 0;
            $state['last_failure_message'] = '';
            $this->writeState($directory, $state);
            $committed = true;

            try {
                $this->applyRetention((int) $settings['backup_retention_count'], (int) $settings['backup_retention_days'], $filename);
            } catch (Throwable $retentionError) {
                error_log('CRM automated backup retention failed: ' . $retentionError->getMessage());
            }

            return [
                'status' => 'success',
                'type' => $type,
                'filename' => $filename,
                'message' => $type === 'full'
                    ? 'Automated full backup created: ' . $filename
                    : 'Automated SQL backup created: ' . $filename,
            ];
        } catch (Throwable $error) {
            if (is_file($temporaryPath) || is_link($temporaryPath)) {
                @unlink($temporaryPath);
            }
            if ($promoted && !$committed && (is_file($finalPath) || is_link($finalPath))) {
                @unlink($finalPath);
            }
            $state['last_failure_at'] = $now;
            $state['last_failure_message'] = 'Automated backup failed.';
            $this->writeState($directory, $state);
            error_log('CRM automated backup failed: ' . $error->getMessage());
            throw new RuntimeException('Automated backup failed.', 0, $error);
        }
    }

    private function prepareStorage(): string
    {
        if (is_link($this->storagePath)) {
            throw new RuntimeException('مسیر بکاپ خودکار معتبر نیست.');
        }
        if (!is_dir($this->storagePath) && !@mkdir($this->storagePath, 0700, true) && !is_dir($this->storagePath)) {
            throw new RuntimeException('ساخت فضای بکاپ خودکار ناموفق بود.');
        }
        $realPath = realpath($this->storagePath);
        if ($realPath === false || !is_writable($realPath)) {
            throw new RuntimeException('فضای بکاپ خودکار قابل نوشتن نیست.');
        }
        if ($this->usesDefaultStorage) {
            $storageRoot = realpath(dirname(__DIR__, 2) . '/storage');
            $boundary = $storageRoot === false ? '' : rtrim(str_replace('\\', '/', $storageRoot), '/') . '/';
            $normalized = rtrim(str_replace('\\', '/', $realPath), '/') . '/';
            if ($boundary === '' || !str_starts_with($normalized, $boundary)) {
                throw new RuntimeException('مسیر بکاپ خودکار معتبر نیست.');
            }
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($realPath, 0700);
        }
        return rtrim($realPath, '/\\');
    }

    private function acquireLock(string $directory)
    {
        $path = $directory . '/backup.lock';
        if (is_link($path)) {
            throw new RuntimeException('قفل بکاپ خودکار معتبر نیست.');
        }
        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new RuntimeException('ساخت قفل بکاپ خودکار ناموفق بود.');
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($path, 0600);
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    private function readState(string $directory): array
    {
        $path = $directory . '/state.json';
        if (!is_file($path)) {
            return $this->emptyState();
        }
        if (is_link($path)) {
            throw new RuntimeException('فایل وضعیت بکاپ خودکار معتبر نیست.');
        }
        try {
            $state = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($state) || ($state['version'] ?? null) !== self::STATE_VERSION) {
                throw new UnexpectedValueException('Invalid automated backup state.');
            }
            return array_replace($this->emptyState(), $state);
        } catch (Throwable) {
            error_log('CRM automated backup state was malformed and has been reset.');
            return $this->emptyState();
        }
    }

    private function writeState(string $directory, array $state): void
    {
        $path = $directory . '/state.json';
        if (is_link($path)) {
            throw new RuntimeException('فایل وضعیت بکاپ خودکار معتبر نیست.');
        }
        $temporary = $directory . '/.state-' . bin2hex(random_bytes(10)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) {
            throw new RuntimeException('ثبت وضعیت بکاپ خودکار ناموفق بود.');
        }
        try {
            $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new RuntimeException('ثبت وضعیت بکاپ خودکار ناموفق بود.');
            }
        } finally {
            fclose($handle);
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($temporary, 0600);
        }
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('نهایی‌سازی وضعیت بکاپ خودکار ناموفق بود.');
        }
    }

    private function backupFiles(string $directory): array
    {
        $files = [];
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
            $name = $file->getFilename();
            if (!$file->isFile() || $file->isLink() || (!$this->isBackupFilename($name))) {
                continue;
            }
            $files[] = ['name' => $name, 'path' => $file->getPathname(), 'mtime' => $file->getMTime()];
        }
        return $files;
    }

    private function isBackupFilename(string $filename): bool
    {
        return preg_match(self::FULL_PATTERN, $filename) === 1 || preg_match(self::SQL_PATTERN, $filename) === 1;
    }

    private function emptyState(): array
    {
        return [
            'version' => self::STATE_VERSION,
            'last_success_at' => 0,
            'last_success_type' => '',
            'last_success_file' => '',
            'last_success_size' => 0,
            'last_success_sha256' => '',
            'last_attempt_at' => 0,
            'last_failure_at' => 0,
            'last_failure_message' => '',
        ];
    }
}
