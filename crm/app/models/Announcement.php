<?php

declare(strict_types=1);

class Announcement
{
    public static function search(): array
    {
        $stmt = db()->query("
            SELECT a.*, c.customer_name, u.name AS created_by_name,
                (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) AS read_count
            FROM announcements a
            LEFT JOIN customers c ON c.id = a.customer_id
            LEFT JOIN users u ON u.id = a.created_by_user_id
            WHERE a.deleted_at IS NULL
            ORDER BY a.published_at DESC, a.id DESC
        ");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare("
            SELECT a.*, c.customer_name, u.name AS created_by_name
            FROM announcements a
            LEFT JOIN customers c ON c.id = a.customer_id
            LEFT JOIN users u ON u.id = a.created_by_user_id
            WHERE a.id = ? AND a.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $payload = self::payload($data);
        $payload['created_by_user_id'] = $userId;
        $sql = 'INSERT INTO announcements (title, body, audience_type, customer_id, published_at, created_by_user_id, is_active)
                VALUES (:title, :body, :audience_type, :customer_id, :published_at, :created_by_user_id, :is_active)';
        db()->prepare($sql)->execute($payload);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $payload = self::payload($data);
        $payload['id'] = $id;
        $sql = 'UPDATE announcements
                SET title=:title, body=:body, audience_type=:audience_type, customer_id=:customer_id, published_at=:published_at, is_active=:is_active
                WHERE id=:id AND deleted_at IS NULL';
        db()->prepare($sql)->execute($payload);
    }

    public static function delete(int $id): void
    {
        db()->prepare('UPDATE announcements SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
    }

    public static function forContact(array $contact): array
    {
        $stmt = db()->prepare("
            SELECT a.*, ar.read_at
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.contact_id = ?
            WHERE a.deleted_at IS NULL
              AND a.is_active = 1
              AND a.published_at <= NOW()
              AND (a.audience_type = 'all' OR (a.audience_type = 'customer' AND a.customer_id = ?))
            ORDER BY a.published_at DESC, a.id DESC
        ");
        $stmt->execute([(int) $contact['id'], (int) $contact['customer_id']]);
        return $stmt->fetchAll();
    }

    public static function findForContact(int $id, array $contact): ?array
    {
        $stmt = db()->prepare("
            SELECT a.*, ar.read_at
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.contact_id = ?
            WHERE a.id = ?
              AND a.deleted_at IS NULL
              AND a.is_active = 1
              AND a.published_at <= NOW()
              AND (a.audience_type = 'all' OR (a.audience_type = 'customer' AND a.customer_id = ?))
            LIMIT 1
        ");
        $stmt->execute([(int) $contact['id'], $id, (int) $contact['customer_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function unreadCountForContact(array $contact): int
    {
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.contact_id = ?
            WHERE a.deleted_at IS NULL
              AND a.is_active = 1
              AND a.published_at <= NOW()
              AND ar.id IS NULL
              AND (a.audience_type = 'all' OR (a.audience_type = 'customer' AND a.customer_id = ?))
        ");
        $stmt->execute([(int) $contact['id'], (int) $contact['customer_id']]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $announcementId, int $contactId): void
    {
        $stmt = db()->prepare('INSERT IGNORE INTO announcement_reads (announcement_id, contact_id, read_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$announcementId, $contactId]);
    }

    public static function audienceLabel(array $announcement): string
    {
        if (($announcement['audience_type'] ?? '') === 'customer') {
            return 'مشتری: ' . ($announcement['customer_name'] ?? 'نامشخص');
        }
        return 'همه مشتریان';
    }

    private static function payload(array $data): array
    {
        $audienceType = ($data['audience_type'] ?? 'all') === 'customer' ? 'customer' : 'all';
        $customerId = $audienceType === 'customer' ? (int) ($data['customer_id'] ?? 0) : null;
        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'audience_type' => $audienceType,
            'customer_id' => $customerId ?: null,
            'published_at' => db_date($data['published_at'] ?? null) ?: date('Y-m-d'),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ];
    }
}
