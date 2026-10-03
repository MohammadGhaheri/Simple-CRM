<?php

declare(strict_types=1);

class ContractPayment
{
    public static function byContract(int $contractId, ?PDO $pdo = null): array
    {
        $stmt = ($pdo ?: db())->prepare('SELECT cp.*, creator.name AS created_by_name FROM contract_payments cp LEFT JOIN users creator ON creator.id = cp.created_by_user_id WHERE cp.contract_id = ? AND cp.deleted_at IS NULL ORDER BY cp.payment_date DESC, cp.id DESC');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, ?PDO $pdo = null): ?array
    {
        $stmt = ($pdo ?: db())->prepare('SELECT * FROM contract_payments WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $actorId, ?PDO $pdo = null): int
    {
        $pdo = $pdo ?: db();
        return self::mutate($pdo, (int) ($data['contract_id'] ?? 0), null, function (string $amount) use ($pdo, $data, $actorId): int {
            $stmt = $pdo->prepare('INSERT INTO contract_payments (contract_id, amount, payment_date, payment_method, reference_number, notes, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([(int) $data['contract_id'], $amount, self::paymentDate($data), self::paymentMethod($data), self::nullable($data['reference_number'] ?? null), self::nullable($data['notes'] ?? null), $actorId]);
            return (int) $pdo->lastInsertId();
        }, $data);
    }

    public static function update(int $id, array $data, int $actorId, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?: db();
        $payment = self::find($id, $pdo);
        if (!$payment) {
            throw new RuntimeException('دریافتی مورد نظر پیدا نشد.');
        }
        self::mutate($pdo, (int) $payment['contract_id'], $id, function (string $amount) use ($pdo, $data, $actorId, $id): void {
            $stmt = $pdo->prepare('UPDATE contract_payments SET amount=?, payment_date=?, payment_method=?, reference_number=?, notes=?, updated_by_user_id=? WHERE id=? AND deleted_at IS NULL');
            $stmt->execute([$amount, self::paymentDate($data), self::paymentMethod($data), self::nullable($data['reference_number'] ?? null), self::nullable($data['notes'] ?? null), $actorId, $id]);
        }, $data);
    }

    public static function softDelete(int $id, int $actorId, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?: db();
        $payment = self::find($id, $pdo);
        if (!$payment) {
            throw new RuntimeException('دریافتی مورد نظر پیدا نشد.');
        }
        $pdo->beginTransaction();
        try {
            self::lockContract($pdo, (int) $payment['contract_id']);
            $stmt = $pdo->prepare('UPDATE contract_payments SET deleted_at=CURRENT_TIMESTAMP, deleted_by_user_id=? WHERE id=? AND deleted_at IS NULL');
            $stmt->execute([$actorId, $id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('دریافتی مورد نظر دیگر فعال نیست.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function receivedForContract(int $contractId, ?PDO $pdo = null, ?int $excludeId = null): string
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0.00) FROM contract_payments WHERE contract_id = ? AND deleted_at IS NULL';
        $params = [$contractId];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = ($pdo ?: db())->prepare($sql);
        $stmt->execute($params);
        return normalize_money_decimal((string) $stmt->fetchColumn(), true);
    }

    public static function summaryForContract(int $contractId, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?: db();
        $stmt = $pdo->prepare('SELECT contract_amount FROM contracts WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$contractId]);
        $amount = $stmt->fetchColumn();
        if ($amount === false) {
            throw new RuntimeException('قرارداد پیدا نشد.');
        }
        $amount = normalize_money_decimal((string) $amount, true);
        $received = self::receivedForContract($contractId, $pdo);
        return ['contract_amount' => $amount, 'received' => $received, 'balance' => money_subtract($amount, $received)];
    }

    public static function financialRows(array $filters = [], ?PDO $pdo = null): array
    {
        $sql = 'SELECT ct.id, ct.contract_title, ct.contract_number, ct.status, ct.contract_amount, c.customer_name, u.name AS owner_name, COALESCE(SUM(cp.amount), 0.00) AS received_amount FROM contracts ct JOIN customers c ON c.id=ct.customer_id LEFT JOIN users u ON u.id=ct.owner_user_id LEFT JOIN contract_payments cp ON cp.contract_id=ct.id AND cp.deleted_at IS NULL WHERE ct.deleted_at IS NULL AND c.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (c.customer_name LIKE ? OR ct.contract_title LIKE ? OR ct.contract_number LIKE ?)';
            $q = '%' . trim((string) $filters['q']) . '%';
            array_push($params, $q, $q, $q);
        }
        foreach (['status', 'owner_user_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND ct.$field = ?";
                $params[] = $filters[$field];
            }
        }
        $sql .= ' GROUP BY ct.id, ct.contract_title, ct.contract_number, ct.status, ct.contract_amount, c.customer_name, u.name ORDER BY ct.id DESC';
        $stmt = ($pdo ?: db())->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['balance_amount'] = money_subtract((string) $row['contract_amount'], (string) $row['received_amount']);
        }
        return $rows;
    }

    public static function totals(array $rows): array
    {
        $totals = ['contract_amount' => '0.00', 'received' => '0.00', 'balance' => '0.00'];
        foreach ($rows as $row) {
            $totals['contract_amount'] = money_add($totals['contract_amount'], (string) $row['contract_amount']);
            $totals['received'] = money_add($totals['received'], (string) $row['received_amount']);
            $totals['balance'] = money_add($totals['balance'], (string) $row['balance_amount']);
        }
        return $totals;
    }

    private static function mutate(PDO $pdo, int $contractId, ?int $excludeId, callable $write, array $data): mixed
    {
        $amount = normalize_money_decimal((string) ($data['amount'] ?? ''), false);
        $pdo->beginTransaction();
        try {
            $contractAmount = self::lockContract($pdo, $contractId);
            if ($excludeId !== null) {
                $current = $pdo->prepare('SELECT contract_id FROM contract_payments WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
                $current->execute([$excludeId]);
                if ((int) $current->fetchColumn() !== $contractId) {
                    throw new RuntimeException('دریافتی مورد نظر دیگر فعال نیست.');
                }
            }
            $received = self::receivedForContract($contractId, $pdo, $excludeId);
            if (money_compare(money_add($received, $amount), $contractAmount) > 0) {
                throw new DomainException('مبلغ دریافتی از مبلغ قرارداد بیشتر می‌شود.');
            }
            $result = $write($amount);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function lockContract(PDO $pdo, int $contractId): string
    {
        $stmt = $pdo->prepare('SELECT contract_amount FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$contractId]);
        $amount = $stmt->fetchColumn();
        if ($amount === false) {
            throw new RuntimeException('قرارداد پیدا نشد.');
        }
        return normalize_money_decimal((string) $amount, true);
    }

    private static function paymentDate(array $data): string
    {
        $date = db_date($data['payment_date'] ?? null);
        if (!$date) {
            throw new InvalidArgumentException('تاریخ دریافت معتبر نیست.');
        }
        return $date;
    }

    private static function paymentMethod(array $data): string
    {
        $method = trim((string) ($data['payment_method'] ?? ''));
        if (!array_key_exists($method, payment_method_options())) {
            throw new InvalidArgumentException('روش پرداخت معتبر نیست.');
        }
        return $method;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
