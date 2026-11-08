<?php

declare(strict_types=1);

final class DeliveryReceipt
{
    public static function byContractItem(int $itemId): array
    {
        $stmt = db()->prepare('SELECT DISTINCT dr.*, creator.name AS creator_name, approver.name AS approver_name
            FROM delivery_receipts dr
            JOIN delivery_receipt_items dri ON dri.delivery_receipt_id = dr.id AND dri.contract_id = dr.contract_id
            JOIN installation_records ir ON ir.id = dri.installation_record_id AND ir.contract_id = dr.contract_id
            LEFT JOIN users creator ON creator.id = dr.created_by_user_id
            LEFT JOIN users approver ON approver.id = dr.approved_by_user_id
            WHERE ir.contract_item_id = ? ORDER BY dr.created_at DESC, dr.id DESC');
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    public static function createApproved(array $installationIds, array $data, int $actorId): int
    {
        self::assertOperationsMutation();
        $installationIds = array_values(array_unique(array_filter(array_map('intval', $installationIds))));
        $number = trim((string) ($data['receipt_number'] ?? ''));
        $receiver = trim((string) ($data['receiver_name'] ?? ''));
        $deliveredAt = self::normalizeDateTime($data['delivered_at'] ?? null);
        if (!$installationIds || count($installationIds) > 200 || $number === '' || mb_strlen($number) > 120
            || $receiver === '' || mb_strlen($receiver) > 190 || $deliveredAt === null || $actorId <= 0) {
            throw new InvalidArgumentException('اطلاعات رسید یا دستگاه‌های انتخاب‌شده معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($installationIds), '?'));
            $stmt = $pdo->prepare("SELECT ir.id, ir.contract_id, ir.contract_item_id, ir.customer_id, ir.status
                FROM installation_records ir
                WHERE ir.id IN ({$placeholders}) AND ir.deleted_at IS NULL ORDER BY ir.contract_id, ir.id FOR UPDATE");
            $stmt->execute($installationIds);
            $records = $stmt->fetchAll();
            if (count($records) !== count($installationIds)) {
                throw new RuntimeException('یک یا چند نصب انتخاب‌شده پیدا نشد.');
            }
            $contractId = (int) $records[0]['contract_id'];
            $customerId = (int) $records[0]['customer_id'];
            foreach ($records as $record) {
                if ((int) $record['contract_id'] !== $contractId || (int) $record['customer_id'] !== $customerId
                    || $record['status'] !== 'approved') {
                    throw new DomainException('رسید فقط برای نصب‌های تأییدشده یک قرارداد و مشتری قابل ثبت است.');
                }
            }
            $pdo->prepare('SELECT id FROM contracts WHERE id = ? AND customer_id = ? AND deleted_at IS NULL FOR UPDATE')
                ->execute([$contractId, $customerId]);
            $existing = $pdo->prepare("SELECT COUNT(*) FROM delivery_receipt_items dri
                JOIN delivery_receipts dr ON dr.id = dri.delivery_receipt_id AND dr.status = 'approved'
                WHERE dri.installation_record_id IN ({$placeholders}) AND dri.revoked_at IS NULL");
            $existing->execute($installationIds);
            if ((int) $existing->fetchColumn() > 0) {
                throw new DomainException('حداقل یکی از نصب‌ها قبلاً تحویل قطعی شده است.');
            }
            $pdo->prepare('INSERT INTO delivery_receipts
                (contract_id, customer_id, receipt_number, delivered_at, receiver_name, evidence_path,
                 evidence_name, created_by_user_id, approved_by_user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                    $contractId, $customerId, $number, $deliveredAt, $receiver,
                    trim((string) ($data['evidence_path'] ?? '')) ?: null,
                    trim((string) ($data['evidence_name'] ?? '')) ?: null,
                    $actorId, $actorId,
                ]);
            $receiptId = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO delivery_receipt_items
                (contract_id, delivery_receipt_id, installation_record_id) VALUES (?, ?, ?)');
            foreach ($records as $record) {
                $insert->execute([$contractId, $receiptId, $record['id']]);
            }
            self::recordCompletions($pdo, $contractId, $actorId);
            $pdo->commit();
            return $receiptId;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function cancel(int $receiptId, string $reason, int $actorId): void
    {
        self::assertOperationsMutation();
        $reason = trim($reason);
        if ($receiptId <= 0 || $actorId <= 0 || $reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('شناسه یا دلیل ابطال رسید معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, contract_id FROM delivery_receipts
                WHERE id = ? AND status = 'approved' FOR UPDATE");
            $stmt->execute([$receiptId]);
            $receipt = $stmt->fetch();
            if (!$receipt) {
                throw new RuntimeException('رسید فعال پیدا نشد.');
            }
            $pdo->prepare("UPDATE delivery_receipts SET status = 'cancelled', cancelled_by_user_id = ?,
                cancelled_at = CURRENT_TIMESTAMP, cancellation_reason = ? WHERE id = ?")
                ->execute([$actorId, $reason, $receiptId]);
            $pdo->prepare('UPDATE delivery_receipt_items SET revoked_at = CURRENT_TIMESTAMP
                WHERE delivery_receipt_id = ? AND revoked_at IS NULL')->execute([$receiptId]);
            $items = $pdo->prepare('SELECT DISTINCT ir.contract_item_id FROM delivery_receipt_items dri
                JOIN installation_records ir ON ir.id = dri.installation_record_id WHERE dri.delivery_receipt_id = ?');
            $items->execute([$receiptId]);
            foreach ($items->fetchAll() as $item) {
                $itemId = (int) $item['contract_item_id'];
                $pdo->prepare('INSERT INTO fulfillment_events
                    (contract_id, contract_item_id, event_type, event_key, details, created_by_user_id)
                    VALUES (?, ?, ?, ?, ?, ?)')->execute([
                        $receipt['contract_id'], $itemId, 'receipt_cancelled',
                        "receipt-cancelled:{$receiptId}:{$itemId}", $reason, $actorId,
                    ]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function recordCompletions(PDO $pdo, int $contractId, int $actorId): void
    {
        $stmt = $pdo->prepare("SELECT i.id, i.quantity,
                COUNT(DISTINCT CASE WHEN ir.installation_kind = 'sale' AND dr.status = 'approved' AND dri.revoked_at IS NULL THEN ir.id END) delivered
            FROM contract_items i
            LEFT JOIN installation_records ir ON ir.contract_item_id = i.id AND ir.contract_id = i.contract_id
            LEFT JOIN delivery_receipt_items dri ON dri.installation_record_id = ir.id AND dri.revoked_at IS NULL
            LEFT JOIN delivery_receipts dr ON dr.id = dri.delivery_receipt_id AND dr.status = 'approved'
            WHERE i.contract_id = ? AND i.item_type = 'device' AND i.requires_installation = 1 AND i.deleted_at IS NULL
            GROUP BY i.id, i.quantity");
        $stmt->execute([$contractId]);
        $insert = $pdo->prepare("INSERT IGNORE INTO fulfillment_events
            (contract_id, contract_item_id, event_type, event_key, finance_followup_status, details, created_by_user_id)
            VALUES (?, ?, 'obligation_completed', ?, 'pending', ?, ?)");
        foreach ($stmt->fetchAll() as $row) {
            if ((int) $row['delivered'] >= (int) $row['quantity']) {
                $insert->execute([
                    $contractId, $row['id'], 'obligation-completed:' . $row['id'],
                    'تعهد تحویل تکمیل شد؛ بررسی مالی لازم است.', $actorId,
                ]);
            }
        }
    }

    private static function assertOperationsMutation(): void
    {
        if (!in_array(current_user_role(), ['admin', 'operations'], true)) {
            throw new RuntimeException('اجازه مدیریت رسید تحویل را ندارید.');
        }
    }

    private static function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
