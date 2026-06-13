<?php

declare(strict_types=1);

class Activity
{
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT a.*, c.customer_name, d.deal_name, ct.contract_title, u.name AS owner_name FROM activities a JOIN customers c ON c.id = a.customer_id LEFT JOIN deals d ON d.id = a.deal_id LEFT JOIN contracts ct ON ct.id = a.contract_id LEFT JOIN users u ON u.id = a.owner_user_id WHERE a.deleted_at IS NULL AND c.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (a.summary LIKE ? OR c.customer_name LIKE ? OR d.deal_name LIKE ? OR ct.contract_title LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q);
        }
        foreach (['status', 'activity_type', 'owner_user_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND a.$field = ?";
                $params[] = $filters[$field];
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND a.activity_date >= ?';
            $params[] = db_date($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND a.activity_date <= ?';
            $params[] = db_date($filters['date_to']);
        }
        $sql .= ' ORDER BY a.activity_date DESC, a.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byCustomer(int $customerId): array
    {
        $stmt = db()->prepare('SELECT a.*, d.deal_name, ct.contract_title FROM activities a LEFT JOIN deals d ON d.id = a.deal_id LEFT JOIN contracts ct ON ct.id = a.contract_id WHERE a.customer_id = ? AND a.deleted_at IS NULL ORDER BY a.activity_date DESC, a.id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function byDeal(int $dealId): array
    {
        $stmt = db()->prepare('SELECT a.*, c.customer_name, ct.contract_title FROM activities a JOIN customers c ON c.id = a.customer_id LEFT JOIN contracts ct ON ct.id = a.contract_id WHERE a.deal_id = ? AND a.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY a.activity_date DESC, a.id DESC');
        $stmt->execute([$dealId]);
        return $stmt->fetchAll();
    }

    public static function byContract(int $contractId): array
    {
        $stmt = db()->prepare('SELECT a.*, c.customer_name, d.deal_name FROM activities a JOIN customers c ON c.id = a.customer_id LEFT JOIN deals d ON d.id = a.deal_id WHERE a.contract_id = ? AND a.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY a.activity_date DESC, a.id DESC');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM activities WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findForOwner(int $id, int $ownerId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM activities WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $ownerId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO activities (customer_id, deal_id, contract_id, activity_date, activity_type, summary, next_action, next_followup_date, owner_user_id, status, notes) VALUES (:customer_id, :deal_id, :contract_id, :activity_date, :activity_type, :summary, :next_action, :next_followup_date, :owner_user_id, :status, :notes)';
        db()->prepare($sql)->execute(self::payload($data));
        self::syncCustomerFollowup($data);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE activities SET customer_id=:customer_id, deal_id=:deal_id, contract_id=:contract_id, activity_date=:activity_date, activity_type=:activity_type, summary=:summary, next_action=:next_action, next_followup_date=:next_followup_date, owner_user_id=:owner_user_id, status=:status, notes=:notes WHERE id=:id';
        $payload = self::payload($data);
        $payload['id'] = $id;
        db()->prepare($sql)->execute($payload);
        self::syncCustomerFollowup($data);
    }

    public static function delete(int $id): void
    {
        db()->prepare('UPDATE activities SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
    }

    public static function recent(int $limit = 6): array
    {
        $stmt = db()->prepare('SELECT a.*, c.customer_name FROM activities a JOIN customers c ON c.id = a.customer_id WHERE a.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY a.activity_date DESC, a.id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function upcoming(int $limit = 6): array
    {
        $stmt = db()->prepare("SELECT a.*, c.customer_name FROM activities a JOIN customers c ON c.id = a.customer_id WHERE a.next_followup_date >= CURDATE() AND a.status <> 'Done' AND a.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY a.next_followup_date ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function overdueCount(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM activities a JOIN customers c ON c.id = a.customer_id WHERE a.next_followup_date < CURDATE() AND a.status <> 'Done' AND a.deleted_at IS NULL AND c.deleted_at IS NULL")->fetchColumn();
    }

    public static function agendaForOwner(int $ownerId, string $bucket): array
    {
        $where = [
            'a.owner_user_id = ?',
            "a.status <> 'Done'",
            'a.next_followup_date IS NOT NULL',
            'a.deleted_at IS NULL',
            'c.deleted_at IS NULL',
        ];
        $params = [$ownerId];

        if ($bucket === 'overdue') {
            $where[] = 'a.next_followup_date < CURDATE()';
        } elseif ($bucket === 'today') {
            $where[] = 'a.next_followup_date = CURDATE()';
        } elseif ($bucket === 'upcoming') {
            $where[] = 'a.next_followup_date > CURDATE()';
            $where[] = 'a.next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        }

        $sql = 'SELECT a.*, c.customer_name, d.deal_name, ct.contract_title
                FROM activities a
                JOIN customers c ON c.id = a.customer_id
                LEFT JOIN deals d ON d.id = a.deal_id
                LEFT JOIN contracts ct ON ct.id = a.contract_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.next_followup_date ASC, a.id ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function agendaCountsForOwner(int $ownerId): array
    {
        $stmt = db()->prepare("
            SELECT
                SUM(CASE WHEN next_followup_date < CURDATE() AND status <> 'Done' THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN next_followup_date = CURDATE() AND status <> 'Done' THEN 1 ELSE 0 END) AS today_count,
                SUM(CASE WHEN next_followup_date > CURDATE() AND next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status <> 'Done' THEN 1 ELSE 0 END) AS upcoming_count,
                SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count
            FROM activities
            WHERE owner_user_id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch() ?: [];
        return [
            'overdue' => (int) ($row['overdue_count'] ?? 0),
            'today' => (int) ($row['today_count'] ?? 0),
            'upcoming' => (int) ($row['upcoming_count'] ?? 0),
            'open' => (int) ($row['open_count'] ?? 0),
        ];
    }

    public static function markDoneForOwner(int $id, int $ownerId): void
    {
        $stmt = db()->prepare("UPDATE activities SET status = 'Done' WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, $ownerId]);
    }

    public static function transferOwner(int $fromUserId, int $toUserId): int
    {
        $stmt = db()->prepare("UPDATE activities SET owner_user_id = ? WHERE owner_user_id = ? AND status <> 'Done' AND deleted_at IS NULL");
        $stmt->execute([$toUserId, $fromUserId]);
        return $stmt->rowCount();
    }

    public static function createOrUpdateContractRenewal(array $contract): void
    {
        $contractId = (int) ($contract['id'] ?? 0);
        if ($contractId <= 0) {
            return;
        }

        if (!in_array(($contract['status'] ?? 'Active'), ['Active', 'Renewal Due'], true)) {
            $stmt = db()->prepare("UPDATE activities SET status = 'Cancelled' WHERE contract_id = ? AND activity_type = 'Contract Renewal' AND status <> 'Done' AND deleted_at IS NULL");
            $stmt->execute([$contractId]);
            return;
        }

        if (empty($contract['renewal_reminder_date'])) {
            return;
        }

        $payload = [
            'customer_id' => (int) $contract['customer_id'],
            'deal_id' => !empty($contract['deal_id']) ? (int) $contract['deal_id'] : null,
            'contract_id' => $contractId,
            'activity_date' => date('Y-m-d'),
            'activity_type' => 'Contract Renewal',
            'summary' => 'پیگیری تمدید قرارداد: ' . ($contract['contract_title'] ?? ''),
            'next_action' => 'بررسی تمدید قرارداد پیش از تاریخ پایان ' . fa_date($contract['end_date'] ?? ''),
            'next_followup_date' => $contract['renewal_reminder_date'],
            'owner_user_id' => (int) ($contract['owner_user_id'] ?? current_user_id()),
            'status' => 'Open',
            'notes' => 'این فعالیت به صورت خودکار از قرارداد ساخته یا به‌روزرسانی شده است.',
        ];

        $existing = db()->prepare("SELECT id FROM activities WHERE contract_id = ? AND activity_type = 'Contract Renewal' AND status <> 'Done' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
        $existing->execute([$contractId]);
        $activityId = (int) ($existing->fetchColumn() ?: 0);
        if ($activityId > 0) {
            self::update($activityId, $payload);
            return;
        }

        self::create($payload);
    }

    private static function payload(array $data): array
    {
        return [
            'customer_id' => (int) ($data['customer_id'] ?? 0),
            'deal_id' => !empty($data['deal_id']) ? (int) $data['deal_id'] : null,
            'contract_id' => !empty($data['contract_id']) ? (int) $data['contract_id'] : null,
            'activity_date' => db_date($data['activity_date'] ?? null) ?: date('Y-m-d'),
            'activity_type' => $data['activity_type'] ?? 'Follow-up',
            'summary' => trim($data['summary'] ?? ''),
            'next_action' => trim($data['next_action'] ?? ''),
            'next_followup_date' => db_date($data['next_followup_date'] ?? null),
            'owner_user_id' => (int) ($data['owner_user_id'] ?? current_user_id()),
            'status' => $data['status'] ?? 'Open',
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    private static function syncCustomerFollowup(array $data): void
    {
        if (empty($data['customer_id'])) {
            return;
        }
        $stmt = db()->prepare('UPDATE customers SET last_followup_date = ?, next_followup_date = COALESCE(?, next_followup_date) WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([db_date($data['activity_date'] ?? null) ?: date('Y-m-d'), db_date($data['next_followup_date'] ?? null), (int) $data['customer_id']]);
    }
}
