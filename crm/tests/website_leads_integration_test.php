<?php
declare(strict_types=1);
require __DIR__ . '/../app/models/WebsiteLead.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/Customer.php';
require __DIR__ . '/../app/models/Contact.php';
function db(): PDO { return $GLOBALS['db']; }
function current_user_id(): int { return (int) $GLOBALS['test_user_id']; }
function current_user_role(): string { return (string) $GLOBALS['test_role']; }
function db_date($date) { return $date ?: null; }
function check(bool $ok, string $why): void { if(!$ok) throw new RuntimeException('FAIL '.$why); }
$dsn=(string)getenv('CRM_TEST_DSN');
if ($dsn === '') throw new RuntimeException('Only run against an isolated synthetic test database.');
$GLOBALS['db'] = new PDO($dsn, (string)getenv('CRM_TEST_USER'), (string)getenv('CRM_TEST_PASSWORD'), [
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false
]);
$db=db();
$db->exec("INSERT INTO users (name,email,password_hash,role,is_active) VALUES ('TEST Sales','test-sales@example.org','synthetic','sales',1)");
$GLOBALS['test_user_id'] = (int)$db->lastInsertId();
$GLOBALS['test_role'] = 'sales';
$p = [
 'request_id'=>'TEST-20261123-abcdefgh',
 'source'=>'mammutconnect.com','submitted_at'=>'2026-11-23T11:00:00Z',
 'name'=>'تماس‌گیرنده آزمایشی TEST','mobile'=>'09120000123','email'=>'test-website@example.org',
 'company'=>'شرکت ساختگی TEST','fleet_type'=>'truck','fleet_type_label'=>'کشنده',
 'service'=>'ifms','service_label'=>'مدیریت ناوگان',
 'vehicle_count'=>20,'message'=>'متن فارسی تست','consent'=>true,
 'page_url'=>'https://mammutconnect.com/contact-us-2/', 'utm_source'=>'test'
];
$raw = json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$v=WebsiteLead::validate($p);
$nonce=bin2hex(random_bytes(16));
check(WebsiteLead::gateRequest($db,$nonce,'mammutconnect.com'),'nonce accepted');
check(!WebsiteLead::gateRequest($db,$nonce,'mammutconnect.com'),'nonce replay rejected');
$r1=WebsiteLead::receive($db,$v,$raw);
$r2=WebsiteLead::receive($db,$v,$raw);
check(!$r1['duplicate'] && $r2['duplicate'] && $r1['lead_id']===$r2['lead_id'],'exactly once receive');
try {
    WebsiteLead::receive($db,$v,$raw.' ');
    throw new RuntimeException('same request ID with different body accepted');
} catch (DomainException $e) { check($e->getCode()===409, 'conflict status'); }
$counts=WebsiteLead::counts('sales',$GLOBALS['test_user_id']);
check($counts['new']===1,'sales sees unassigned lead');
$rows=WebsiteLead::search(['q'=>'شرکت ساختگی TEST'],'sales',$GLOBALS['test_user_id']);
check(count($rows)===1 && $rows[0]['contact_name']==='تماس‌گیرنده آزمایشی TEST','UTF-8 search');
WebsiteLead::updateFollowup($r1['lead_id'],[
 'status'=>'qualified',
 'assigned_user_id'=>$GLOBALS['test_user_id'],
 'call_result'=>'تماس آزمایشی برقرار شد',
 'next_followup_at'=>'2026-11-24T11:30'
],'sales',$GLOBALS['test_user_id']);
try {
    WebsiteLead::updateFollowup($r1['lead_id'], ['status'=>'new','assigned_user_id'=>999], 'sales', $GLOBALS['test_user_id']);
    throw new RuntimeException('RBAC owner bypass accepted');
} catch (DomainException $e) {}
$converted=WebsiteLead::convert($r1['lead_id'],0,true,'sales',$GLOBALS['test_user_id']);
check($converted['customer_id']>0 && $converted['contact_id']>0 && $converted['deal_id']>0,'transactional conversion');
$lead=WebsiteLead::findVisible($r1['lead_id'],'sales',$GLOBALS['test_user_id']);
check($lead['status']==='converted','converted status');
$stage=$db->prepare('SELECT to_stage FROM deal_stage_events WHERE deal_id=?');
$stage->execute([$converted['deal_id']]);
check($stage->fetchColumn()==='Lead','initial deal history');
$customers=$db->query("SELECT COUNT(*) FROM customers WHERE customer_name='شرکت ساختگی TEST'")->fetchColumn();
check((int)$customers===1,'single customer created');
try {
    WebsiteLead::convert($r1['lead_id'],0,true,'sales',$GLOBALS['test_user_id']);
    throw new RuntimeException('second conversion accepted');
} catch (DomainException $e) {}
check((int)$db->query('SELECT COUNT(*) FROM deals')->fetchColumn()===1,'exactly one deal');
echo "PASS synthetic MariaDB idempotency, replay, UTF-8, RBAC, conversion\n";
