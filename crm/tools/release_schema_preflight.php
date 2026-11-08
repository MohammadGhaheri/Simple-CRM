<?php

declare(strict_types=1);

/**
 * Read-only release schema inventory.
 * Usage:
 * php crm/tools/release_schema_preflight.php --config=/absolute/path/database.copy.php
 *
 * Supply only a dedicated READ-ONLY account for a COPY of the deployed DB.
 * This tool never executes DDL/DML, prints no credentials or customer rows,
 * and deliberately never marks a release as production-ready.
 */
require __DIR__ . '/../app/services/ReleaseSchemaPreflight.php';

$options = getopt('', ['config:']);
$configPath = $options['config'] ?? null;
if (!is_string($configPath) || $configPath === '' || !is_file($configPath)) {
    fwrite(STDERR, "Usage: php crm/tools/release_schema_preflight.php --config=/absolute/path/database.copy.php\n");
    exit(2);
}
$config = require $configPath;
if (!is_array($config) || !isset($config['host'], $config['dbname'], $config['username'], $config['password'])) {
    fwrite(STDERR, "Invalid database configuration (no credentials will be displayed).\n");
    exit(2);
}
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            (int) ($config['port'] ?? 3306),
            $config['dbname']
        ),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $report = ReleaseSchemaPreflight::inspect($pdo);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit($report['state'] === 'manual_review_required' ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, "Read-only schema preflight failed; inspect the connection and permissions.\n");
    exit(2);
}
