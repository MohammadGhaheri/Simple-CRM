<?php
declare(strict_types=1);
require __DIR__ . '/../app/models/WebsiteLead.php';
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException("TEST FAILED: $message");
}
function bad(callable $f): void {
    try { $f(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('TEST FAILED: invalid payload was accepted');
}
$p = [
 'request_id'=>'TEST-20261123-11111111',
 'source'=>'mammutconnect.com', 'lead_source_detail'=>'درخواست تست دمو',
 'submitted_at'=>'2026-11-23T10:30:00Z',
 'name'=>'تست توسعه ماموت کانکت',
 'mobile'=>'۰۹۱۲۳۴۵۶۷۸۹',
 'email'=>'test@example.org',
 'company'=>'شرکت آزمایشی TEST',
 'fleet_type'=>'truck', 'service'=>'ifms',
 'vehicle_count'=>20,'message'=>'درخواست نمایشی برای تست UTF-8',
 'consent'=>true,'page_url'=>'https://mammutconnect.com/contact-us-2/',
 'utm_source'=>'TEST', 'utm_medium'=>'unit', 'utm_campaign'=>'',
];
$v = WebsiteLead::validate($p);
check($v['mobile']==='09123456789', 'normalize Persian digits');
check($v['contact_name']==='تست توسعه ماموت کانکت', 'UTF-8 name');
check($v['service_code']==='ifms', 'service mapping');
foreach ([
    ['consent'=>false], ['mobile'=>'123'], ['source'=>'fake.test'],
    ['email'=>'bad@@email'], ['page_url'=>'http://mammutconnect.com/test'],
    ['service'=>'unknown'], ['vehicle_count'=>-1],
] as $invalid) {
    bad(static fn()=>WebsiteLead::validate(array_replace($p, $invalid)));
}
$secret = str_repeat('s', 64);
$body = json_encode($p, JSON_UNESCAPED_UNICODE);
$time=(string)time();
$nonce=bin2hex(random_bytes(16));
$hmac='sha256='.hash_hmac('sha256', $time."\n".$nonce."\n".$body, $secret);
check(WebsiteLead::authenticate($time,$nonce,$hmac,$body,$secret,time()), 'valid signature');
check(!WebsiteLead::authenticate($time,$nonce,$hmac,$body.'!',$secret,time()), 'tamper rejection');
check(!WebsiteLead::authenticate((string)(time()-600),$nonce,$hmac,$body,$secret,time()), 'stale reject');
check(!WebsiteLead::authenticate($time,$nonce,str_repeat('0', 64),$body,$secret,time()), 'malformed reject');
echo "PASS website leads validation and HMAC\n";
