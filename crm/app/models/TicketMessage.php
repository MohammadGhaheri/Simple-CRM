<?php

declare(strict_types=1);

class TicketMessage
{
    public static function byTicket(int $ticketId): array
    {
        $stmt = db()->prepare("
            SELECT tm.*,
                u.name AS user_name,
                u.avatar_path AS user_avatar_path,
                ct.contact_name,
                ct.avatar_path AS contact_avatar_path
            FROM ticket_messages tm
            LEFT JOIN users u ON u.id = tm.sender_user_id
            LEFT JOIN contacts ct ON ct.id = tm.sender_contact_id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC, tm.id ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public static function markReadForContact(int $ticketId, int $contactId): void
    {
        db()->prepare("
            UPDATE ticket_messages
            SET contact_read_at = COALESCE(contact_read_at, CURRENT_TIMESTAMP)
            WHERE ticket_id = ?
              AND sender_type = 'user'
              AND contact_read_at IS NULL
              AND ticket_id IN (SELECT id FROM tickets WHERE id = ? AND contact_id = ?)
        ")->execute([$ticketId, $ticketId, $contactId]);
    }

    public static function createFromContact(int $ticketId, int $contactId, string $message, ?array $attachment = null): int
    {
        return self::create([
            'ticket_id' => $ticketId,
            'sender_type' => 'contact',
            'sender_contact_id' => $contactId,
            'sender_user_id' => null,
            'message' => $message,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_name' => $attachment['name'] ?? null,
            'attachment_mime' => $attachment['mime'] ?? null,
            'attachment_size' => $attachment['size'] ?? null,
        ]);
    }

    public static function createFromUser(int $ticketId, int $userId, string $message, ?array $attachment = null): int
    {
        return self::create([
            'ticket_id' => $ticketId,
            'sender_type' => 'user',
            'sender_contact_id' => null,
            'sender_user_id' => $userId,
            'message' => $message,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_name' => $attachment['name'] ?? null,
            'attachment_mime' => $attachment['mime'] ?? null,
            'attachment_size' => $attachment['size'] ?? null,
        ]);
    }

    private static function create(array $data): int
    {
        $sql = 'INSERT INTO ticket_messages (ticket_id, sender_type, sender_contact_id, sender_user_id, message, attachment_path, attachment_name, attachment_mime, attachment_size)
                VALUES (:ticket_id, :sender_type, :sender_contact_id, :sender_user_id, :message, :attachment_path, :attachment_name, :attachment_mime, :attachment_size)';
        db()->prepare($sql)->execute([
            'ticket_id' => (int) $data['ticket_id'],
            'sender_type' => $data['sender_type'],
            'sender_contact_id' => !empty($data['sender_contact_id']) ? (int) $data['sender_contact_id'] : null,
            'sender_user_id' => !empty($data['sender_user_id']) ? (int) $data['sender_user_id'] : null,
            'message' => trim((string) ($data['message'] ?? '')),
            'attachment_path' => $data['attachment_path'] ?: null,
            'attachment_name' => $data['attachment_name'] ?: null,
            'attachment_mime' => $data['attachment_mime'] ?: null,
            'attachment_size' => !empty($data['attachment_size']) ? (int) $data['attachment_size'] : null,
        ]);

        db()->prepare('UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $data['ticket_id']]);
        return (int) db()->lastInsertId();
    }
}
