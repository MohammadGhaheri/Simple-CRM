<?php

declare(strict_types=1);

class Ticket
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT t.*, c.customer_name, c.is_vip, ct.contact_name, u.name AS assigned_name
                FROM tickets t
                JOIN customers c ON c.id = t.customer_id
                JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN users u ON u.id = t.assigned_user_id
                WHERE 1=1';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (t.ticket_code LIKE ? OR t.subject LIKE ? OR c.customer_name LIKE ? OR ct.contact_name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q);
        }
        foreach (['status', 'priority', 'category'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND t.$field = ?";
                $params[] = $filters[$field];
            }
        }
        $sql .= ' ORDER BY t.updated_at DESC, t.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byContact(int $contactId): array
    {
        $stmt = db()->prepare('SELECT * FROM tickets WHERE contact_id = ? ORDER BY updated_at DESC, id DESC');
        $stmt->execute([$contactId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT t.*, c.customer_name, c.is_vip, ct.contact_name, u.name AS assigned_name
            FROM tickets t
            JOIN customers c ON c.id = t.customer_id
            JOIN contacts ct ON ct.id = t.contact_id
            LEFT JOIN users u ON u.id = t.assigned_user_id
            WHERE t.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findForContact(int $id, int $contactId): ?array
    {
        $stmt = db()->prepare('SELECT t.*, c.customer_name, c.is_vip FROM tickets t JOIN customers c ON c.id = t.customer_id WHERE t.id = ? AND t.contact_id = ?');
        $stmt->execute([$id, $contactId]);
        return $stmt->fetch() ?: null;
    }

    public static function createFromPortal(array $contact, array $data): int
    {
        $assignedUserId = self::defaultAssignedUserId($contact);
        $sql = 'INSERT INTO tickets (ticket_code, customer_id, contact_id, subject, category, priority, description, assigned_user_id) VALUES (:ticket_code, :customer_id, :contact_id, :subject, :category, :priority, :description, :assigned_user_id)';
        db()->prepare($sql)->execute([
            'ticket_code' => self::nextCode(),
            'customer_id' => (int) $contact['customer_id'],
            'contact_id' => (int) $contact['id'],
            'subject' => trim($data['subject'] ?? ''),
            'category' => self::validCategory($data['category'] ?? 'Support'),
            'priority' => self::validPriority($data['priority'] ?? 'Normal'),
            'description' => trim($data['description'] ?? ''),
            'assigned_user_id' => $assignedUserId,
        ]);
        $ticketId = (int) db()->lastInsertId();
        if (class_exists('TicketMessage')) {
            TicketMessage::createFromContact($ticketId, (int) $contact['id'], trim($data['description'] ?? ''), $data['attachment'] ?? null);
        }
        return $ticketId;
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE tickets SET status=:status, priority=:priority, category=:category, assigned_user_id=:assigned_user_id, response=:response WHERE id=:id';
        db()->prepare($sql)->execute([
            'status' => self::validStatus($data['status'] ?? 'Open'),
            'priority' => self::validPriority($data['priority'] ?? 'Normal'),
            'category' => self::validCategory($data['category'] ?? 'Support'),
            'assigned_user_id' => !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            'response' => trim($data['response'] ?? ''),
            'id' => $id,
        ]);
    }

    public static function updateMeta(int $id, array $data): void
    {
        $sql = 'UPDATE tickets SET status=:status, priority=:priority, category=:category, assigned_user_id=:assigned_user_id WHERE id=:id';
        db()->prepare($sql)->execute([
            'status' => self::validStatus($data['status'] ?? 'Open'),
            'priority' => self::validPriority($data['priority'] ?? 'Normal'),
            'category' => self::validCategory($data['category'] ?? 'Support'),
            'assigned_user_id' => !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            'id' => $id,
        ]);
    }

    public static function close(int $id): void
    {
        db()->prepare("UPDATE tickets SET status = 'Closed' WHERE id = ?")->execute([$id]);
    }

    public static function closeForContact(int $id, int $contactId): void
    {
        db()->prepare("UPDATE tickets SET status = 'Closed' WHERE id = ? AND contact_id = ?")->execute([$id, $contactId]);
    }

    public static function isClosed(array $ticket): bool
    {
        return in_array(($ticket['status'] ?? ''), ['Closed', 'Resolved'], true);
    }

    public static function needsReviewCount(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Resolved','Closed')")->fetchColumn();
    }

    public static function statuses(): array
    {
        return function_exists('option_values') ? option_values('options_ticket_statuses') : ['Open', 'In Progress', 'Waiting Customer', 'Resolved', 'Closed'];
    }

    public static function priorities(): array
    {
        return function_exists('option_values') ? option_values('options_ticket_priorities') : ['Low', 'Normal', 'High', 'Urgent'];
    }

    public static function categories(): array
    {
        return function_exists('option_values') ? option_values('options_ticket_categories') : ['Support', 'Request', 'Bug', 'Training', 'Billing', 'Other'];
    }

    public static function label(string $value): string
    {
        if (function_exists('option_pairs')) {
            foreach (['options_ticket_statuses', 'options_ticket_priorities', 'options_ticket_categories'] as $key) {
                $pairs = option_pairs($key);
                if (isset($pairs[$value])) {
                    return $pairs[$value];
                }
            }
        }

        $labels = [
            'Open' => 'باز',
            'In Progress' => 'در حال بررسی',
            'Waiting Customer' => 'در انتظار مشتری',
            'Resolved' => 'حل شده',
            'Closed' => 'بسته',
            'Low' => 'کم',
            'Normal' => 'عادی',
            'High' => 'زیاد',
            'Urgent' => 'فوری',
            'Support' => 'پشتیبانی',
            'Request' => 'درخواست',
            'Bug' => 'خطا',
            'Training' => 'آموزش',
            'Billing' => 'مالی',
            'Other' => 'سایر',
        ];
        return $labels[$value] ?? $value;
    }

    private static function nextCode(): string
    {
        return 'TCK-' . date('ymd') . '-' . random_int(1000, 9999);
    }

    private static function defaultAssignedUserId(array $contact): ?int
    {
        if (!empty($contact['default_support_user_id'])) {
            return (int) $contact['default_support_user_id'];
        }
        $settings = class_exists('Setting') ? Setting::all() : [];
        return !empty($settings['sms_default_assigned_user_id']) ? (int) $settings['sms_default_assigned_user_id'] : null;
    }

    private static function validStatus(string $value): string
    {
        return in_array($value, self::statuses(), true) ? $value : (self::statuses()[0] ?? 'Open');
    }

    private static function validPriority(string $value): string
    {
        return in_array($value, self::priorities(), true) ? $value : (self::priorities()[0] ?? 'Normal');
    }

    private static function validCategory(string $value): string
    {
        return in_array($value, self::categories(), true) ? $value : (self::categories()[0] ?? 'Support');
    }
}
