<?php

declare(strict_types=1);

class UsageReport
{
    public static function logLogin(string $actorType, int $actorId): void
    {
        self::ensureTables();
        $stmt = db()->prepare('INSERT INTO login_events (actor_type, actor_id, ip_address, user_agent) VALUES (?, ?, ?, ?)');
        $stmt->execute([$actorType, $actorId, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    }

    public static function logUsage(string $actorType, int $actorId, string $area, string $action): void
    {
        self::ensureTables();
        $stmt = db()->prepare('INSERT INTO usage_events (actor_type, actor_id, area, action_name, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$actorType, $actorId, $area, $action, $_SERVER['REMOTE_ADDR'] ?? '']);
    }

    public static function summary(): array
    {
        self::ensureTables();
        return [
            'user_logins_30' => self::count("SELECT COUNT(*) FROM login_events WHERE actor_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'contact_logins_30' => self::count("SELECT COUNT(*) FROM login_events WHERE actor_type = 'contact' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'user_usage_30' => self::count("SELECT COUNT(*) FROM usage_events WHERE actor_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'contact_usage_30' => self::count("SELECT COUNT(*) FROM usage_events WHERE actor_type = 'contact' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        ];
    }

    public static function loginsByActor(string $actorType): array
    {
        self::ensureTables();
        $nameExpr = $actorType === 'user' ? 'u.name' : 'ct.contact_name';
        $join = $actorType === 'user' ? 'JOIN users u ON u.id = le.actor_id' : 'JOIN contacts ct ON ct.id = le.actor_id';
        $stmt = db()->prepare("SELECT $nameExpr AS name, COUNT(*) AS total, MAX(le.created_at) AS last_login FROM login_events le $join WHERE le.actor_type = ? GROUP BY le.actor_id, name ORDER BY total DESC");
        $stmt->execute([$actorType]);
        return $stmt->fetchAll();
    }

    public static function usageByArea(): array
    {
        self::ensureTables();
        return db()->query('SELECT actor_type, area, COUNT(*) AS total FROM usage_events GROUP BY actor_type, area ORDER BY total DESC')->fetchAll();
    }

    private static function count(string $sql): int
    {
        return (int) db()->query($sql)->fetchColumn();
    }

    private static function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        db()->exec("CREATE TABLE IF NOT EXISTS login_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_type ENUM('user','contact') NOT NULL,
            actor_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_actor (actor_type, actor_id),
            INDEX idx_login_created (created_at)
        ) ENGINE=InnoDB");
        db()->exec("CREATE TABLE IF NOT EXISTS usage_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_type ENUM('user','contact') NOT NULL,
            actor_id INT UNSIGNED NOT NULL,
            area VARCHAR(80) NOT NULL,
            action_name VARCHAR(80) NOT NULL,
            ip_address VARCHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usage_actor (actor_type, actor_id),
            INDEX idx_usage_area (area),
            INDEX idx_usage_created (created_at)
        ) ENGINE=InnoDB");
        $done = true;
    }
}
