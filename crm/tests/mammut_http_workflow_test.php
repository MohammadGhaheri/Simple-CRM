<?php

declare(strict_types=1);

/**
 * Full HTTP smoke test against the real CRM front controller and a disposable
 * MariaDB database. Not a mock router test. Does not use production credentials.
 *
 * The script refuses to overwrite a local database.php and creates test-only
 * session files, user records, private receipt evidence and a PHP web server.
 */
$root = dirname(__DIR__);
$configPath = $root . '/app/config/database.php';
if (file_exists($configPath)) {
    throw new RuntimeException('Refusing to run HTTP test with existing local CRM database.php');
}
$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_TEST_PORT') ?: 3306);
$username = getenv('MYSQL_TEST_USER') ?: 'root';
$password = (string) (getenv('MYSQL_TEST_PASSWORD') ?: '');
$serverPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$dbName = 'crm_mammut_http_' . bin2hex(random_bytes(5));
$serverPdo->exec("CREATE DATABASE {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$webProcess = null;
$sessionFiles = [];
$evidenceFile = null;
$logPath = tempnam(sys_get_temp_dir(), 'crm-http-test-');

function http_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('HTTP integration test: ' . $message);
    }
}

function http_request(int $port, string $sessionId, string $path, string $method = 'GET', array $post = []): array
{
    $headers = "Cookie: PHPSESSID={$sessionId}\r\nConnection: close\r\n";
    $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 8];
    if ($method === 'POST') {
        $options['content'] = http_build_query($post);
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $headers .= 'Content-Length: ' . strlen($options['content']) . "\r\n";
    }
    $options['header'] = $headers;
    $context = stream_context_create(['http' => $options]);
    $result = @file_get_contents("http://127.0.0.1:{$port}/index.php?{$path}", false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusLine = (string) ($responseHeaders[0] ?? '');
    if (!preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/', $statusLine, $matches)) {
        throw new RuntimeException('HTTP integration test received no HTTP status: ' . $path);
    }
    return [(int) $matches[1], $result === false ? '' : $result];
}

function http_seed_session(string $sessionDir, int $id, string $role): array
{
    $sid = bin2hex(random_bytes(16));
    $csrf = bin2hex(random_bytes(32));
    session_save_path($sessionDir);
    session_id($sid);
    if (!session_start()) {
        throw new RuntimeException('Test session could not be started.');
    }
    $_SESSION['user'] = [
        'id' => $id, 'name' => 'HTTP Test User ' . $id,
        'email' => 'http-' . $id . '@example.test', 'role' => $role, 'avatar_path' => '',
    ];
    $_SESSION['csrf_token'] = $csrf;
    session_write_close();
    return [$sid, $csrf];
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql = (string) file_get_contents($root . '/database/schema.sql');
    $sql = preg_replace('/^\h*--[^\r\n]*(?:\r?\n|\z)/m', '', $sql);
    $sql = preg_replace('/^\h*CREATE DATABASE IF NOT EXISTS simple_crm[^;]*;\h*(?:\r?\n|\z)/mi', '', $sql);
    $sql = preg_replace('/^\h*USE\h+simple_crm\h*;\h*(?:\r?\n|\z)/mi', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }

    $pdo->exec("INSERT INTO users (id,name,email,password_hash,role,is_active) VALUES
        (1,'Admin','admin-http@example.test','TEST','admin',1),
        (2,'Sales Owner','owner-http@example.test','TEST','sales',1),
        (3,'Operations','ops-http@example.test','TEST','operations',1),
        (4,'Finance','finance-http@example.test','TEST','finance',1),
        (5,'Support','support-http@example.test','TEST','support',1),
        (6,'Other Sales','other-http@example.test','TEST','sales',1)");
    $pdo->exec("INSERT INTO customers (id,customer_code,customer_name,owner_user_id) VALUES
        (1,'HTTP-C1','HTTP Owned Customer',2),
        (2,'HTTP-C2','HTTP Other Customer',6)");
    $pdo->exec("INSERT INTO contracts
        (id,contract_type,contract_number,contract_title,customer_id,contract_amount,owner_user_id,status)
        VALUES
        (1,'direct_sale','HTTP-1','HTTP Sale A',1,9876543.21,2,'Active'),
        (2,'direct_sale','HTTP-2','HTTP Sale B',2,8765432.10,6,'Active')");
    $pdo->exec("INSERT INTO contract_items
        (id,contract_id,line_number,item_type,product,item_title,quantity,unit_amount,line_amount,requires_installation)
        VALUES
        (1,1,1,'device','IoTruck','HTTP IoTruck',2,1000.00,2000.00,1),
        (2,2,1,'device','IoTrailer','HTTP IoTrailer',1,1000.00,1000.00,1)");

    file_put_contents($configPath, '<?php return ' . var_export([
        'host' => $host, 'port' => $port, 'dbname' => $dbName,
        'username' => $username, 'password' => $password, 'charset' => 'utf8mb4',
    ], true) . ';');

    $sessionDir = $root . '/storage/sessions';
    if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
        throw new RuntimeException('Cannot create test session directory.');
    }
    $sessions = [];
    foreach (['admin' => 1, 'sales' => 2, 'operations' => 3, 'finance' => 4, 'support' => 5, 'other' => 6] as $role => $userId) {
        [$sid, $csrf] = http_seed_session($sessionDir, $userId, $role === 'other' ? 'sales' : $role);
        $sessions[$role] = ['id' => $sid, 'csrf' => $csrf];
        $sessionFiles[] = $sessionDir . '/sess_' . $sid;
    }

    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$listener) {
        throw new RuntimeException("Cannot allocate local HTTP port: {$errstr}");
    }
    $socketName = stream_socket_get_name($listener, false);
    fclose($listener);
    $httpPort = (int) substr((string) $socketName, strrpos((string) $socketName, ':') + 1);
    $command = [PHP_BINARY, '-d', 'display_errors=0', '-S', '127.0.0.1:' . $httpPort, '-t', $root . '/public'];
    $descriptor = [0 => ['pipe', 'r'], 1 => ['file', $logPath, 'a'], 2 => ['file', $logPath, 'a']];
    $webProcess = proc_open($command, $descriptor, $pipes, $root);
    http_expect(is_resource($webProcess), 'PHP built-in server could not start.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 35; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $httpPort, $code, $error, 0.2);
        if ($connection) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    http_expect($ready, 'Local PHP HTTP server did not become ready.');

    $get = static fn(string $role, string $query): array =>
        http_request($httpPort, $sessions[$role]['id'], $query);
    $post = static fn(string $role, string $query, array $fields): array =>
        http_request($httpPort, $sessions[$role]['id'], $query, 'POST',
            ['csrf_token' => $sessions[$role]['csrf']] + $fields);

    [$status, $html] = $get('operations', 'page=installations');
    http_expect($status === 200, 'Operations cannot open installation queue: ' . $status);
    http_expect(str_contains($html, 'HTTP Owned Customer') && str_contains($html, 'HTTP Other Customer'),
        'Operations queue does not show its two allowed contracts.');
    http_expect(!str_contains($html, '9876543.21') && !str_contains($html, '8765432.10'),
        'Operations HTML leaked a contract amount.');

    [$status, $html] = $get('sales', 'page=installations');
    http_expect($status === 200 && str_contains($html, 'HTTP Owned Customer')
        && !str_contains($html, 'HTTP Other Customer'), 'Sales queue leaked other seller contracts.');
    [$status, $html] = $get('other', 'page=installations');
    http_expect($status === 200 && !str_contains($html, 'HTTP Owned Customer'),
        'Different Sales user can see another owner obligation.');
    [$status] = $get('other', 'page=installations&action=show&id=1');
    http_expect($status === 404, 'Different Sales user can access another owner detail URL.');
    [$status, $html] = $get('finance', 'page=installations&action=show&id=1');
    http_expect($status === 200 && !str_contains($html, 'name="imei"'),
        'Finance should see a read-only installation view.');
    [$status, $html] = $get('support', 'page=installations');
    http_expect($status === 200 && !str_contains($html, 'HTTP Owned Customer'),
        'Support accessed an un-delivered installation.');

    $createRoute = 'page=installations&action=record_create&id=1';
    [$status] = $post('sales', $createRoute, [
        'contract_item_id' => '1', 'imei' => 'HTTP-ATTACK',
    ]);
    http_expect($status === 403, 'Sales can create installations by forged POST.');
    [$status] = $post('finance', $createRoute, [
        'contract_item_id' => '1', 'imei' => 'HTTP-FINANCE',
    ]);
    http_expect($status === 403, 'Finance can create installations by forged POST.');
    [$status] = http_request($httpPort, $sessions['operations']['id'], $createRoute,
        'POST', ['csrf_token' => 'wrong', 'contract_item_id' => '1', 'imei' => 'HTTP-CSRF']);
    http_expect($status === 419, 'Invalid CSRF did not block installation create.');
    http_expect((int) $pdo->query('SELECT COUNT(*) FROM installation_records')->fetchColumn() === 0,
        'Unauthorized or CSRF-rejected request still mutated DB.');

    [$status] = $post('operations', $createRoute, [
        'contract_item_id' => '1', 'imei' => 'HTTP-VALID-DEVICE',
        'device_model' => 'IoTruck 4G', 'status' => 'scheduled',
    ]);
    http_expect($status === 302, 'Authorized Operations installation create failed: ' . $status);
    $installationId = (int) $pdo->query('SELECT id FROM installation_records')->fetchColumn();
    http_expect($installationId > 0, 'Authorized installation was not stored.');

    foreach (['in_progress', 'installed', 'pending_approval', 'approved'] as $nextStatus) {
        [$status] = $post('operations', 'page=installations&action=transition&id=1', [
            'contract_item_id' => '1', 'installation_id' => (string) $installationId,
            'to_status' => $nextStatus, 'reason' => 'HTTP workflow test',
        ]);
        http_expect($status === 302, 'HTTP installation transition failed for ' . $nextStatus);
        $actual = (string) $pdo->query("SELECT status FROM installation_records WHERE id={$installationId}")->fetchColumn();
        http_expect($actual === $nextStatus, 'HTTP transition did not persist ' . $nextStatus);
    }

    $deliveryRoute = 'page=installations&action=delivery_create&id=1';
    [$status] = $post('sales', $deliveryRoute, [
        'installation_ids' => [(string) $installationId],
        'receipt_number' => 'HTTP-UNAUTHORIZED', 'receiver_name' => 'Test',
        'delivered_at' => '2026-11-08T10:00',
    ]);
    http_expect($status === 403, 'Sales can submit handover by forged POST.');
    [$status] = $post('operations', $deliveryRoute, [
        'contract_item_id' => '1',
        'installation_ids' => [(string) $installationId],
        'receipt_number' => 'HTTP-R-1', 'receiver_name' => 'Test Receiver',
        'delivered_at' => '2026-11-08T10:00',
        'evidence_path' => 'attacker/private-other-document.txt',
        'evidence_name' => 'forged.txt',
    ]);
    http_expect($status === 302, 'Authorized handover POST did not redirect: ' . $status);
    $receipt = $pdo->query('SELECT * FROM delivery_receipts ORDER BY id DESC LIMIT 1')->fetch();
    http_expect($receipt && $receipt['evidence_path'] === null && $receipt['evidence_name'] === null,
        'Attacker-provided evidence path was persisted.');
    [$status, $html] = $get('support', 'page=installations');
    http_expect($status === 200 && str_contains($html, 'HTTP Owned Customer'),
        'Support cannot see successfully handed-over device.');
    [$status, $html] = $get('support', 'page=installations&action=show&id=1');
    http_expect($status === 200 && str_contains($html, 'HTTP-R-1'),
        'Support cannot see an active delivered receipt.');
    [$status] = $get('other', 'page=installations&action=evidence_download&receipt_id=' . $receipt['id']);
    http_expect($status === 404, 'Foreign Sales read private receipt endpoint.');

    $relativeEvidence = 'http-smoke-' . bin2hex(random_bytes(5)) . '.txt';
    $evidenceDir = $root . '/storage/contract-documents';
    if (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0700, true) && !is_dir($evidenceDir)) {
        throw new RuntimeException('Could not create private test evidence directory.');
    }
    $evidenceFile = $evidenceDir . '/' . $relativeEvidence;
    $evidenceContent = 'TEST PRIVATE RECEIPT ' . bin2hex(random_bytes(12));
    file_put_contents($evidenceFile, $evidenceContent);
    $pdo->prepare('UPDATE delivery_receipts SET evidence_path = ?, evidence_name = ? WHERE id = ?')
        ->execute([$relativeEvidence, 'test-receipt.txt', $receipt['id']]);

    [$status, $body] = $get('support', 'page=installations&action=evidence_download&receipt_id=' . $receipt['id']);
    http_expect($status === 200 && $body === $evidenceContent,
        'Authorized Support could not download its precise private receipt.');
    [$status] = $get('other', 'page=installations&action=evidence_download&receipt_id=' . $receipt['id']);
    http_expect($status === 404, 'Foreign Sales downloaded another customer receipt.');

    [$status] = $post('operations', 'page=installations&action=delivery_cancel&id=1', [
        'contract_item_id' => '1', 'receipt_id' => (string) $receipt['id'],
        'reason' => 'HTTP controlled cancellation',
    ]);
    http_expect($status === 302, 'Authorized Operations receipt cancellation failed.');
    http_expect((string) $pdo->query('SELECT status FROM delivery_receipts WHERE id=' . (int) $receipt['id'])->fetchColumn() === 'cancelled',
        'Receipt cancellation was not persisted.');
    [$status] = $get('support', 'page=installations&action=evidence_download&receipt_id=' . $receipt['id']);
    http_expect($status === 404, 'Support downloaded cancelled receipt evidence.');
    [$status, $body] = $get('admin', 'page=installations&action=evidence_download&receipt_id=' . $receipt['id']);
    http_expect($status === 200 && $body === $evidenceContent,
        'Admin cannot inspect cancelled receipt audit evidence.');

    echo "Mammut full HTTP workflow and role/CSRF/evidence tests passed.\n";
} catch (Throwable $error) {
    if (is_file($logPath)) {
        fwrite(STDERR, "Test-only PHP server log (tail):\n" . substr((string) file_get_contents($logPath), -5500) . "\n");
    }
    throw $error;
} finally {
    if (is_resource($webProcess)) {
        proc_terminate($webProcess);
        proc_close($webProcess);
    }
    foreach ($sessionFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if ($evidenceFile && is_file($evidenceFile)) {
        unlink($evidenceFile);
    }
    if (is_file($configPath)) {
        unlink($configPath);
    }
    if (is_file($logPath)) {
        unlink($logPath);
    }
    $serverPdo->exec("DROP DATABASE IF EXISTS {$dbName}");
}
