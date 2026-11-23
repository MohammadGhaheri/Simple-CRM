<?php
declare(strict_types=1);

// Standalone machine-to-machine endpoint: no session bootstrap, browser auth or CSRF bypass.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error'=>'method_not_allowed']);
}
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
if (!$isHttps) respond(403, ['error'=>'https_required']);
if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    respond(415, ['error'=>'unsupported_media_type']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) respond(413, ['error'=>'payload_too_large']);
$body = file_get_contents('php://input', false, null, 0, 16385);
if ($body === false || strlen($body) > 16384) respond(413, ['error'=>'payload_too_large']);

require __DIR__ . '/../../../../app/core/helpers.php';
require __DIR__ . '/../../../../app/models/Setting.php';
require __DIR__ . '/../../../../app/models/WebsiteLead.php';
$secret = (string) getenv('MAMMUT_WEBSITE_HMAC_SECRET');
if ($secret === '') {
    // Private server-only configuration: never place secrets in Git, logs or public_html.
    $privateConfig = __DIR__ . '/../../../../app/config/website-leads-hmac.php';
    if (is_file($privateConfig) && !is_link($privateConfig)) {
        $secret = (string) require $privateConfig;
    }
}
$ts = (string) ($_SERVER['HTTP_X_MC_TIMESTAMP'] ?? '');
$nonce = (string) ($_SERVER['HTTP_X_MC_NONCE'] ?? '');
$sig = (string) ($_SERVER['HTTP_X_MC_SIGNATURE'] ?? '');
if ($secret === '') respond(503, ['error'=>'integration_not_configured']);
if (!WebsiteLead::authenticate($ts, $nonce, $sig, $body, $secret, time())) {
    respond(401, ['error'=>'invalid_signature_or_timestamp']);
}
try {
    $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || array_is_list($data)) respond(422, ['error'=>'invalid_payload']);
    $valid = WebsiteLead::validate($data);
} catch (JsonException | InvalidArgumentException $e) {
    respond(422, ['error'=>'invalid_payload', 'detail'=>$e->getMessage()]);
}

try {
    if (!WebsiteLead::gateRequest(db(), $nonce, 'mammutconnect.com')) {
        respond(409, ['error'=>'nonce_replayed']);
    }
    $result = WebsiteLead::receive(db(), $valid, $body);
    respond($result['duplicate'] ? 200 : 201, [
        'status' => $result['duplicate'] ? 'duplicate' : 'created',
        'lead_id' => $result['lead_id'],
        'request_id' => $valid['request_id'],
    ]);
} catch (DomainException $e) {
    $status = in_array($e->getCode(), [409,429], true) ? $e->getCode() : 409;
    respond($status, ['error'=> $status === 429 ? 'rate_limited' : 'request_id_conflict']);
} catch (Throwable $e) {
    error_log('website_leads_ingest_failed type=' . get_class($e));
    respond(503, ['error'=>'temporarily_unavailable']);
}
