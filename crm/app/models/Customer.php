<?php

declare(strict_types=1);

class Customer
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT c.*, u.name AS owner_name FROM customers c LEFT JOIN users u ON u.id = c.owner_user_id WHERE 1=1';
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
        $stmt = db()->prepare('SELECT c.*, u.name AS owner_name FROM customers c LEFT JOIN users u ON u.id = c.owner_user_id WHERE c.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO customers (customer_code, customer_name, customer_type, industry, city, lead_source, interested_product, vehicle_count, estimated_contract_value, sales_status, owner_user_id, last_followup_date, next_followup_date, notes) VALUES (:customer_code, :customer_name, :customer_type, :industry, :city, :lead_source, :interested_product, :vehicle_count, :estimated_contract_value, :sales_status, :owner_user_id, :last_followup_date, :next_followup_date, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE customers SET customer_code=:customer_code, customer_name=:customer_name, customer_type=:customer_type, industry=:industry, city=:city, lead_source=:lead_source, interested_product=:interested_product, vehicle_count=:vehicle_count, estimated_contract_value=:estimated_contract_value, sales_status=:sales_status, owner_user_id=:owner_user_id, last_followup_date=:last_followup_date, next_followup_date=:next_followup_date, notes=:notes WHERE id=:id';
        $payload = self::payload($data);
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
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
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    public static function statsByType(): array
    {
        return db()->query('SELECT customer_type, COUNT(*) AS total FROM customers GROUP BY customer_type ORDER BY total DESC')->fetchAll();
    }
}
