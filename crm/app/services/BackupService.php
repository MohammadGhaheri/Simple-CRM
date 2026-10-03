<?php

declare(strict_types=1);

class BackupService
{
    public const FORMAT = 'elm-simple-crm-full-backup';
    public const FORMAT_VERSION = 1;
    public const MAX_ENTRIES = 10000;
    public const MAX_TOTAL_SIZE = 1073741824;
    public const MAX_FILE_SIZE = 104857600;
    public const MAX_COMPRESSION_RATIO = 200;
    private const LOCK_MAX_AGE = 1800;

    public static function managedRoots(): array
    {
        return [
            'activity-attachments' => ['path' => activity_attachment_root(), 'prefix' => 'files/private/activity-attachments'],
            'announcement-attachments' => ['path' => announcement_attachment_root(), 'prefix' => 'files/private/announcement-attachments'],
            'contract-documents' => ['path' => contract_document_root(), 'prefix' => 'files/private/contract-documents'],
            'public-uploads' => ['path' => public_upload_root() . '/uploads', 'prefix' => 'files/public/uploads'],
        ];
    }

    public static function downloadFull(): never
    {
        self::requireZip();
        $workspace = self::makeWorkspace('backup');
        $path = $workspace . '/elm-simple-crm-full-backup-' . date('Ymd-His') . '.zip';
        try {
            self::createFullBackup($path);
            register_shutdown_function(static fn() => self::removeTree($workspace));
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        } catch (Throwable $e) {
            self::removeTree($workspace);
            throw $e;
        }
    }

    public static function downloadSql(): never
    {
        $filename = 'elm-simple-crm-backup-' . date('Ymd-His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo self::dump();
        exit;
    }

    public static function createSqlBackup(string $destination): array
    {
        try {
            self::writeDatabaseDump($destination);
            return self::validateSqlBackup($destination);
        } catch (Throwable $error) {
            if (is_file($destination)) {
                @unlink($destination);
            }
            throw $error;
        }
    }

    public static function validateSqlBackup(string $path): array
    {
        if (!is_file($path) || (int) filesize($path) <= 0) {
            throw new RuntimeException('فایل بکاپ SQL خالی یا نامعتبر است.');
        }
        self::assertLegacySql($path);
        $hash = hash_file('sha256', $path);
        if (!is_string($hash) || strlen($hash) !== 64) {
            throw new RuntimeException('محاسبه SHA-256 بکاپ SQL ناموفق بود.');
        }
        return ['size' => (int) filesize($path), 'sha256' => $hash];
    }

    public static function download(): never
    {
        self::downloadSql();
    }

    public static function createFullBackup(string $destination, ?array $roots = null, ?string $databaseSqlPath = null): array
    {
        self::requireZip();
        $roots ??= self::managedRoots();
        $ownedSql = $databaseSqlPath === null;
        if ($ownedSql) {
            $databaseSqlPath = dirname($destination) . '/database-' . bin2hex(random_bytes(6)) . '.sql';
            self::writeDatabaseDump($databaseSqlPath);
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ساخت فایل بکاپ کامل ناموفق بود.');
            }
            $manifest = [
                'format' => self::FORMAT,
                'format_version' => self::FORMAT_VERSION,
                'generated_at' => date(DATE_ATOM),
                'app_version' => app_version(),
                'database' => self::fileMetadata($databaseSqlPath, 'database.sql'),
                'roots' => [],
                'files' => [],
            ];
            if (!$zip->addFile($databaseSqlPath, 'database.sql')) {
                throw new RuntimeException('افزودن database.sql به بکاپ ناموفق بود.');
            }
            foreach ($roots as $key => $root) {
                $physical = rtrim((string) $root['path'], '/\\');
                $prefix = trim((string) $root['prefix'], '/');
                self::assertArchivePath($prefix . '/placeholder');
                if (is_link($physical)) {
                    throw new RuntimeException('Root داده نمی‌تواند symbolic link باشد: ' . $key);
                }
                $exists = is_dir($physical);
                $manifest['roots'][] = ['key' => (string) $key, 'prefix' => $prefix, 'exists' => $exists];
                if (!$exists) {
                    continue;
                }
                foreach (self::filesInRoot($physical) as $file) {
                    $logical = $prefix . '/' . $file['relative'];
                    if (!$zip->addFile($file['absolute'], $logical)) {
                        $zip->close();
                        throw new RuntimeException('افزودن فایل به بکاپ ناموفق بود: ' . $logical);
                    }
                    $manifest['files'][] = self::fileMetadata($file['absolute'], $logical);
                }
            }
            $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $zip->addFromString('manifest.json', $manifestJson);
            if (!$zip->close()) {
                throw new RuntimeException('نهایی‌سازی فایل بکاپ کامل ناموفق بود.');
            }
            self::validateFullBackup($destination);
            return $manifest;
        } catch (Throwable $error) {
            if (isset($zip) && $zip instanceof ZipArchive) {
                @$zip->close();
            }
            if (is_file($destination)) {
                @unlink($destination);
            }
            throw $error;
        } finally {
            if ($ownedSql && is_file((string) $databaseSqlPath)) {
                @unlink((string) $databaseSqlPath);
            }
        }
    }

    public static function restoreUploaded(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new RuntimeException('حجم فایل از محدودیت آپلود PHP روی سرور بیشتر است.');
            }
            throw new RuntimeException('فایل بکاپ انتخاب نشده یا آپلود آن ناموفق بوده است.');
        }
        $name = sanitize_uploaded_filename((string) ($file['name'] ?? 'backup'));
        if ((int) ($file['size'] ?? 0) > self::MAX_TOTAL_SIZE) {
            throw new RuntimeException('حجم فایل بکاپ از سقف ایمن Restore بیشتر است.');
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['zip', 'sql'], true)) {
            throw new RuntimeException('فقط بکاپ ZIP کامل یا بکاپ SQL قدیمی قابل بازگردانی است.');
        }
        return $extension === 'zip' ? self::restoreFull((string) $file['tmp_name']) : self::restoreLegacySql((string) $file['tmp_name']);
    }

    public static function validateFullBackup(string $archivePath): array
    {
        self::requireZip();
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('فایل ZIP معتبر نیست.');
        }
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('تعداد فایل‌های بکاپ از حد مجاز بیشتر است.');
            }
            $names = [];
            $totalSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                self::assertArchivePath($name);
                if (isset($names[$name])) {
                    throw new RuntimeException('مسیر تکراری و ناامن در فایل ZIP شناسایی شد.');
                }
                $names[$name] = true;
                if (self::zipEntryIsSymlink($zip, $i)) {
                    throw new RuntimeException('وجود symbolic link در بکاپ مجاز نیست.');
                }
                $size = (int) ($stat['size'] ?? 0);
                $compressed = (int) ($stat['comp_size'] ?? 0);
                if ($size > self::MAX_FILE_SIZE) {
                    throw new RuntimeException('یکی از فایل‌های بکاپ بیش از حد مجاز بزرگ است.');
                }
                $totalSize += $size;
                if ($totalSize > self::MAX_TOTAL_SIZE) {
                    throw new RuntimeException('حجم بازشده بکاپ از حد مجاز بیشتر است.');
                }
                if ($size > 1048576 && $compressed > 0 && ($size / $compressed) > self::MAX_COMPRESSION_RATIO) {
                    throw new RuntimeException('نسبت فشرده‌سازی مشکوک در بکاپ شناسایی شد.');
                }
            }
            if (!isset($names['manifest.json']) || !isset($names['database.sql'])) {
                throw new RuntimeException('manifest.json یا database.sql در بکاپ وجود ندارد.');
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, 64, JSON_THROW_ON_ERROR);
            self::validateManifestShape($manifest);
            $expected = ['manifest.json' => true, 'database.sql' => true];
            foreach ($manifest['files'] as $metadata) {
                $expected[(string) $metadata['path']] = true;
            }
            foreach ($names as $name => $_) {
                if (!str_ends_with($name, '/') && !isset($expected[$name])) {
                    throw new RuntimeException('فایل خارج از manifest در بکاپ وجود دارد: ' . $name);
                }
            }
            $actualFiles = array_filter(array_keys($names), static fn(string $name): bool => !str_ends_with($name, '/'));
            if (count($expected) !== count($actualFiles)) {
                throw new RuntimeException('یک یا چند فایل manifest در ZIP وجود ندارد.');
            }
            self::verifyZipEntry($zip, $manifest['database']);
            foreach ($manifest['files'] as $metadata) {
                self::verifyZipEntry($zip, $metadata);
            }
            return $manifest;
        } catch (JsonException $e) {
            throw new RuntimeException('ساختار JSON فایل manifest معتبر نیست.', 0, $e);
        } finally {
            $zip->close();
        }
    }

    public static function isRestoreLocked(): bool
    {
        $path = self::restoreLockPath();
        if (!is_file($path)) {
            return false;
        }
        $timestamp = (int) trim((string) @file_get_contents($path));
        if ($timestamp <= 0 || time() - $timestamp > self::LOCK_MAX_AGE) {
            @unlink($path);
            return false;
        }
        return true;
    }

    public static function denyIfRestoreLocked(): void
    {
        if (self::isRestoreLocked()) {
            http_response_code(503);
            header('Retry-After: 120');
            exit('سامانه در حال بازگردانی پشتیبان است. چند دقیقه دیگر دوباره تلاش کنید.');
        }
    }

    private static function restoreFull(string $archivePath): array
    {
        $manifest = self::validateFullBackup($archivePath);
        $workspace = self::makeWorkspace('restore');
        $snapshot = null;
        $lockHeld = false;
        $swapped = [];
        try {
            $stage = $workspace . '/stage';
            self::extractVerified($archivePath, $manifest, $stage);
            self::assertDiskSpace($workspace, self::manifestTotalSize($manifest) * 3);
            $snapshot = self::createSafetySnapshot();
            self::acquireRestoreLock();
            $lockHeld = true;
            try {
                self::executeSqlFile($stage . '/database.sql');
                $swapped = self::promoteRoots($stage, $workspace . '/old', $manifest);
                self::verifyLiveFiles($manifest);
                self::verifyDatabase($stage . '/database.sql');
            } catch (Throwable $restoreError) {
                $rollbackError = null;
                $fileRollbackFailed = str_contains($restoreError->getMessage(), 'Rollback فایل‌ها ناموفق');
                try {
                    if (!$fileRollbackFailed) {
                        self::rollbackRoots($swapped);
                    }
                    self::restoreDatabaseFromSnapshot($snapshot);
                } catch (Throwable $e) {
                    $rollbackError = $e;
                }
                if ($rollbackError || $fileRollbackFailed) {
                    throw new RuntimeException('بازگردانی شکست خورد و Rollback نیز ناموفق بود؛ وضعیت بحرانی است: ' . $restoreError->getMessage(), 0, $rollbackError);
                }
                throw new RuntimeException('بازگردانی شکست خورد؛ Rollback با موفقیت انجام شد: ' . $restoreError->getMessage(), 0, $restoreError);
            }
            self::removeTree($workspace . '/old');
            return ['type' => 'full', 'generated_at' => (string) $manifest['generated_at'], 'source_app_version' => (string) $manifest['app_version'], 'current_app_version' => app_version(), 'file_count' => count($manifest['files']), 'database_restored' => true, 'snapshot' => basename($snapshot)];
        } finally {
            if ($lockHeld) {
                self::releaseRestoreLock();
            }
            self::removeTree($workspace);
        }
    }

    private static function restoreLegacySql(string $sqlPath): array
    {
        self::assertLegacySql($sqlPath);
        $snapshot = self::createSafetySnapshot();
        self::acquireRestoreLock();
        try {
            try {
                self::executeSqlFile($sqlPath);
                self::verifyDatabase($sqlPath);
            } catch (Throwable $error) {
                try {
                    self::restoreDatabaseFromSnapshot($snapshot);
                } catch (Throwable $rollbackError) {
                    throw new RuntimeException('بازگردانی SQL و Rollback هر دو ناموفق بودند؛ وضعیت بحرانی است.', 0, $rollbackError);
                }
                throw new RuntimeException('بازگردانی SQL شکست خورد؛ پایگاه داده قبلی بازیابی شد.', 0, $error);
            }
            return ['type' => 'sql', 'generated_at' => '', 'source_app_version' => 'legacy', 'current_app_version' => app_version(), 'file_count' => 0, 'database_restored' => true, 'snapshot' => basename($snapshot)];
        } finally {
            self::releaseRestoreLock();
        }
    }

    private static function createSafetySnapshot(): string
    {
        self::requireZip();
        $dir = dirname(__DIR__, 2) . '/storage/restore-snapshots';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('ساخت پوشه Snapshot پیش از Restore ناموفق بود.');
        }
        $path = $dir . '/pre-restore-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        try {
            self::createFullBackup($path);
            self::validateFullBackup($path);
        } catch (Throwable $error) {
            @unlink($path);
            throw $error;
        }
        return $path;
    }

    private static function extractVerified(string $archivePath, array $manifest, string $stage): void
    {
        if (!mkdir($stage, 0770, true) && !is_dir($stage)) {
            throw new RuntimeException('ساخت فضای موقت Restore ناموفق بود.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('باز کردن فایل ZIP ناموفق بود.');
        }
        try {
            foreach (array_merge([$manifest['database']], $manifest['files']) as $metadata) {
                $path = (string) $metadata['path'];
                $target = $stage . '/' . $path;
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0770, true) && !is_dir($parent)) {
                    throw new RuntimeException('ساخت مسیر موقت Restore ناموفق بود.');
                }
                $input = $zip->getStream($path);
                $output = fopen($target, 'wb');
                if (!is_resource($input) || !is_resource($output)) {
                    throw new RuntimeException('استخراج امن فایل بکاپ ناموفق بود.');
                }
                stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
                self::verifyFileMetadata($target, $metadata);
            }
            foreach ($manifest['roots'] as $root) {
                $dir = $stage . '/' . $root['prefix'];
                if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                    throw new RuntimeException('ساخت Root خالی در فضای Restore ناموفق بود.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function promoteRoots(string $stage, string $oldBase, array $manifest, ?array $rootMap = null): array
    {
        $rootMap ??= self::managedRoots();
        if (!is_dir($oldBase) && !mkdir($oldBase, 0770, true) && !is_dir($oldBase)) {
            throw new RuntimeException('ساخت فضای Rollback فایل‌ها ناموفق بود.');
        }
        $swapped = [];
        try {
            foreach ($manifest['roots'] as $rootInfo) {
                $key = (string) $rootInfo['key'];
                if (!isset($rootMap[$key]) || $rootMap[$key]['prefix'] !== $rootInfo['prefix']) {
                    throw new RuntimeException('Mapping مسیرهای بکاپ با سامانه فعلی سازگار نیست.');
                }
                $live = rtrim($rootMap[$key]['path'], '/\\');
                $staged = $stage . '/' . $rootInfo['prefix'];
                $old = $oldBase . '/' . $key;
                $parent = dirname($live);
                if (!is_dir($parent) && !mkdir($parent, 0770, true) && !is_dir($parent)) {
                    throw new RuntimeException('مسیر مقصد Restore قابل ساخت نیست.');
                }
                $hadLive = file_exists($live);
                if ($hadLive && !@rename($live, $old)) {
                    throw new RuntimeException('انتقال Root فعلی برای Rollback ناموفق بود: ' . $key);
                }
                $swapped[] = ['live' => $live, 'old' => $old, 'had_live' => $hadLive];
                if (!@rename($staged, $live)) {
                    throw new RuntimeException('جایگزینی Root فایل ناموفق بود: ' . $key);
                }
            }
        } catch (Throwable $error) {
            self::rollbackRoots($swapped);
            throw $error;
        }
        return $swapped;
    }

    private static function rollbackRoots(array $swapped): void
    {
        foreach (array_reverse($swapped) as $root) {
            if (file_exists($root['live'])) {
                self::removeTree($root['live']);
            }
            if ($root['had_live'] && !@rename($root['old'], $root['live'])) {
                throw new RuntimeException('Rollback فایل‌ها ناموفق بود.');
            }
        }
    }

    private static function verifyLiveFiles(array $manifest, ?array $rootMap = null): void
    {
        $roots = [];
        foreach ($rootMap ?? self::managedRoots() as $root) {
            $roots[$root['prefix']] = rtrim($root['path'], '/\\');
        }
        foreach ($manifest['files'] as $metadata) {
            $matched = false;
            foreach ($roots as $prefix => $physical) {
                if (str_starts_with($metadata['path'], $prefix . '/')) {
                    $relative = substr($metadata['path'], strlen($prefix) + 1);
                    self::verifyFileMetadata($physical . '/' . $relative, $metadata);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                throw new RuntimeException('مسیر فایل manifest قابل نگاشت نیست.');
            }
        }
    }

    private static function restoreDatabaseFromSnapshot(string $snapshot): void
    {
        $manifest = self::validateFullBackup($snapshot);
        $workspace = self::makeWorkspace('rollback');
        try {
            self::extractVerified($snapshot, $manifest, $workspace);
            self::executeSqlFile($workspace . '/database.sql');
            self::verifyDatabase($workspace . '/database.sql');
        } finally {
            self::removeTree($workspace);
        }
    }

    private static function validateManifestShape(array $manifest): void
    {
        if (($manifest['format'] ?? '') !== self::FORMAT || (int) ($manifest['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            throw new RuntimeException('فرمت یا نسخه manifest این بکاپ پشتیبانی نمی‌شود.');
        }
        if (!is_string($manifest['generated_at'] ?? null) || !is_string($manifest['app_version'] ?? null)) {
            throw new RuntimeException('اطلاعات نسخه یا زمان تولید manifest معتبر نیست.');
        }
        if (!is_array($manifest['database'] ?? null) || ($manifest['database']['path'] ?? '') !== 'database.sql') {
            throw new RuntimeException('اطلاعات database.sql در manifest معتبر نیست.');
        }
        if (!is_array($manifest['roots'] ?? null) || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('لیست Rootها یا فایل‌های manifest معتبر نیست.');
        }
        $allowedRoots = [];
        foreach (self::managedRoots() as $key => $root) {
            $allowedRoots[(string) $key] = $root['prefix'];
        }
        $seenRoots = [];
        foreach ($manifest['roots'] as $root) {
            $key = (string) ($root['key'] ?? '');
            $prefix = (string) ($root['prefix'] ?? '');
            if (!isset($allowedRoots[$key]) || $allowedRoots[$key] !== $prefix || isset($seenRoots[$key]) || !is_bool($root['exists'] ?? null)) {
                throw new RuntimeException('Root ناشناخته یا تکراری در manifest وجود دارد.');
            }
            $seenRoots[$key] = true;
        }
        if (count($seenRoots) !== count($allowedRoots)) {
            throw new RuntimeException('همه Rootهای مدیریت‌شده در manifest تعریف نشده‌اند.');
        }
        $seenFiles = [];
        foreach (array_merge([$manifest['database']], $manifest['files']) as $metadata) {
            if (!is_array($metadata) || !isset($metadata['path'], $metadata['size'], $metadata['sha256'])) {
                throw new RuntimeException('Metadata یکی از فایل‌های manifest ناقص است.');
            }
            if ((!is_int($metadata['size']) && !ctype_digit((string) $metadata['size'])) || (int) $metadata['size'] < 0
                || !is_string($metadata['sha256']) || !preg_match('/^[a-f0-9]{64}$/i', $metadata['sha256'])) {
                throw new RuntimeException('اندازه یا SHA-256 یکی از فایل‌های manifest معتبر نیست.');
            }
            self::assertArchivePath((string) $metadata['path']);
            $path = (string) $metadata['path'];
            if (isset($seenFiles[$path])) {
                throw new RuntimeException('مسیر تکراری در manifest وجود دارد.');
            }
            $seenFiles[$path] = true;
        }
    }

    private static function verifyZipEntry(ZipArchive $zip, array $metadata): void
    {
        $name = (string) $metadata['path'];
        $stat = $zip->statName($name);
        if ($stat === false || (int) $stat['size'] !== (int) $metadata['size']) {
            throw new RuntimeException('اندازه فایل بکاپ با manifest تطابق ندارد: ' . $name);
        }
        $stream = $zip->getStream($name);
        if (!is_resource($stream)) {
            throw new RuntimeException('خواندن فایل داخل ZIP ناموفق بود: ' . $name);
        }
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);
        if (!hash_equals(strtolower((string) $metadata['sha256']), hash_final($hash))) {
            throw new RuntimeException('Hash فایل بکاپ با manifest تطابق ندارد: ' . $name);
        }
    }

    private static function assertArchivePath(string $path): void
    {
        if ($path === '' || preg_match('/[\x00-\x1F\x7F:]/', $path) || str_contains($path, '\\') || str_contains($path, '//')
            || str_starts_with($path, '/') || preg_match('~(^|/)\.{1,2}(/|$)~', $path)) {
            throw new RuntimeException('مسیر ناامن در فایل ZIP شناسایی شد.');
        }
        if (in_array($path, ['manifest.json', 'database.sql'], true)) {
            return;
        }
        foreach (self::managedRoots() as $root) {
            if (str_starts_with($path, $root['prefix'] . '/')) {
                return;
            }
        }
        throw new RuntimeException('مسیر خارج از محدوده مجاز در فایل ZIP وجود دارد.');
    }

    private static function filesInRoot(string $root): array
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('وجود symbolic link در مسیرهای داده مانع تهیه بکاپ شد: ' . $item->getPathname());
            }
            if ($item->isFile()) {
                $absolute = $item->getPathname();
                $basename = strtolower($item->getBasename());
                $extension = strtolower($item->getExtension());
                if (in_array($basename, ['.htaccess', '.gitkeep'], true) || in_array($extension, ['php', 'phtml', 'phar'], true)) {
                    continue;
                }
                $files[] = ['absolute' => $absolute, 'relative' => str_replace('\\', '/', substr($absolute, strlen($rootReal) + 1))];
            }
        }
        usort($files, static fn(array $a, array $b): int => strcmp($a['relative'], $b['relative']));
        return $files;
    }

    private static function fileMetadata(string $path, string $logical): array
    {
        return ['path' => $logical, 'size' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    }

    private static function verifyFileMetadata(string $path, array $metadata): void
    {
        if (!is_file($path) || filesize($path) !== (int) $metadata['size'] || !hash_equals(strtolower((string) $metadata['sha256']), hash_file('sha256', $path))) {
            throw new RuntimeException('فایل استخراج‌شده با manifest تطابق ندارد: ' . $metadata['path']);
        }
    }

    private static function zipEntryIsSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        return $zip->getExternalAttributesIndex($index, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000;
    }

    private static function manifestTotalSize(array $manifest): int
    {
        return (int) $manifest['database']['size'] + array_sum(array_map(static fn(array $file): int => (int) $file['size'], $manifest['files']));
    }

    private static function assertDiskSpace(string $path, int $required): void
    {
        $free = @disk_free_space($path);
        if (is_float($free) && $free < max($required, 50 * 1024 * 1024)) {
            throw new RuntimeException('فضای دیسک برای Snapshot و Restore کافی نیست.');
        }
    }

    private static function restoreLockPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/restore.lock';
    }

    private static function acquireRestoreLock(): void
    {
        if (self::isRestoreLocked()) {
            throw new RuntimeException('یک عملیات Restore دیگر در حال اجرا است.');
        }
        $handle = @fopen(self::restoreLockPath(), 'x');
        if (!is_resource($handle)) {
            throw new RuntimeException('ایجاد قفل Restore ناموفق بود.');
        }
        fwrite($handle, (string) time());
        fclose($handle);
    }

    private static function releaseRestoreLock(): void
    {
        @unlink(self::restoreLockPath());
    }

    private static function makeWorkspace(string $prefix): string
    {
        $base = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($base) && !mkdir($base, 0770, true) && !is_dir($base)) {
            throw new RuntimeException('ساخت فضای موقت غیرعمومی ناموفق بود.');
        }
        $path = $base . '/' . $prefix . '-' . bin2hex(random_bytes(12));
        if (!mkdir($path, 0770, true)) {
            throw new RuntimeException('ساخت Workspace موقت ناموفق بود.');
        }
        return $path;
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (new FilesystemIterator($path) as $item) {
            self::removeTree($item->getPathname());
        }
        @rmdir($path);
    }

    private static function requireZip(): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZIP روی سرور فعال نیست؛ بکاپ SQL همچنان قابل استفاده است.');
        }
    }

    private static function dump(): string
    {
        $stream = fopen('php://temp/maxmemory:5242880', 'w+');
        self::writeDatabaseDumpToStream($stream);
        rewind($stream);
        return (string) stream_get_contents($stream);
    }

    private static function writeDatabaseDump(string $path): void
    {
        $stream = fopen($path, 'wb');
        if (!is_resource($stream)) {
            throw new RuntimeException('ساخت database.sql ناموفق بود.');
        }
        try {
            self::writeDatabaseDumpToStream($stream);
        } finally {
            fclose($stream);
        }
    }

    private static function writeDatabaseDumpToStream($stream): void
    {
        $pdo = db();
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        fwrite($stream, "-- Elm Simple CRM Backup\n-- Generated at " . date('c') . "\n-- Database: `{$database}`\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        while ($row = $tables->fetch(PDO::FETCH_NUM)) {
            $table = (string) $row[0];
            $quotedTable = str_replace('`', '``', $table);
            $create = $pdo->query("SHOW CREATE TABLE `{$quotedTable}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = (string) ($create['Create Table'] ?? array_values($create)[1] ?? '');
            fwrite($stream, "DROP TABLE IF EXISTS `{$quotedTable}`;\n{$createSql};\n\n");
            $records = $pdo->query("SELECT * FROM `{$quotedTable}`");
            while ($record = $records->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map(static fn($column): string => '`' . str_replace('`', '``', (string) $column) . '`', array_keys($record));
                $values = array_map(static fn($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($record));
                fwrite($stream, "INSERT INTO `{$quotedTable}` (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
            }
            fwrite($stream, "\n");
        }
        fwrite($stream, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private static function assertLegacySql(string $path): void
    {
        $handle = fopen($path, 'rb');
        $prefix = is_resource($handle) ? fread($handle, 4096) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (!is_string($prefix) || stripos($prefix, 'Elm Simple CRM Backup') === false) {
            throw new RuntimeException('این فایل SQL بکاپ معتبر Elm Simple CRM نیست.');
        }
    }

    private static function executeSqlFile(string $path): void
    {
        self::assertLegacySql($path);
        self::executeScript((string) file_get_contents($path));
    }

    private static function executeScript(string $sql): void
    {
        $statement = '';
        foreach (preg_split('/\r\n|\r|\n/u', $sql) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $statement .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                db()->exec($statement);
                $statement = '';
            }
        }
        if (trim($statement) !== '') {
            db()->exec($statement);
        }
    }

    private static function verifyDatabase(string $sqlPath): void
    {
        foreach (['app_settings', 'users', 'customers', 'contracts', 'tickets'] as $table) {
            $quoted = str_replace('`', '``', $table);
            db()->query("SELECT 1 FROM `{$quoted}` LIMIT 1");
        }
        if (self::sqlDefinesTable($sqlPath, 'contract_documents')) {
            db()->query('SELECT 1 FROM `contract_documents` LIMIT 1');
        }
    }

    private static function sqlDefinesTable(string $path, string $table): bool
    {
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            return false;
        }
        try {
            while (($line = fgets($handle)) !== false) {
                if (stripos($line, 'CREATE TABLE') !== false && stripos($line, '`' . $table . '`') !== false) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }
        return false;
    }
}
