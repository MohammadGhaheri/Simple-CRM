<?php

declare(strict_types=1);

class Customer
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT c.*, u.name AS owner_name FROM customers c LEFT JOIN users u ON u.id = c.owner_user_id WHERE c.deleted_at IS NULL';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (c.customer_name LIKE ? OR c.customer_code LIKE ? OR c.city LIKE ? OR c.industry LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q);
        }
        foreach (['customer_type', 'sales_status', 'owner_user_id', 'city'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND c.$field = ?";
                $params[] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT c.*, u.name AS owner_name FROM customers c LEFT JOIN users u ON u.id = c.owner_user_id WHERE c.id = ? AND c.deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO customers (customer_code, customer_name, customer_type, industry, city, lead_source, interested_product, vehicle_count, estimated_contract_value, sales_status, owner_user_id, last_followup_date, next_followup_date, is_vip, notes) VALUES (:customer_code, :customer_name, :customer_type, :industry, :city, :lead_source, :interested_product, :vehicle_count, :estimated_contract_value, :sales_status, :owner_user_id, :last_followup_date, :next_followup_date, :is_vip, :notes)';
        $payload = self::payload($data);
        if ($payload['customer_code'] === '' && class_exists('Setting') && Setting::get('customer_code_mode') === 'auto') {
            $payload['customer_code'] = self::nextCode();
        }
        db()->prepare($sql)->execute($payload);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE customers SET customer_code=:customer_code, customer_name=:customer_name, customer_type=:customer_type, industry=:industry, city=:city, lead_source=:lead_source, interested_product=:interested_product, vehicle_count=:vehicle_count, estimated_contract_value=:estimated_contract_value, sales_status=:sales_status, owner_user_id=:owner_user_id, last_followup_date=:last_followup_date, next_followup_date=:next_followup_date, is_vip=:is_vip, notes=:notes WHERE id=:id';
        $payload = self::payload($data);
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
    }

    public static function delete(int $id): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE customers SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
            $pdo->prepare('UPDATE contacts SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE customer_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE tickets SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE customer_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE deals SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE customer_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE contracts SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE customer_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE activities SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE customer_id = ?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function ensureInviteToken(int $id): string
    {
        $customer = self::find($id);
        if (!$customer) {
            return '';
        }
        if (!empty($customer['contact_invite_token'])) {
            return (string) $customer['contact_invite_token'];
        }

        return self::regenerateInviteToken($id);
    }

    public static function regenerateInviteToken(int $id): string
    {
        $token = '';
        $stmt = db()->prepare('SELECT COUNT(*) FROM customers WHERE contact_invite_token = ?');
        do {
            $token = bin2hex(random_bytes(24));
            $stmt->execute([$token]);
        } while ((int) $stmt->fetchColumn() > 0);

        db()->prepare('UPDATE customers SET contact_invite_token = ?, contact_invite_created_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$token, $id]);
        return $token;
    }

    public static function findByInviteToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = db()->prepare('SELECT c.*, u.name AS owner_name FROM customers c LEFT JOIN users u ON u.id = c.owner_user_id WHERE c.contact_invite_token = ? AND c.deleted_at IS NULL LIMIT 1');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    private static function payload(array $data): array
    {
        return [
            'customer_code' => trim($data['customer_code'] ?? ''),
            'customer_name' => trim($data['customer_name'] ?? ''),
            'customer_type' => $data['customer_type'] ?? 'Other',
            'industry' => trim($data['industry'] ?? ''),
            'city' => trim($data['city'] ?? ''),
            'lead_source' => trim($data['lead_source'] ?? ''),
            'interested_product' => $data['interested_product'] ?? 'Other',
            'vehicle_count' => (int) ($data['vehicle_count'] ?? 0),
            'estimated_contract_value' => (float) ($data['estimated_contract_value'] ?? 0),
            'sales_status' => $data['sales_status'] ?? 'New',
            'owner_user_id' => (int) ($data['owner_user_id'] ?? current_user_id()),
            'last_followup_date' => db_date($data['last_followup_date'] ?? null),
            'next_followup_date' => db_date($data['next_followup_date'] ?? null),
            'is_vip' => isset($data['is_vip']) ? 1 : 0,
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    private static function nextCode(): string
    {
        $format = class_exists('Setting') ? trim(Setting::get('customer_code_format')) : '';
        $format = $format !== '' ? $format : 'CUS-{YYYY}-{SEQ4}';
        $next = (int) db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM customers')->fetchColumn();
        $replacements = [
            '{YYYY}' => date('Y'),
            '{YY}' => date('y'),
            '{MM}' => date('m'),
            '{DD}' => date('d'),
            '{SEQ}' => (string) $next,
            '{SEQ3}' => str_pad((string) $next, 3, '0', STR_PAD_LEFT),
            '{SEQ4}' => str_pad((string) $next, 4, '0', STR_PAD_LEFT),
            '{SEQ5}' => str_pad((string) $next, 5, '0', STR_PAD_LEFT),
        ];

        $code = strtr($format, $replacements);
        $base = $code;
        $suffix = 1;
        $stmt = db()->prepare('SELECT COUNT(*) FROM customers WHERE customer_code = ?');
        while (true) {
            $stmt->execute([$code]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $code;
            }
            $suffix++;
            $code = $base . '-' . $suffix;
        }
    }

    public static function statsByType(): array
    {
        return db()->query('SELECT customer_type, COUNT(*) AS total FROM customers WHERE deleted_at IS NULL GROUP BY customer_type ORDER BY total DESC')->fetchAll();
    }
}
