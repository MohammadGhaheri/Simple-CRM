<?php

declare(strict_types=1);

class Contact
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT ct.*, c.customer_name, u.name AS default_support_name FROM contacts ct JOIN customers c ON c.id = ct.customer_id LEFT JOIN users u ON u.id = ct.default_support_user_id WHERE 1=1';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (ct.contact_name LIKE ? OR ct.mobile LIKE ? OR ct.email LIKE ? OR c.customer_name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q);
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND ct.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (isset($filters['portal_enabled']) && $filters['portal_enabled'] !== '') {
            $sql .= ' AND ct.portal_enabled = ?';
            $params[] = (int) $filters['portal_enabled'];
        }
        $sql .= ' ORDER BY c.customer_name, ct.is_primary DESC, ct.contact_name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byCustomer(int $customerId): array
    {
        $stmt = db()->prepare('SELECT ct.*, u.name AS default_support_name FROM contacts ct LEFT JOIN users u ON u.id = ct.default_support_user_id WHERE ct.customer_id = ? ORDER BY ct.is_primary DESC, ct.id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function primaryByCustomer(int $customerId): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name FROM contacts ct JOIN customers c ON c.id = ct.customer_id WHERE ct.customer_id = ? AND ct.email IS NOT NULL AND ct.email <> "" ORDER BY ct.is_primary DESC, ct.id DESC LIMIT 1');
        $stmt->execute([$customerId]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name, u.name AS default_support_name FROM contacts ct JOIN customers c ON c.id = ct.customer_id LEFT JOIN users u ON u.id = ct.default_support_user_id WHERE ct.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findPortalByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name, c.is_vip FROM contacts ct JOIN customers c ON c.id = ct.customer_id WHERE ct.email = ? AND ct.portal_enabled = 1 LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO contacts (customer_id, contact_name, position, mobile, phone, email, portal_enabled, password_hash, default_support_user_id, is_primary, notes) VALUES (:customer_id, :contact_name, :position, :mobile, :phone, :email, :portal_enabled, :password_hash, :default_support_user_id, :is_primary, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $payload = self::payload($data);
        $passwordSql = '';
        if (trim((string) ($data['portal_password'] ?? '')) !== '') {
            $passwordSql = ', password_hash=:password_hash';
            $payload['password_hash'] = password_hash((string) $data['portal_password'], PASSWORD_DEFAULT);
        } else {
            unset($payload['password_hash']);
        }
        $sql = "UPDATE contacts SET customer_id=:customer_id, contact_name=:contact_name, position=:position, mobile=:mobile, phone=:phone, email=:email, portal_enabled=:portal_enabled, default_support_user_id=:default_support_user_id, is_primary=:is_primary, notes=:notes $passwordSql WHERE id=:id";
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
    }

    private static function payload(array $data): array
    {
        return [
            'customer_id' => (int) $data['customer_id'],
            'contact_name' => trim($data['contact_name'] ?? ''),
            'position' => trim($data['position'] ?? ''),
            'mobile' => trim($data['mobile'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'portal_enabled' => isset($data['portal_enabled']) ? 1 : 0,
            'password_hash' => trim((string) ($data['portal_password'] ?? '')) !== '' ? password_hash((string) $data['portal_password'], PASSWORD_DEFAULT) : null,
            'default_support_user_id' => !empty($data['default_support_user_id']) ? (int) $data['default_support_user_id'] : null,
            'is_primary' => isset($data['is_primary']) ? 1 : 0,
            'notes' => trim($data['notes'] ?? ''),
        ];
    }
}
