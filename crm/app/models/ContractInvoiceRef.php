<?php

declare(strict_types=1);

/**
 * Reference-only invoices. This does not issue accounting invoices or record
 * money receipts. Every mutation locks the parent contract so allocations
 * cannot overrun quantities when concurrent users submit the same item.
 */
class ContractInvoiceRef
{
    public static function byContract(int $contractId): array
    {
        $stmt = db()->prepare('SELECT * FROM contract_invoice_refs
            WHERE contract_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    public static function allocationsByContract(int $contractId): array
    {
        $stmt = db()->prepare('SELECT il.*, ir.invoice_number, i.item_title
            FROM contract_invoice_lines il
            JOIN contract_invoice_refs ir ON ir.id = il.invoice_ref_id AND ir.contract_id = il.contract_id
            JOIN contract_items i ON i.id = il.contract_item_id AND i.contract_id = il.contract_id
            WHERE il.contract_id = ? AND ir.deleted_at IS NULL AND i.deleted_at IS NULL
            ORDER BY ir.invoice_number, il.id');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    public static function create(int $contractId, string $number, ?string $date = null): int
    {
        $number = trim($number);
        if ($contractId <= 0 || $number === '' || mb_strlen($number) > 120) {
            throw new InvalidArgumentException('شماره فاکتور یا قرارداد معتبر نیست.');
        }
        $when = db_date($date);
        if ($date !== null && trim($date) !== '' && $when === null) {
            throw new InvalidArgumentException('تاریخ فاکتور معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::lockContract($pdo, $contractId);
            $stmt = $pdo->prepare('INSERT INTO contract_invoice_refs (contract_id, invoice_number, invoice_date) VALUES (?, ?, ?)');
            $stmt->execute([$contractId, $number, $when]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function allocate(int $contractId, int $invoiceId, int $itemId, int $quantity): int
    {
        if ($contractId <= 0 || $invoiceId <= 0 || $itemId <= 0 || $quantity <= 0 || $quantity > 1000000000) {
            throw new InvalidArgumentException('شناسه یا تعداد تخصیص معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::lockContract($pdo, $contractId);
            $stmt = $pdo->prepare('SELECT id FROM contract_invoice_refs
                WHERE id = ? AND contract_id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$invoiceId, $contractId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('فاکتور به این قرارداد تعلق ندارد.');
            }
            $stmt = $pdo->prepare('SELECT quantity FROM contract_items
                WHERE id = ? AND contract_id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$itemId, $contractId]);
            $committed = $stmt->fetchColumn();
            if ($committed === false) {
                throw new RuntimeException('قلم فروش به این قرارداد تعلق ندارد.');
            }
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(invoiced_quantity),0) FROM contract_invoice_lines
                WHERE contract_id = ? AND contract_item_id = ? AND reversed_at IS NULL');
            $stmt->execute([$contractId, $itemId]);
            $allocated = (int) $stmt->fetchColumn();
            if ($allocated + $quantity > (int) $committed) {
                throw new DomainException('تعداد فاکتورشده از تعداد تعهد قلم فروش بیشتر می‌شود.');
            }
            $stmt = $pdo->prepare('INSERT INTO contract_invoice_lines
                (contract_id, invoice_ref_id, contract_item_id, invoiced_quantity)
                VALUES (?, ?, ?, ?)');
            $stmt->execute([$contractId, $invoiceId, $itemId, $quantity]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Reverses an invoice allocation without removing its audit record.
     * A reversed allocation no longer counts toward invoiced quantity.
     */
    public static function reverseAllocation(int $contractId, int $allocationId, string $reason, int $actorId): void
    {
        $reason = trim($reason);
        if ($contractId <= 0 || $allocationId <= 0 || $actorId <= 0
            || $reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('شناسه یا دلیل ابطال تخصیص معتبر نیست.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::lockContract($pdo, $contractId);
            $stmt = $pdo->prepare('SELECT id FROM contract_invoice_lines
                WHERE id = ? AND contract_id = ? AND reversed_at IS NULL FOR UPDATE');
            $stmt->execute([$allocationId, $contractId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('تخصیص فعال متعلق به این قرارداد پیدا نشد.');
            }
            $stmt = $pdo->prepare('UPDATE contract_invoice_lines
                SET reversed_at = CURRENT_TIMESTAMP, reversed_by_user_id = ?, reversal_reason = ?
                WHERE id = ? AND contract_id = ? AND reversed_at IS NULL');
            $stmt->execute([$actorId, $reason, $allocationId, $contractId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('تخصیص پیش‌تر تغییر کرده است.');
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function lockContract(PDO $pdo, int $contractId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$contractId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('قرارداد یا فروش پیدا نشد.');
        }
    }
}
