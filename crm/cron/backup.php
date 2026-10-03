<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/services/BackupService.php';
require __DIR__ . '/../app/services/AutomatedBackupService.php';

try {
    $result = (new AutomatedBackupService())->run(Setting::all());
    fwrite(STDOUT, $result['message'] . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Automated backup failed.' . PHP_EOL);
    exit(1);
}
