<?php

declare(strict_types=1);

$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_TEST_PORT') ?: 3306);
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = (string) (getenv('MYSQL_TEST_PASSWORD') ?: '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database = 'crm_full_schema_' . bin2hex(random_bytes(5));
$server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $schema = (string) file_get_contents(__DIR__ . '/../database/schema.sql');
    $schema = preg_replace('/^\h*--[^\r\n]*(?:\r?\n|\z)/m', '', $schema);
    // The distributable installer schema names simple_crm; tests must remain in
    // their random isolated database and must never switch to a persistent DB.
    $schema = preg_replace('/^\h*CREATE DATABASE IF NOT EXISTS simple_crm[^;]*;\h*(?:\r?\n|\z)/mi', '', $schema);
    $schema = preg_replace('/^\h*USE\h+simple_crm\h*;\h*(?:\r?\n|\z)/mi', '', $schema);
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        $pdo->exec($statement);
    }

    foreach (['contracts', 'contract_items', 'contract_item_amendments', 'contract_item_change_events',
        'contract_invoice_refs', 'contract_invoice_lines', 'contract_invoice_ref_events'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
        $stmt->execute([$database, $table]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException("Full schema is missing {$table}.");
        }
    }

    $pdo->exec("INSERT INTO users (id,name,email,password_hash,role) VALUES (1,'Legacy Owner','legacy@example.test','test','sales')");
    $pdo->exec("INSERT INTO customers (id,customer_code,customer_name,owner_user_id) VALUES (1,'LEGACY-1','Legacy customer',1)");
    $pdo->exec("INSERT INTO contracts
        (id,contract_type,contract_number,contract_title,customer_id,product,vehicle_count,contract_amount,owner_user_id,status)
        VALUES (1,'formal','OLD-1','Legacy contract',1,'Other',2,500.00,1,'Active')");
    if ((int) $pdo->query('SELECT COUNT(*) FROM contract_items WHERE contract_id = 1')->fetchColumn() !== 0) {
        throw new RuntimeException('Fresh schema inferred item rows for a legacy contract.');
    }
    if ((string) $pdo->query('SELECT contract_amount FROM contracts WHERE id = 1')->fetchColumn() !== '500.00') {
        throw new RuntimeException('Legacy contract amount changed unexpectedly.');
    }
    echo "Full CRM schema and legacy contract compatibility tests passed.\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
}
