<?php

declare(strict_types=1);

class User
{
    public static function all(): array
    {
        return db()->query('SELECT id, name, email, role, is_active FROM users ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, :role, :is_active)';
        db()->prepare($sql)->execute([
            'name' => trim($data['name'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'role' => self::validRole($data['role'] ?? 'sales'),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $payload = [
            'name' => trim($data['name'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'role' => self::validRole($data['role'] ?? 'sales'),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'id' => $id,
        ];
        $passwordSql = '';
        if (trim((string) ($data['password'] ?? '')) !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $payload['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        $sql = "UPDATE users SET name = :name, email = :email, role = :role, is_active = :is_active $passwordSql WHERE id = :id";
        db()->prepare($sql)->execute($payload);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function roles(): array
    {
        return ['admin' => 'مدیر سیستم', 'sales' => 'کارشناس فروش'];
    }

    public static function roleLabel(string $role): string
    {
        return self::roles()[$role] ?? $role;
    }

    private static function validRole(string $role): string
    {
        return array_key_exists($role, self::roles()) ? $role : 'sales';
    }
}
