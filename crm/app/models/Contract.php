<?php

declare(strict_types=1);

class Contract
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT ct.*, c.customer_name, d.deal_name, u.name AS owner_name
                FROM contracts ct
                JOIN customers c ON c.id = ct.customer_id
                LEFT JOIN deals d ON d.id = ct.deal_id
                LEFT JOIN users u ON u.id = ct.owner_user_id
                WHERE ct.deleted_at IS NULL AND c.deleted_at IS NULL';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (ct.contract_title LIKE ? OR ct.contract_number LIKE ? OR c.customer_name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q);
        }
        foreach (['status', 'owner_user_id', 'customer_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND ct.$field = ?";
                $params[] = $filters[$field];
            }
        }
        if (!empty($filters['renewal_due'])) {
            $sql .= " AND ct.renewal_reminder_date <= CURDATE() AND ct.status IN ('Active','Renewal Due')";
        }

        $sql .= ' ORDER BY ct.end_date ASC, ct.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byCustomer(int $customerId): array
    {
        $stmt = db()->prepare('SELECT ct.*, d.deal_name, u.name AS owner_name FROM contracts ct LEFT JOIN deals d ON d.id = ct.deal_id LEFT JOIN users u ON u.id = ct.owner_user_id WHERE ct.customer_id = ? AND ct.deleted_at IS NULL ORDER BY ct.end_date DESC, ct.id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function belongsToCustomer(int $id, int $customerId): bool
    {
        if ($id <= 0) {
            return true;
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM contracts WHERE id = ? AND customer_id = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $customerId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function byDeal(int $dealId): array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name, u.name AS owner_name FROM contracts ct JOIN customers c ON c.id = ct.customer_id LEFT JOIN users u ON u.id = ct.owner_user_id WHERE ct.deal_id = ? AND ct.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY ct.end_date DESC, ct.id DESC');
        $stmt->execute([$dealId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT ct.*, c.customer_name, d.deal_name, u.name AS owner_name
            FROM contracts ct
            JOIN customers c ON c.id = ct.customer_id
            LEFT JOIN deals d ON d.id = ct.deal_id
            LEFT JOIN users u ON u.id = ct.owner_user_id
            WHERE ct.id = ? AND ct.deleted_at IS NULL AND c.deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO contracts (contract_number, contract_title, customer_id, deal_id, product, vehicle_count, contract_amount, start_date, end_date, renewal_reminder_date, owner_user_id, status, notes) VALUES (:contract_number, :contract_title, :customer_id, :deal_id, :product, :vehicle_count, :contract_amount, :start_date, :end_date, :renewal_reminder_date, :owner_user_id, :status, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        $id = (int) db()->lastInsertId();
        $contract = self::find($id);
        if ($contract) {
            Activity::createOrUpdateContractRenewal($contract);
        }
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE contracts SET contract_number=:contract_number, contract_title=:contract_title, customer_id=:customer_id, deal_id=:deal_id, product=:product, vehicle_count=:vehicle_count, contract_amount=:contract_amount, start_date=:start_date, end_date=:end_date, renewal_reminder_date=:renewal_reminder_date, owner_user_id=:owner_user_id, status=:status, notes=:notes WHERE id=:id';
        $payload = self::payload($data);
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
        $contract = self::find($id);
        if ($contract) {
            Activity::createOrUpdateContractRenewal($contract);
        }
    }

    public static function delete(int $id): void
    {
        db()->prepare('UPDATE contracts SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
    }

    public static function transferOwner(int $fromUserId, int $toUserId): int
    {
        $stmt = db()->prepare("UPDATE contracts SET owner_user_id = ? WHERE owner_user_id = ? AND status IN ('Active','Renewal Due') AND deleted_at IS NULL");
        $stmt->execute([$toUserId, $fromUserId]);
        return $stmt->rowCount();
    }

    public static function renewalDue(int $limit = 6): array
    {
        $stmt = db()->prepare("SELECT ct.*, c.customer_name FROM contracts ct JOIN customers c ON c.id = ct.customer_id WHERE ct.renewal_reminder_date <= CURDATE() AND ct.status IN ('Active','Renewal Due') AND ct.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY ct.renewal_reminder_date ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function statuses(): array
    {
        $statuses = contract_status_options();
        return $statuses ?: ['Active', 'Renewal Due', 'Renewed', 'Expired', 'Cancelled'];
    }

    private static function payload(array $data): array
    {
        $endDate = db_date($data['end_date'] ?? null);
        $reminderDate = db_date($data['renewal_reminder_date'] ?? null);
        if (!$reminderDate && $endDate) {
            $days = max(0, (int) (Setting::get('contract_renewal_reminder_days') ?: 30));
            $reminderDate = date('Y-m-d', strtotime($endDate . ' -' . $days . ' days'));
        }

        return [
            'contract_number' => trim($data['contract_number'] ?? ''),
            'contract_title' => trim($data['contract_title'] ?? ''),
            'customer_id' => (int) ($data['customer_id'] ?? 0),
            'deal_id' => !empty($data['deal_id']) ? (int) $data['deal_id'] : null,
            'product' => $data['product'] ?? 'Other',
            'vehicle_count' => (int) ($data['vehicle_count'] ?? 0),
            'contract_amount' => (float) ($data['contract_amount'] ?? 0),
            'start_date' => db_date($data['start_date'] ?? null),
            'end_date' => $endDate,
            'renewal_reminder_date' => $reminderDate,
            'owner_user_id' => (int) ($data['owner_user_id'] ?? current_user_id()),
            'status' => in_array(($data['status'] ?? 'Active'), self::statuses(), true) ? $data['status'] : 'Active',
            'notes' => trim($data['notes'] ?? ''),
        ];
    }
}
