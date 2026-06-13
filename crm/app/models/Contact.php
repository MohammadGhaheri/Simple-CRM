<?php

declare(strict_types=1);

class Contact
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT ct.*, c.customer_name, u.name AS default_support_name FROM contacts ct JOIN customers c ON c.id = ct.customer_id LEFT JOIN users u ON u.id = ct.default_support_user_id WHERE ct.deleted_at IS NULL AND c.deleted_at IS NULL';
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
        $stmt = db()->prepare('SELECT ct.*, u.name AS default_support_name FROM contacts ct LEFT JOIN users u ON u.id = ct.default_support_user_id WHERE ct.customer_id = ? AND ct.deleted_at IS NULL ORDER BY ct.is_primary DESC, ct.id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function primaryByCustomer(int $customerId): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name FROM contacts ct JOIN customers c ON c.id = ct.customer_id WHERE ct.customer_id = ? AND ct.deleted_at IS NULL AND c.deleted_at IS NULL AND ct.email IS NOT NULL AND ct.email <> "" ORDER BY ct.is_primary DESC, ct.id DESC LIMIT 1');
        $stmt->execute([$customerId]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name, u.name AS default_support_name FROM contacts ct JOIN customers c ON c.id = ct.customer_id LEFT JOIN users u ON u.id = ct.default_support_user_id WHERE ct.id = ? AND ct.deleted_at IS NULL AND c.deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findPortalByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name, c.is_vip FROM contacts ct JOIN customers c ON c.id = ct.customer_id WHERE ct.email = ? AND ct.portal_enabled = 1 AND ct.deleted_at IS NULL AND c.deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        if (trim($email) === '') {
            return false;
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM contacts WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([trim($email)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO contacts (customer_id, contact_name, position, mobile, phone, email, portal_enabled, password_hash, approval_status, default_support_user_id, is_primary, notes) VALUES (:customer_id, :contact_name, :position, :mobile, :phone, :email, :portal_enabled, :password_hash, :approval_status, :default_support_user_id, :is_primary, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        return (int) db()->lastInsertId();
    }

    public static function createFromInvitation(array $customer, array $data): int
    {
        $sql = 'INSERT INTO contacts (customer_id, contact_name, position, mobile, phone, email, portal_enabled, password_hash, approval_status, default_support_user_id, is_primary, notes)
                VALUES (:customer_id, :contact_name, :position, :mobile, :phone, :email, 0, NULL, :approval_status, :default_support_user_id, 0, :notes)';
        db()->prepare($sql)->execute([
            'customer_id' => (int) $customer['id'],
            'contact_name' => trim($data['contact_name'] ?? ''),
            'position' => trim($data['position'] ?? ''),
            'mobile' => trim($data['mobile'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'approval_status' => 'pending',
            'default_support_user_id' => !empty($customer['owner_user_id']) ? (int) $customer['owner_user_id'] : null,
            'notes' => trim($data['notes'] ?? ''),
        ]);
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
        $sql = "UPDATE contacts SET customer_id=:customer_id, contact_name=:contact_name, position=:position, mobile=:mobile, phone=:phone, email=:email, portal_enabled=:portal_enabled, approval_status=:approval_status, default_support_user_id=:default_support_user_id, is_primary=:is_primary, notes=:notes $passwordSql WHERE id=:id";
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
    }

    public static function updatePortalPassword(int $id, string $password): void
    {
        db()->prepare('UPDATE contacts SET password_hash = ? WHERE id = ?')->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $id,
        ]);
    }

    public static function updateAvatar(int $id, string $avatarPath): void
    {
        db()->prepare('UPDATE contacts SET avatar_path = ? WHERE id = ?')->execute([$avatarPath, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE contacts SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
            $pdo->prepare('UPDATE tickets SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE contact_id = ?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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
            'approval_status' => self::validApprovalStatus($data['approval_status'] ?? (isset($data['portal_enabled']) ? 'approved' : 'approved')),
            'default_support_user_id' => !empty($data['default_support_user_id']) ? (int) $data['default_support_user_id'] : null,
            'is_primary' => isset($data['is_primary']) ? 1 : 0,
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    public static function approvalStatuses(): array
    {
        return [
            'approved' => 'تأیید شده',
            'pending' => 'در انتظار تأیید',
            'rejected' => 'رد شده',
        ];
    }

    public static function approvalLabel(string $status): string
    {
        return self::approvalStatuses()[$status] ?? $status;
    }

    private static function validApprovalStatus(string $status): string
    {
        return array_key_exists($status, self::approvalStatuses()) ? $status : 'approved';
    }
}
