<?php

declare(strict_types=1);

class Contact
{
    public static function byCustomer(int $customerId): array
    {
        $stmt = db()->prepare('SELECT * FROM contacts WHERE customer_id = ? ORDER BY is_primary DESC, id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM contacts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO contacts (customer_id, contact_name, position, mobile, phone, email, is_primary, notes) VALUES (:customer_id, :contact_name, :position, :mobile, :phone, :email, :is_primary, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE contacts SET customer_id=:customer_id, contact_name=:contact_name, position=:position, mobile=:mobile, phone=:phone, email=:email, is_primary=:is_primary, notes=:notes WHERE id=:id';
        $payload = self::payload($data);
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
            'is_primary' => isset($data['is_primary']) ? 1 : 0,
            'notes' => trim($data['notes'] ?? ''),
        ];
    }
}
