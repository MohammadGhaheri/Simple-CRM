<?php

declare(strict_types=1);

require __DIR__ . '/../app/services/LoginRateLimiter.php';

function rate_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rate_remove_tree(string $path): void
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

function rate_state_files(string $path): array
{
    return glob($path . '/*.json') ?: [];
}

$base = sys_get_temp_dir() . '/simple-crm-rate-limit-' . bin2hex(random_bytes(6));
$now = 1700000000;
$clock = static function () use (&$now): int {
    return $now;
};
$limiter = new LoginRateLimiter($base . '/policy', $clock);
$email = ' User@Example.COM ';
$ip = '203.0.113.10';

rate_expect(LoginRateLimiter::normalizeIdentifier($email) === 'user@example.com', 'Identifiers must be trimmed and lowercased.');
$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.99';
rate_expect(LoginRateLimiter::clientIp() === '198.51.100.8', 'Rate limiting must use REMOTE_ADDR, not X-Forwarded-For.');
unset($_SERVER['REMOTE_ADDR']);
rate_expect(LoginRateLimiter::clientIp() === 'unknown', 'Missing REMOTE_ADDR must use the fixed fallback.');

for ($failure = 1; $failure <= 4; $failure++) {
    $result = $limiter->recordFailure('internal', $email, $ip);
    rate_expect($result['blocked'] === false, 'The first four pair failures must remain allowed.');
}
$fifth = $limiter->recordFailure('internal', $email, $ip);
rate_expect($fifth['blocked'] === true && $fifth['retry_after'] === LoginRateLimiter::PAIR_BLOCK_SECONDS, 'The fifth pair failure must start the pair block.');
rate_expect($limiter->check('internal', $email, $ip)['blocked'] === true, 'A pair must remain blocked before expiration.');
$now += LoginRateLimiter::PAIR_BLOCK_SECONDS + 1;
rate_expect($limiter->check('internal', $email, $ip)['blocked'] === false, 'An expired pair block must be released.');

$globalPath = $base . '/global';
$global = new LoginRateLimiter($globalPath, $clock);
$globalIp = '203.0.113.20';
for ($failure = 1; $failure <= LoginRateLimiter::IP_MAX_FAILURES; $failure++) {
    $globalResult = $global->recordFailure('internal', 'person-' . $failure . '@example.com', $globalIp);
}
rate_expect($globalResult['blocked'] === true && $globalResult['retry_after'] === LoginRateLimiter::IP_BLOCK_SECONDS, 'Twenty failures must start the global IP block.');
rate_expect($global->check('internal', 'new-person@example.com', $globalIp)['blocked'] === true, 'The global block must cover other identifiers in the same scope.');
rate_expect($global->check('internal', $email, '203.0.113.21')['blocked'] === false, 'A pair block from one IP must not block a second IP.');
rate_expect($global->check('portal', 'new-person@example.com', $globalIp)['blocked'] === false, 'Internal failures must not block the Portal scope.');
$global->recordFailure('portal', $email, '203.0.113.22');
rate_expect($global->check('internal', $email, '203.0.113.22')['blocked'] === false, 'Portal failures must not block the Internal scope.');

$successPath = $base . '/success';
$success = new LoginRateLimiter($successPath, $clock);
for ($failure = 0; $failure < 4; $failure++) {
    $success->recordFailure('internal', $email, $ip);
}
$success->recordSuccess('internal', $email, $ip);
rate_expect($success->recordFailure('internal', $email, $ip)['blocked'] === false, 'Success must reset only the matching pair counter.');

$historyPath = $base . '/history';
$history = new LoginRateLimiter($historyPath, $clock);
for ($failure = 1; $failure <= 19; $failure++) {
    $history->recordFailure('internal', 'spray-' . $failure . '@example.com', $ip);
}
$history->recordSuccess('internal', 'spray-1@example.com', $ip);
rate_expect($history->recordFailure('internal', 'spray-20@example.com', $ip)['blocked'] === true, 'Success must not clear global IP failure history.');

$storagePath = $base . '/storage';
$storage = new LoginRateLimiter($storagePath, $clock);
$storage->recordFailure('internal', 'Plain.Email@example.com', $ip);
$files = rate_state_files($storagePath);
rate_expect(count($files) === 1, 'One scope and IP must use exactly one state file.');
rate_expect(!str_contains(basename($files[0]), 'Plain.Email') && preg_match('/^[a-f0-9]{64}\.json$/', basename($files[0])) === 1, 'State filenames must be hashes.');
$stateJson = (string) file_get_contents($files[0]);
rate_expect(!str_contains($stateJson, 'Plain.Email') && !str_contains(strtolower($stateJson), 'plain.email'), 'Plain identifiers must not be stored in JSON.');
rate_expect(!str_contains($stateJson, 'password'), 'Rate-limit state must never contain passwords.');
file_put_contents($files[0], '{broken-json');
rate_expect($storage->check('internal', 'Plain.Email@example.com', $ip)['blocked'] === false, 'Malformed JSON must reset safely without a fatal error.');
rate_expect(is_array(json_decode((string) file_get_contents($files[0]), true)), 'Malformed state must be replaced by valid JSON.');

if (function_exists('proc_open')) {
    $concurrencyPath = $base . '/concurrency';
    $workerPath = $base . '/rate-worker.php';
    $servicePath = realpath(__DIR__ . '/../app/services/LoginRateLimiter.php');
    file_put_contents($workerPath, '<?php require ' . var_export($servicePath, true) . '; $limiter = new LoginRateLimiter($argv[1]); for ($i = 0; $i < 5; $i++) { $limiter->recordFailure("internal", "parallel@example.com", "203.0.113.90"); }');
    $workers = [];
    for ($worker = 0; $worker < 3; $worker++) {
        $process = proc_open([PHP_BINARY, $workerPath, $concurrencyPath], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        rate_expect(is_resource($process), 'Concurrent rate-limit worker must start.');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes[1], $pipes[2]];
    }
    foreach ($workers as [$process, $stdout, $stderr]) {
        $workerOutput = stream_get_contents($stdout) . stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        rate_expect(proc_close($process) === 0, 'Concurrent rate-limit worker failed: ' . $workerOutput);
    }
    $concurrentState = json_decode((string) file_get_contents(rate_state_files($concurrencyPath)[0]), true, 32, JSON_THROW_ON_ERROR);
    rate_expect(count($concurrentState['global_attempts']) === 15, 'Exclusive file locking must preserve concurrent failure updates.');
}

$prunePath = $base . '/prune';
$prune = new LoginRateLimiter($prunePath, $clock);
$prune->recordFailure('internal', $email, $ip);
$now += LoginRateLimiter::IP_WINDOW_SECONDS + 1;
$prune->check('internal', 'different@example.com', $ip);
$prunedState = json_decode((string) file_get_contents(rate_state_files($prunePath)[0]), true, 32, JSON_THROW_ON_ERROR);
rate_expect($prunedState['global_attempts'] === [] && $prunedState['identifiers'] === [], 'Attempts outside their windows must be pruned.');

$capPath = $base . '/cap';
$cap = new LoginRateLimiter($capPath, $clock);
for ($identifier = 0; $identifier < 75; $identifier++) {
    $cap->recordFailure('internal', 'random-' . $identifier . '@example.com', $ip);
}
$capFiles = rate_state_files($capPath);
$capState = json_decode((string) file_get_contents($capFiles[0]), true, 32, JSON_THROW_ON_ERROR);
rate_expect(count($capFiles) === 1, 'Random identifiers from one IP must not create multiple files.');
rate_expect(count($capState['identifiers']) <= LoginRateLimiter::MAX_IDENTIFIERS_PER_IP, 'Identifier state must respect the configured cap.');

try {
    $cap->check('arbitrary', $email, $ip);
    rate_expect(false, 'Arbitrary rate-limit scopes must be rejected.');
} catch (InvalidArgumentException) {
}
$outsideDirectory = $base . '/outside-rate-limits';
$symlinkPath = $base . '/symlink-rate-limits';
mkdir($outsideDirectory, 0700, true);
if (function_exists('symlink') && @symlink($outsideDirectory, $symlinkPath)) {
    try {
        (new LoginRateLimiter($symlinkPath, $clock))->check('internal', $email, $ip);
        rate_expect(false, 'A symlinked rate-limit directory must be rejected.');
    } catch (RuntimeException) {
    }
    unlink($symlinkPath);
}

$serviceSource = file_get_contents(__DIR__ . '/../app/services/LoginRateLimiter.php');
$loginSource = file_get_contents(__DIR__ . '/../public/login.php');
$portalSource = file_get_contents(__DIR__ . '/../public/portal.php');
rate_expect(str_contains($serviceSource, 'flock($handle, LOCK_EX)') && str_contains($serviceSource, 'fflush($handle)'), 'State updates must use an exclusive lock and flush writes.');
rate_expect(strpos($loginSource, "verify_csrf()") < strpos($loginSource, "->check('internal'"), 'CSRF verification must precede Internal rate-limit checks.');
rate_expect(strpos($loginSource, "->check('internal'") < strpos($loginSource, 'User::findByEmail'), 'Blocked Internal requests must stop before user lookup and password verification.');
rate_expect(strpos($portalSource, "verify_csrf()", strpos($portalSource, "if (\$action === 'login')")) < strpos($portalSource, "->check('portal'"), 'CSRF verification must precede Portal rate-limit checks.');
rate_expect(strpos($portalSource, "->check('portal'") < strpos($portalSource, 'Contact::findPortalByEmail'), 'Blocked Portal requests must stop before contact lookup and password verification.');
foreach ([$loginSource, $portalSource] as $source) {
    rate_expect(str_contains($source, 'http_response_code(429)') && str_contains($source, "header('Retry-After: '"), 'Blocked login pages must return HTTP 429 and Retry-After.');
    rate_expect(str_contains($source, 'تعداد تلاش‌های ورود بیش از حد مجاز است'), 'Blocked login pages must show the generic Persian message.');
}
rate_expect(strpos($loginSource, "recordSuccess('internal'") < strpos($loginSource, 'login_user($user)'), 'Internal pair state must reset before login succeeds.');
rate_expect(strpos($loginSource, 'login_user($user)') < strpos($loginSource, 'UsageReport::logLogin'), 'Internal successful login semantics must remain ordered.');
rate_expect(strpos($loginSource, 'UsageReport::logLogin') < strpos($loginSource, 'PerformanceAnalytics::startUserSession'), 'Internal analytics must start only after successful login logging.');
$portalLoginPosition = strpos($portalSource, "if (\$action === 'login')");
$portalRegeneratePosition = strpos($portalSource, 'session_regenerate_id(true)', $portalLoginPosition);
$portalSessionPosition = strpos($portalSource, "\$_SESSION['portal_contact']", $portalLoginPosition);
rate_expect(strpos($portalSource, "recordSuccess('portal'", $portalLoginPosition) < $portalRegeneratePosition, 'Portal pair state must reset before login succeeds.');
rate_expect($portalRegeneratePosition < $portalSessionPosition, 'Portal session regeneration and structure must remain intact.');
rate_expect(strpos($loginSource, 'BackupService::denyIfRestoreLocked()') < strpos($loginSource, "->check('internal'"), 'Restore lock must precede Internal rate-limit mutation.');
rate_expect(strpos($portalSource, 'BackupService::denyIfRestoreLocked()') < strpos($portalSource, "->check('portal'"), 'Restore lock must precede Portal rate-limit mutation.');

rate_remove_tree($base);
echo "Login rate limiter tests passed." . PHP_EOL;
