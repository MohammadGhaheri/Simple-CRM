<?php

declare(strict_types=1);

class Announcement
{
    public static function search(): array
    {
        $stmt = db()->query("
            SELECT a.*, c.customer_name, u.name AS created_by_name,
                GROUP_CONCAT(DISTINCT tc.customer_name ORDER BY tc.customer_name SEPARATOR '، ') AS target_customer_names,
                (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) AS read_count
            FROM announcements a
            LEFT JOIN customers c ON c.id = a.customer_id
            LEFT JOIN announcement_targets tgt ON tgt.announcement_id = a.id
            LEFT JOIN customers tc ON tc.id = tgt.customer_id AND tc.deleted_at IS NULL
            LEFT JOIN users u ON u.id = a.created_by_user_id
            WHERE a.deleted_at IS NULL
            GROUP BY a.id
            ORDER BY a.published_at DESC, a.id DESC
        ");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare("
            SELECT a.*, c.customer_name, u.name AS created_by_name,
                GROUP_CONCAT(DISTINCT tgt.customer_id ORDER BY tgt.customer_id SEPARATOR ',') AS target_customer_ids
            FROM announcements a
            LEFT JOIN customers c ON c.id = a.customer_id
            LEFT JOIN announcement_targets tgt ON tgt.announcement_id = a.id
            LEFT JOIN users u ON u.id = a.created_by_user_id
            WHERE a.id = ? AND a.deleted_at IS NULL
            GROUP BY a.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $payload = self::payload($data);
        $payload['created_by_user_id'] = $userId;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $sql = 'INSERT INTO announcements (title, body, audience_type, customer_id, published_at, created_by_user_id, is_active)
                    VALUES (:title, :body, :audience_type, :customer_id, :published_at, :created_by_user_id, :is_active)';
            $pdo->prepare($sql)->execute($payload);
            $id = (int) $pdo->lastInsertId();
            self::syncTargets($id, $data);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): void
    {
        $payload = self::payload($data);
        $payload['id'] = $id;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $sql = 'UPDATE announcements
                    SET title=:title, body=:body, audience_type=:audience_type, customer_id=:customer_id, published_at=:published_at, is_active=:is_active
                    WHERE id=:id AND deleted_at IS NULL';
            $pdo->prepare($sql)->execute($payload);
            self::syncTargets($id, $data);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void
    {
        db()->prepare('UPDATE announcements SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
    }

    public static function forContact(array $contact): array
    {
        $stmt = db()->prepare("
            SELECT a.*, ar.read_at,
                (SELECT COUNT(*) FROM announcement_attachments aa WHERE aa.announcement_id = a.id AND aa.deleted_at IS NULL) AS attachment_count
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.contact_id = ?
            WHERE a.deleted_at IS NULL
              AND a.is_active = 1
              AND a.published_at <= NOW()
              AND (
                  a.audience_type = 'all'
                  OR (a.audience_type = 'customer' AND (a.customer_id = ? OR EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?)))
                  OR (a.audience_type = 'customers' AND EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?))
              )
            ORDER BY a.published_at DESC, a.id DESC
        ");
        $customerId = (int) $contact['customer_id'];
        $stmt->execute([(int) $contact['id'], $customerId, $customerId, $customerId]);
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
              AND (
                  a.audience_type = 'all'
                  OR (a.audience_type = 'customer' AND (a.customer_id = ? OR EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?)))
                  OR (a.audience_type = 'customers' AND EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?))
              )
            LIMIT 1
        ");
        $customerId = (int) $contact['customer_id'];
        $stmt->execute([(int) $contact['id'], $id, $customerId, $customerId, $customerId]);
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
              AND (
                  a.audience_type = 'all'
                  OR (a.audience_type = 'customer' AND (a.customer_id = ? OR EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?)))
                  OR (a.audience_type = 'customers' AND EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?))
              )
        ");
        $customerId = (int) $contact['customer_id'];
        $stmt->execute([(int) $contact['id'], $customerId, $customerId, $customerId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $announcementId, int $contactId): void
    {
        $stmt = db()->prepare('INSERT IGNORE INTO announcement_reads (announcement_id, contact_id, read_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$announcementId, $contactId]);
    }

    public static function audienceLabel(array $announcement): string
    {
        if (($announcement['audience_type'] ?? '') === 'customers') {
            return 'مشتریان منتخب: ' . ($announcement['target_customer_names'] ?? 'نامشخص');
        }
        if (($announcement['audience_type'] ?? '') === 'customer') {
            return 'مشتری: ' . ($announcement['customer_name'] ?? 'نامشخص');
        }
        return 'همه مشتریان';
    }

    public static function attachments(int $announcementId): array
    {
        $stmt = db()->prepare('SELECT * FROM announcement_attachments WHERE announcement_id = ? AND deleted_at IS NULL ORDER BY id DESC');
        $stmt->execute([$announcementId]);
        return $stmt->fetchAll();
    }

    public static function addAttachments(int $announcementId, array $files): void
    {
        if (!$files) {
            return;
        }
        $stmt = db()->prepare('INSERT INTO announcement_attachments (announcement_id, file_path, file_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)');
        foreach ($files as $file) {
            $stmt->execute([
                $announcementId,
                $file['path'],
                $file['name'],
                $file['mime'],
                (int) $file['size'],
            ]);
        }
    }

    public static function attachment(int $id): ?array
    {
        $stmt = db()->prepare('SELECT aa.*, a.title FROM announcement_attachments aa JOIN announcements a ON a.id = aa.announcement_id WHERE aa.id = ? AND aa.deleted_at IS NULL AND a.deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function attachmentForContact(int $id, array $contact): ?array
    {
        $stmt = db()->prepare("
            SELECT aa.*, a.title
            FROM announcement_attachments aa
            JOIN announcements a ON a.id = aa.announcement_id
            WHERE aa.id = ?
              AND aa.deleted_at IS NULL
              AND a.deleted_at IS NULL
              AND a.is_active = 1
              AND a.published_at <= NOW()
              AND (
                  a.audience_type = 'all'
                  OR (a.audience_type = 'customer' AND (a.customer_id = ? OR EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?)))
                  OR (a.audience_type = 'customers' AND EXISTS (SELECT 1 FROM announcement_targets tgt WHERE tgt.announcement_id = a.id AND tgt.customer_id = ?))
              )
            LIMIT 1
        ");
        $customerId = (int) $contact['customer_id'];
        $stmt->execute([$id, $customerId, $customerId, $customerId]);
        return $stmt->fetch() ?: null;
    }

    public static function deleteAttachment(int $id): ?array
    {
        $attachment = self::attachment($id);
        if (!$attachment) {
            return null;
        }
        db()->prepare('UPDATE announcement_attachments SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
        return $attachment;
    }

    private static function payload(array $data): array
    {
        $audienceType = in_array(($data['audience_type'] ?? 'all'), ['all', 'customer', 'customers'], true) ? (string) $data['audience_type'] : 'all';
        $targetIds = self::targetCustomerIds($data);
        $customerId = $audienceType === 'customer' ? ($targetIds[0] ?? (int) ($data['customer_id'] ?? 0)) : null;
        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'body' => sanitize_rich_html($data['body'] ?? ''),
            'audience_type' => $audienceType,
            'customer_id' => $customerId ?: null,
            'published_at' => db_date($data['published_at'] ?? null) ?: date('Y-m-d'),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ];
    }

    private static function syncTargets(int $announcementId, array $data): void
    {
        db()->prepare('DELETE FROM announcement_targets WHERE announcement_id = ?')->execute([$announcementId]);
        $audienceType = in_array(($data['audience_type'] ?? 'all'), ['customer', 'customers'], true) ? (string) $data['audience_type'] : 'all';
        if ($audienceType === 'all') {
            return;
        }

        $stmt = db()->prepare('INSERT IGNORE INTO announcement_targets (announcement_id, customer_id) VALUES (?, ?)');
        foreach (self::targetCustomerIds($data) as $customerId) {
            if (Customer::find($customerId)) {
                $stmt->execute([$announcementId, $customerId]);
            }
        }
    }

    private static function targetCustomerIds(array $data): array
    {
        $raw = $data['target_customer_ids'] ?? ($data['customer_id'] ?? []);
        $values = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}
