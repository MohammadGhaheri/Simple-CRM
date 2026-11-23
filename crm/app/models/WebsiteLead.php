<?php
declare(strict_types=1);

/** Isolated website intake; raw requests are never automatically promoted to customers. */
final class WebsiteLead
{
    public const STATUSES = [
        'new' => 'جدید',
        'reviewing' => 'در حال بررسی',
        'contacted' => 'تماس گرفته شد',
        'qualified' => 'واجد شرایط',
        'converted' => 'تبدیل‌شده به فرصت فروش',
        'invalid' => 'نامعتبر / تکراری',
    ];

    public static function validate(array $p): array
    {
        $str = static function (string $key, int $max, bool $required = false) use ($p): string {
            if (isset($p[$key]) && !is_string($p[$key])) {
                throw new InvalidArgumentException("Invalid $key");
            }
            $s = trim((string) ($p[$key] ?? ''));
            if (!preg_match('//u', $s) || strlen($s) > $max * 4 || (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max)
                || ($required && $s === '')) {
                throw new InvalidArgumentException("Invalid $key");
            }
            return $s;
        };
        $request = $str('request_id', 100, true);
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $request)) {
            throw new InvalidArgumentException('Invalid request_id');
        }
        if ($str('source', 120, true) !== 'mammutconnect.com') {
            throw new InvalidArgumentException('Invalid source');
        }
        if (($p['consent'] ?? null) !== true) {
            throw new InvalidArgumentException('Consent required');
        }
        $name = $str('name', 160, true);
        $mobile = strtr($str('mobile', 20, true), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        if (preg_match('/^\+989[0-9]{9}$/D', $mobile)) {
            $mobile = '0' . substr($mobile, 3);
        }
        if (!preg_match('/^09[0-9]{9}$/D', $mobile)) {
            throw new InvalidArgumentException('Invalid mobile');
        }
        $email = $str('email', 190);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        $at = $str('submitted_at', 40, true);
        try {
            $date = new DateTimeImmutable($at);
            if (!preg_match('/^\d{4}-\d\d-\d\dT/', $at)) {
                throw new Exception();
            }
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Invalid submitted_at');
        }
        $page = $str('page_url', 1024, true);
        $url = parse_url($page);
        if (!$url || strtolower($url['scheme'] ?? '') !== 'https' ||
            !in_array(strtolower($url['host'] ?? ''), ['mammutconnect.com', 'www.mammutconnect.com'], true) ||
            isset($url['user']) || isset($url['pass'])) {
            throw new InvalidArgumentException('Invalid page_url');
        }
        $fleet = $str('fleet_type', 40);
        $service = $str('service', 60);
        if (!in_array($fleet, ['truck','trailer','van','car','bus','mixed','other',''], true) ||
            !in_array($service, ['ifms','iotruck','iotrailer','dsp','tracking','consultation','other',''], true)) {
            throw new InvalidArgumentException('Invalid product or fleet code');
        }
        $vehicleCount = $p['vehicle_count'] ?? 0;
        if (!is_int($vehicleCount) && !is_string($vehicleCount)) {
            throw new InvalidArgumentException('Invalid vehicle_count');
        }
        if (!ctype_digit((string) $vehicleCount) || (float) $vehicleCount > 1000000) {
            throw new InvalidArgumentException('Invalid vehicle_count');
        }
        $date = $date->setTimezone(new DateTimeZone('UTC'));
        return [
            'request_id' => $request,
            'source' => 'mammutconnect.com',
            'submitted_at' => $date->format('Y-m-d H:i:s'),
            'contact_name' => $name,
            'mobile' => $mobile,
            'email' => $email ?: null,
            'company' => $str('company', 190) ?: null,
            'fleet_type' => $fleet ?: 'other',
            'fleet_type_label' => $str('fleet_type_label', 160) ?: null,
            'service_code' => $service ?: 'other',
            'service_label' => $str('service_label', 160) ?: null,
            'vehicle_count' => (int) $vehicleCount,
            'message' => $str('message', 4000) ?: null,
            'consent' => 1,
            'page_url' => $page,
            'utm_source' => $str('utm_source', 120) ?: null,
            'utm_medium' => $str('utm_medium', 120) ?: null,
            'utm_campaign' => $str('utm_campaign', 120) ?: null,
        ];
    }

    public static function authenticate(string $timestamp, string $nonce, string $signature, string $body, string $secret, int $now): bool
    {
        if ($secret =[REDACTED] '' || strlen($secret) < 32 || !ctype_digit($timestamp) || abs($now - (int) $timestamp) > 300 ||
            !preg_match('/^[a-f0-9]{32,64}$/D', $nonce) || !preg_match('/^sha256=[a-f0-9]{64}$/D', $signature)) {
            return false;
        }
        return hash_equals('sha256=' . hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret), $signature);
    }

    public static function gateRequest(PDO $db, string $nonce, string $client): bool
    {
        $db->prepare('DELETE FROM website_api_nonces WHERE expires_at < UTC_TIMESTAMP()')->execute();
        $db->prepare('DELETE FROM website_api_rate_limits WHERE minute_bucket < ?')->execute([intdiv(time(), 60) - 15]);
        try {
            $db->prepare('INSERT INTO website_api_nonces (nonce, expires_at) VALUES (?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))')->execute([$nonce]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
        $bucket = intdiv(time(), 60);
        $key = hash('sha256', $client);
        $db->prepare('INSERT INTO website_api_rate_limits (client_key,minute_bucket,count) VALUES (?,?,1) ON DUPLICATE KEY UPDATE count = count + 1')->execute([$key, $bucket]);
        $query = $db->prepare('SELECT count FROM website_api_rate_limits WHERE client_key = ? AND minute_bucket = ?');
        $query->execute([$key, $bucket]);
        if ((int) $query->fetchColumn() > 60) {
            throw new DomainException('Rate limit exceeded', 429);
        }
        return true;
    }

    public static function receive(PDO $db, array $valid, string $rawBody): array
    {
        $sha = hash('sha256', $rawBody);
        $owner = class_exists('Setting') ? (int) Setting::get('website_lead_default_owner_user_id') : 0;
        if ($owner > 0) {
            $q = $db->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND is_active = 1 AND role = 'sales'");
            $q->execute([$owner]);
            if (!(int) $q->fetchColumn()) {
                $owner = 0;
            }
        }
        $columns = array_keys($valid);
        $columns[] = 'payload_sha256';
        $columns[] = 'assigned_user_id';
        $params = array_values($valid);
        $params[] = $sha;
        $params[] = $owner ?: null;
        $insert = 'INSERT INTO website_leads (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        try {
            $db->prepare($insert)->execute($params);
            return ['lead_id' => (int) $db->lastInsertId(), 'duplicate' => false];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $q = $db->prepare('SELECT id,payload_sha256 FROM website_leads WHERE request_id = ? LIMIT 1');
            $q->execute([$valid['request_id']]);
            $old = $q->fetch(PDO::FETCH_ASSOC);
            if (!$old || !hash_equals($old['payload_sha256'], $sha)) {
                throw new DomainException('request_id conflict', 409);
            }
            return ['lead_id' => (int) $old['id'], 'duplicate' => true];
        }
    }

    private static function visibility(string $role, int $userId, array &$params): string
    {
        if ($role === 'admin') {
            return '1=1';
        }
        if ($role === 'sales' && $userId > 0) {
            $params[] = $userId;
            return '(w.assigned_user_id IS NULL OR w.assigned_user_id = ?)';
        }
        return '1=0';
    }

    public static function search(array $filters, string $role, int $userId): array
    {
        $params = [];
        $sql = 'SELECT w.*, u.name AS assigned_name FROM website_leads w LEFT JOIN users u ON u.id = w.assigned_user_id WHERE ' . self::visibility($role, $userId, $params);
        if (isset($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $sql .= ' AND w.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['service'])) {
            $sql .= ' AND w.service_code = ?';
            $params[] = $filters['service'];
        }
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $filters['date_from'])) {
            $sql .= ' AND DATE(w.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $filters['date_to'])) {
            $sql .= ' AND DATE(w.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (w.company LIKE ? OR w.mobile LIKE ? OR w.contact_name LIKE ?)';
            $q = '%' . (function_exists('mb_substr') ? mb_substr((string) $filters['q'], 0, 80, 'UTF-8') : substr((string)$filters['q'], 0, 80)) . '%';
            array_push($params, $q, $q, $q);
        }
        $sql .= ' ORDER BY w.created_at DESC, w.id DESC LIMIT 200';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findVisible(int $id, string $role, int $userId, bool $lock = false): ?array
    {
        $params = [];
        $where = self::visibility($role, $userId, $params);
        $stmt = db()->prepare('SELECT w.* FROM website_leads w WHERE w.id = ? AND ' . $where . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(array_merge([$id], $params));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function counts(string $role, int $userId): array
    {
        $params = [];
        $where = self::visibility($role, $userId, $params);
        $stmt = db()->prepare("SELECT SUM(status='new') AS new_count, SUM(status NOT IN ('converted','invalid') AND next_followup_at IS NOT NULL AND next_followup_at <= UTC_TIMESTAMP()) AS due_count FROM website_leads w WHERE " . $where);
        $stmt->execute($params);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['new' => (int) ($r['new_count'] ?? 0), 'due' => (int) ($r['due_count'] ?? 0)];
    }

    public static function updateFollowup(int $id, array $input, string $role, int $userId): void
    {
        $db = db();
        $db->beginTransaction();
        try {
            $lead = self::findVisible($id, $role, $userId, true);
            if (!$lead || $lead['status'] === 'converted') {
                throw new DomainException('درخواست قابل ویرایش نیست.');
            }
            $status = (string) ($input['status'] ?? '');
            if (!isset(self::STATUSES[$status]) || $status === 'converted') {
                throw new InvalidArgumentException('وضعیت نامعتبر است.');
            }
            $owner = (int) ($input['assigned_user_id'] ?? 0);
            if ($role === 'sales' && $owner !== $userId) {
                throw new DomainException('فروشنده تنها می‌تواند درخواست را به خود اختصاص دهد.');
            }
            if ($owner) {
                $q = $db->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role IN ('sales','admin') AND is_active = 1");
                $q->execute([$owner]);
                if (!(int) $q->fetchColumn()) {
                    throw new InvalidArgumentException('مسئول فروش نامعتبر است.');
                }
            }
            $next = trim((string) ($input['next_followup_at'] ?? ''));
            if ($next !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $next)) {
                throw new InvalidArgumentException('تاریخ پیگیری نامعتبر است.');
            }
            $result = trim((string) ($input['call_result'] ?? ''));
            if (strlen($result) > 12000) {
                throw new InvalidArgumentException('نتیجه تماس طولانی است.');
            }
            $db->prepare('UPDATE website_leads SET status=?, assigned_user_id=?,call_result=?,next_followup_at=? WHERE id=?')
                ->execute([$status, $owner ?: null, $result, $next ? str_replace('T', ' ', $next) . ':00' : null, $id]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function possibleCustomers(array $lead, string $role, int $userId): array
    {
        $company = trim((string) ($lead['company'] ?? ''));
        $email = trim((string) ($lead['email'] ?? ''));
        $params = [$company, $company, $lead['mobile'], $email, $email];
        $sql = 'SELECT DISTINCT c.id, c.customer_name FROM customers c LEFT JOIN contacts ct ON ct.customer_id=c.id AND ct.deleted_at IS NULL
            WHERE c.deleted_at IS NULL AND ((? <> "" AND c.customer_name = ?) OR ct.mobile = ? OR (? <> "" AND ct.email = ?))';
        if ($role === 'sales') {
            $sql .= ' AND c.owner_user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY c.customer_name LIMIT 30';
        $q = db()->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Single transaction, with row lock. No nested Deal::create() transaction. */
    public static function convert(int $id, int $selectedCustomerId, bool $makeDeal, string $role, int $userId): array
    {
        $db = db();
        $db->beginTransaction();
        try {
            $w = self::findVisible($id, $role, $userId, true);
            if (!$w || !in_array($role, ['admin','sales'], true)) {
                throw new DomainException('دسترسی مجاز نیست.');
            }
            if ($w['status'] === 'converted') {
                throw new DomainException('این درخواست قبلاً تبدیل شده است.');
            }
            $owner = $role === 'sales' ? $userId : ((int) $w['assigned_user_id'] ?: $userId);
            if ($selectedCustomerId > 0) {
                $customer = Customer::find($selectedCustomerId);
                if (!$customer) throw new DomainException('مشتری انتخاب‌شده در دسترس نیست.');
                $customerId = $selectedCustomerId;
            } else {
                $q = $db->prepare('SELECT DISTINCT c.id FROM customers c LEFT JOIN contacts ct ON ct.customer_id=c.id AND ct.deleted_at IS NULL WHERE c.deleted_at IS NULL AND ((? <> "" AND c.customer_name = ?) OR ct.mobile = ? OR (? IS NOT NULL AND ? <> "" AND ct.email = ?)) LIMIT 6');
                $company = (string) ($w['company'] ?? '');
                $email = (string) ($w['email'] ?? '');
                $q->execute([$company, $company, $w['mobile'], $email, $email, $email]);
                if ($q->fetchAll(PDO::FETCH_ASSOC)) {
                    throw new DomainException('مشتری مشابه یافت شد؛ ابتدا مشتری موجود را انتخاب کنید.');
                }
                $customerId = Customer::create([
                    'customer_code' => 'WEB-' . $id,
                    'customer_name' => $company ?: $w['contact_name'],
                    'customer_type' => 'B2B Fleet',
                    'lead_source' => 'mammutconnect.com',
                    'interested_product' => $w['service_code'],
                    'vehicle_count' => $w['vehicle_count'],
                    'sales_status' => 'New',
                    'owner_user_id' => $owner,
                    'notes' => 'درخواست وب‌سایت #' . $id . "\n" . (string) $w['message'],
                ]);
            }
            $q = $db->prepare('SELECT id FROM contacts WHERE customer_id=? AND deleted_at IS NULL AND (mobile=? OR (? <> "" AND email=?)) ORDER BY id LIMIT 1');
            $q->execute([$customerId, $w['mobile'], (string) $w['email'], (string) $w['email']]);
            $contactId = (int) ($q->fetchColumn() ?: 0);
            if (!$contactId) {
                $contactId = Contact::create([
                    'customer_id'=>$customerId, 'contact_name'=>$w['contact_name'],
                    'mobile'=>$w['mobile'], 'email'=>$w['email'],
                    'is_primary'=>1, 'notes'=>'ورودی سایت / درخواست #' . $id,
                ]);
            }
            $dealId = null;
            if ($makeDeal) {
                $db->prepare('INSERT INTO deals (deal_name,customer_id,product,vehicle_count,estimated_amount,probability,weighted_amount,deal_stage,owner_user_id,notes) VALUES (?,?,?,?,0,20,0,?, ?,?)')->execute([
                    'درخواست وب‌سایت: ' . ($w['company'] ?: $w['contact_name']),
                    $customerId, $w['service_code'], $w['vehicle_count'], 'Lead', $owner, 'Website lead #' . $id
                ]);
                $dealId = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO deal_stage_events (deal_id, from_stage, to_stage, changed_by_user_id) VALUES (?, NULL, ?, ?)')
                    ->execute([$dealId, 'Lead', $userId]);
            }
            $db->prepare("UPDATE website_leads SET status='converted', assigned_user_id=?, converted_customer_id=?,converted_contact_id=?,converted_deal_id=? WHERE id=?")
                ->execute([$owner, $customerId, $contactId, $dealId, $id]);
            $db->commit();
            return ['customer_id'=>$customerId,'contact_id'=>$contactId,'deal_id'=>$dealId];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
