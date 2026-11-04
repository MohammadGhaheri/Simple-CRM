<?php

declare(strict_types=1);

/**
 * Line items remain independent of the legacy contracts.vehicle_count field.
 * Every mutation locks the parent contract so invoice allocation, amendments
 * and line totals are serializable for a single contract.
 */
class ContractItem
{
    public static function byContract(int $contractId): array
    {
        $stmt = db()->prepare('SELECT * FROM contract_items WHERE contract_id = ? AND deleted_at IS NULL ORDER BY line_number, id');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    public static function amendmentsForContract(int $contractId): array
    {
        $stmt = db()->prepare('SELECT am.*, i.item_title, u.name AS actor_name
            FROM contract_item_amendments am
            JOIN contract_items i ON i.id = am.contract_item_id
            LEFT JOIN users u ON u.id = am.changed_by_user_id
            WHERE i.contract_id = ?
            ORDER BY am.created_at DESC, am.id DESC');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    /**
     * Multiply fixed-decimal money without float rounding or 64-bit overflow.
     */
    public static function lineAmount(string $unitAmount, int $quantity): string
    {
        if ($quantity < 1 || $quantity > 1000000000) {
            throw new InvalidArgumentException('تعداد قلم معتبر نیست.');
        }
        $minor = money_minor(normalize_money_decimal($unitAmount, true));
        $result = '';
        $carry = 0;
        for ($i = strlen($minor) - 1; $i >= 0; $i--) {
            $number = (int) $minor[$i] * $quantity + $carry;
            $result = (string) ($number % 10) . $result;
            $carry = intdiv($number, 10);
        }
        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }
        return normalize_money_decimal(money_from_minor($result), true);
    }

    public static function add(array $data): int
    {
        $contractId = (int) ($data['contract_id'] ?? 0);
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $itemType = trim((string) ($data['item_type'] ?? 'product'));
        $title = trim((string) ($data['item_title'] ?? ''));
        if ($contractId <= 0 || $quantity === false || $quantity === null || $quantity > 1000000000) {
            throw new InvalidArgumentException('تعداد یا مرجع قرارداد معتبر نیست.');
        }
        if (!in_array($itemType, ['device', 'product', 'service'], true)) {
            throw new InvalidArgumentException('نوع قلم معتبر نیست.');
        }
        if ($title === '' || mb_strlen($title) > 190) {
            throw new InvalidArgumentException('عنوان قلم معتبر نیست.');
        }
        $unitAmount = normalize_money_decimal((string) ($data['unit_amount'] ?? '0'), true);
        $lineAmount = self::lineAmount($unitAmount, $quantity);
        if (isset($data['line_amount'])
            && money_compare(normalize_money_decimal((string) $data['line_amount'], true), $lineAmount) !== 0) {
            throw new InvalidArgumentException('مبلغ قلم با تعداد و قیمت واحد مطابقت ندارد.');
        }
        $sku = trim((string) ($data['sku'] ?? ''));
        $product = trim((string) ($data['product'] ?? 'Other'));
        if ($sku !== '' && mb_strlen($sku) > 120) {
            throw new InvalidArgumentException('شناسه کالا طولانی است.');
        }
        if ($product === '' || mb_strlen($product) > 120) {
            throw new InvalidArgumentException('محصول معتبر نیست.');
        }
        if ($itemType !== 'device' && !empty($data['requires_installation'])) {
            throw new InvalidArgumentException('الزام نصب فقط برای نوع دستگاه قابل انتخاب است.');
        }

        $pdo = db();
        // Support atomic contract+items creation without a nested PDO transaction.
        // Other item writers retain their standalone transaction behavior.
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            // This lock serializes line numbering and all contract allocations.
            $lock = $pdo->prepare('SELECT id, contract_amount FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$contractId]);
            $contract = $lock->fetch();
            if (!$contract) {
                throw new RuntimeException('قرارداد یا فروش پیدا نشد.');
            }
            self::assertTotalFits($contractId, $lineAmount, (string) $contract['contract_amount'], $pdo);
            $next = $pdo->prepare('SELECT COALESCE(MAX(line_number),0) + 1 FROM contract_items WHERE contract_id = ?');
            $next->execute([$contractId]);
            $lineNumber = (int) $next->fetchColumn();

            $stmt = $pdo->prepare('INSERT INTO contract_items
                (contract_id, line_number, item_type, product, sku, item_title, quantity, unit_amount, line_amount, requires_installation, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $contractId, $lineNumber, $itemType, $product,
                $sku === '' ? null : $sku, $title, $quantity, $unitAmount,
                $lineAmount, !empty($data['requires_installation']) ? 1 : 0,
                trim((string) ($data['notes'] ?? '')) ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $id;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Amend a quantity, retaining the item's identity and a permanent history
     * event. Existing invoice allocations prevent reducing sold units below
     * the amount already allocated.
     */
    public static function amendQuantity(int $contractId, int $itemId, int $newQuantity, string $reason, int $actorId): void
    {
        $reason = trim($reason);
        if ($contractId <= 0 || $itemId <= 0 || $actorId <= 0
            || $newQuantity < 1 || $newQuantity > 1000000000
            || $reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('تعداد، دلیل اصلاحیه یا شناسه معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT contract_amount FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$contractId]);
            $contractAmount = $lock->fetchColumn();
            if ($contractAmount === false) {
                throw new RuntimeException('قرارداد پیدا نشد.');
            }
            $stmt = $pdo->prepare('SELECT id, quantity, unit_amount, line_amount FROM contract_items
                WHERE id = ? AND contract_id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$itemId, $contractId]);
            $old = $stmt->fetch();
            if (!$old) {
                throw new RuntimeException('قلم متعلق به این قرارداد نیست.');
            }
            $oldQuantity = (int) $old['quantity'];
            if ($oldQuantity === $newQuantity) {
                throw new InvalidArgumentException('تعداد تغییری نکرده است.');
            }
            $assigned = $pdo->prepare('SELECT COALESCE(SUM(invoiced_quantity),0) FROM contract_invoice_lines WHERE contract_id = ? AND contract_item_id = ? AND reversed_at IS NULL');
            $assigned->execute([$contractId, $itemId]);
            if ($newQuantity < (int) $assigned->fetchColumn()) {
                throw new DomainException('تعداد جدید کمتر از تعداد تخصیص‌یافته به فاکتورها است.');
            }
            $newAmount = self::lineAmount((string) $old['unit_amount'], $newQuantity);
            self::assertTotalFits($contractId, $newAmount, (string) $contractAmount, $pdo, $itemId);
            $pdo->prepare('UPDATE contract_items SET quantity = ?, line_amount = ? WHERE id = ? AND contract_id = ?')
                ->execute([$newQuantity, $newAmount, $itemId, $contractId]);
            $pdo->prepare('INSERT INTO contract_item_amendments
                (contract_item_id, old_quantity, new_quantity, old_line_amount, new_line_amount, reason, changed_by_user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$itemId, $oldQuantity, $newQuantity, $old['line_amount'], $newAmount, $reason, $actorId]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function assertAmountCoversItems(int $contractId, string $amount, ?PDO $pdo = null): void
    {
        self::assertTotalFits($contractId, '0.00', normalize_money_decimal($amount, true), $pdo ?: db());
    }

    private static function assertTotalFits(
        int $contractId,
        string $newLineAmount,
        string $contractAmount,
        PDO $pdo,
        ?int $excludeItemId = null
    ): void {
        $sql = 'SELECT COALESCE(SUM(line_amount), 0.00) FROM contract_items WHERE contract_id = ? AND deleted_at IS NULL';
        $params = [$contractId];
        if ($excludeItemId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeItemId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $current = normalize_money_decimal((string) $stmt->fetchColumn(), true);
        if (money_compare(money_add($current, $newLineAmount), $contractAmount) > 0) {
            throw new DomainException('مجموع مبالغ اقلام از مبلغ قرارداد یا فروش بیشتر می‌شود.');
        }
    }
}
