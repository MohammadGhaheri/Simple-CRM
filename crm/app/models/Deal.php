<?php

declare(strict_types=1);

class Deal
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT d.*, c.customer_name, u.name AS owner_name FROM deals d JOIN customers c ON c.id = d.customer_id LEFT JOIN users u ON u.id = d.owner_user_id WHERE d.deleted_at IS NULL AND c.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (d.deal_name LIKE ? OR c.customer_name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q);
        }
        foreach (['deal_stage', 'product', 'owner_user_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND d.$field = ?";
                $params[] = $filters[$field];
            }
        }
        $sql .= ' ORDER BY d.updated_at DESC, d.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byCustomer(int $customerId): array
    {
        $stmt = db()->prepare('SELECT * FROM deals WHERE customer_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT d.*, c.customer_name, u.name AS owner_name FROM deals d JOIN customers c ON c.id = d.customer_id LEFT JOIN users u ON u.id = d.owner_user_id WHERE d.id = ? AND d.deleted_at IS NULL AND c.deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO deals (deal_name, customer_id, product, vehicle_count, estimated_amount, probability, weighted_amount, deal_stage, expected_close_date, owner_user_id, win_loss_reason, notes) VALUES (:deal_name, :customer_id, :product, :vehicle_count, :estimated_amount, :probability, :weighted_amount, :deal_stage, :expected_close_date, :owner_user_id, :win_loss_reason, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE deals SET deal_name=:deal_name, customer_id=:customer_id, product=:product, vehicle_count=:vehicle_count, estimated_amount=:estimated_amount, probability=:probability, weighted_amount=:weighted_amount, deal_stage=:deal_stage, expected_close_date=:expected_close_date, owner_user_id=:owner_user_id, win_loss_reason=:win_loss_reason, notes=:notes WHERE id=:id';
        $payload = self::payload($data);
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
    }

    public static function delete(int $id): void
    {
        db()->prepare('UPDATE deals SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
    }

    private static function payload(array $data): array
    {
        $estimated = (float) ($data['estimated_amount'] ?? 0);
        $probability = min(100, max(0, (int) ($data['probability'] ?? 0)));
        return [
            'deal_name' => trim($data['deal_name'] ?? ''),
            'customer_id' => (int) ($data['customer_id'] ?? 0),
            'product' => $data['product'] ?? 'Other',
            'vehicle_count' => (int) ($data['vehicle_count'] ?? 0),
            'estimated_amount' => $estimated,
            'probability' => $probability,
            'weighted_amount' => $estimated * $probability / 100,
            'deal_stage' => $data['deal_stage'] ?? 'Lead',
            'expected_close_date' => db_date($data['expected_close_date'] ?? null),
            'owner_user_id' => (int) ($data['owner_user_id'] ?? current_user_id()),
            'win_loss_reason' => trim($data['win_loss_reason'] ?? ''),
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    public static function statsByStage(): array
    {
        return db()->query('SELECT d.deal_stage, COUNT(*) AS total, SUM(d.estimated_amount) AS amount FROM deals d JOIN customers c ON c.id = d.customer_id WHERE d.deleted_at IS NULL AND c.deleted_at IS NULL GROUP BY d.deal_stage ORDER BY total DESC')->fetchAll();
    }
}
