<?php

declare(strict_types=1);

class BackupService
{
    public static function download(): never
    {
        $filename = 'elm-simple-crm-backup-' . date('Ymd-His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo self::dump();
        exit;
    }

    public static function restoreUploaded(array $file): void
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('فایل بکاپ انتخاب نشده است.');
        }
        if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
            throw new RuntimeException('حجم فایل بکاپ نباید بیشتر از ۲۰ مگابایت باشد.');
        }

        $sql = (string) file_get_contents($file['tmp_name']);
        if (stripos($sql, 'Elm Simple CRM Backup') === false) {
            throw new RuntimeException('این فایل شبیه بکاپ Elm Simple CRM نیست.');
        }

        self::executeScript($sql);
    }

    private static function dump(): string
    {
        $pdo = db();
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
        $out = "-- Elm Simple CRM Backup\n";
        $out .= "-- Generated at " . date('c') . "\n";
        $out .= "-- Database: `$database`\n\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $row) {
            $table = (string) $row[0];
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
            $createSql = (string) ($create['Create Table'] ?? array_values($create)[1] ?? '');
            $out .= "DROP TABLE IF EXISTS `$table`;\n";
            $out .= $createSql . ";\n\n";

            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $record) {
                $columns = array_map(fn($col) => '`' . str_replace('`', '``', (string) $col) . '`', array_keys($record));
                $values = array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($record));
                $out .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
            }
            $out .= "\n";
        }

        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
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
}
