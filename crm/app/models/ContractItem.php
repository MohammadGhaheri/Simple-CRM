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
        $sql = 'SELECT i.* FROM contract_items i JOIN contracts ct ON ct.id = i.contract_id
            WHERE i.contract_id = ? AND i.deleted_at IS NULL AND ct.deleted_at IS NULL';
        $params = [$contractId];
        self::applyReadVisibility($sql, $params, 'ct');
        $stmt = db()->prepare($sql . ' ORDER BY i.line_number, i.id');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function amendmentsForContract(int $contractId): array
    {
        $sql = 'SELECT am.*, i.item_title, u.name AS actor_name
            FROM contract_item_amendments am
            JOIN contract_items i ON i.id = am.contract_item_id
            LEFT JOIN users u ON u.id = am.changed_by_user_id
            JOIN contracts ct ON ct.id = i.contract_id AND ct.deleted_at IS NULL
            WHERE i.contract_id = ?';
        $params = [$contractId];
        self::applyReadVisibility($sql, $params, 'ct');
        $stmt = db()->prepare($sql . ' ORDER BY am.created_at DESC, am.id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function changeEventsForContract(int $contractId): array
    {
        $sql = 'SELECT ev.*, i.item_title AS current_item_title, u.name AS actor_name
            FROM contract_item_change_events ev
            JOIN contract_items i ON i.id = ev.contract_item_id
            JOIN contracts ct ON ct.id = ev.contract_id AND ct.deleted_at IS NULL
            LEFT JOIN users u ON u.id = ev.changed_by_user_id
            WHERE ev.contract_id = ?';
        $params = [$contractId];
        self::applyReadVisibility($sql, $params, 'ct');
        $stmt = db()->prepare($sql . ' ORDER BY ev.created_at DESC, ev.id DESC');
        $stmt->execute($params);
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
            $lock = $pdo->prepare('SELECT id, contract_amount, owner_user_id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$contractId]);
            $contract = $lock->fetch();
            if (!$contract) {
                throw new RuntimeException('قرارداد یا فروش پیدا نشد.');
            }
            self::assertMutationAuthorized((int) $contract['owner_user_id']);
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
            $lock = $pdo->prepare('SELECT contract_amount, owner_user_id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$contractId]);
            $contract = $lock->fetch();
            if (!$contract) {
                throw new RuntimeException('قرارداد پیدا نشد.');
            }
            self::assertMutationAuthorized((int) $contract['owner_user_id']);
            $contractAmount = (string) $contract['contract_amount'];
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
            if (self::tableExists($pdo, 'installation_records')) {
                $installed = $pdo->prepare("SELECT COUNT(*) FROM installation_records
                    WHERE contract_id = ? AND contract_item_id = ? AND deleted_at IS NULL
                      AND installation_kind = 'sale'
                      AND status IN ('installed','pending_approval','approved')");
                $installed->execute([$contractId, $itemId]);
                if ($newQuantity < (int) $installed->fetchColumn()) {
                    throw new DomainException('تعداد جدید کمتر از نصب‌های معتبر ثبت‌شده است.');
                }
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

    public static function amendIdentity(int $contractId, int $itemId, array $data, string $reason, int $actorId): void
    {
        $title = trim((string) ($data['item_title'] ?? ''));
        $product = trim((string) ($data['product'] ?? ''));
        $sku = trim((string) ($data['sku'] ?? ''));
        $reason = trim($reason);
        if ($contractId <= 0 || $itemId <= 0 || $actorId <= 0 || $title === '' || $product === ''
            || mb_strlen($title) > 190 || mb_strlen($product) > 120 || mb_strlen($sku) > 120
            || $reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('عنوان، مدل، محصول یا دلیل اصلاح معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $contract = self::lockContractForMutation($pdo, $contractId);
            self::assertMutationAuthorized((int) $contract['owner_user_id']);
            $stmt = $pdo->prepare('SELECT item_title, product, sku FROM contract_items
                WHERE id = ? AND contract_id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$itemId, $contractId]);
            $old = $stmt->fetch();
            if (!$old) {
                throw new RuntimeException('قلم متعلق به این قرارداد نیست.');
            }
            $newSku = $sku === '' ? null : $sku;
            if ($old['item_title'] === $title && $old['product'] === $product && $old['sku'] === $newSku) {
                throw new InvalidArgumentException('مشخصات قلم تغییری نکرده است.');
            }
            $pdo->prepare('UPDATE contract_items SET item_title = ?, product = ?, sku = ? WHERE id = ? AND contract_id = ?')
                ->execute([$title, $product, $newSku, $itemId, $contractId]);
            self::recordChangeEvent($pdo, $contractId, $itemId, 'identity_amended', $old, [
                'item_title' => $title, 'product' => $product, 'sku' => $newSku,
            ], $reason, $actorId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function cancel(int $contractId, int $itemId, string $reason, int $actorId): void
    {
        $reason = trim($reason);
        if ($contractId <= 0 || $itemId <= 0 || $actorId <= 0 || $reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('شناسه یا دلیل ابطال قلم معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $contract = self::lockContractForMutation($pdo, $contractId);
            self::assertMutationAuthorized((int) $contract['owner_user_id']);
            $stmt = $pdo->prepare('SELECT item_title, product, sku FROM contract_items
                WHERE id = ? AND contract_id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$itemId, $contractId]);
            $old = $stmt->fetch();
            if (!$old) {
                throw new RuntimeException('قلم فعال متعلق به این قرارداد پیدا نشد.');
            }
            $allocated = $pdo->prepare('SELECT COALESCE(SUM(invoiced_quantity),0) FROM contract_invoice_lines
                WHERE contract_id = ? AND contract_item_id = ? AND reversed_at IS NULL');
            $allocated->execute([$contractId, $itemId]);
            if ((int) $allocated->fetchColumn() > 0) {
                throw new DomainException('قلم دارای تخصیص فعال فاکتور است؛ ابتدا تخصیص‌ها را باطل کنید.');
            }
            // Soft-deleting an item with operational history hides the obligation
            // while leaving installation and delivery records behind. Require a
            // dedicated audited termination workflow, even for failed attempts.
            if (self::tableExists($pdo, 'installation_records')) {
                $recordCount = $pdo->prepare('SELECT COUNT(*) FROM installation_records
                    WHERE contract_id = ? AND contract_item_id = ?');
                $recordCount->execute([$contractId, $itemId]);
                if ((int) $recordCount->fetchColumn() > 0) {
                    throw new DomainException('این قلم سابقه نصب دارد و با فرایند ابطال عادی قابل حذف نیست.');
                }
            }
            $pdo->prepare('UPDATE contract_items SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND contract_id = ? AND deleted_at IS NULL')
                ->execute([$itemId, $contractId]);
            self::recordChangeEvent($pdo, $contractId, $itemId, 'cancelled', $old, $old, $reason, $actorId);
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

    private static function assertMutationAuthorized(int $ownerUserId): void
    {
        $role = function_exists('current_user_role') ? current_user_role() : 'admin';
        if ($role === 'admin' || ($role === 'sales' && $ownerUserId === current_user_id())) {
            return;
        }
        throw new RuntimeException('اجازه تغییر اقلام این قرارداد را ندارید.');
    }

    private static function lockContractForMutation(PDO $pdo, int $contractId): array
    {
        $stmt = $pdo->prepare('SELECT id, owner_user_id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();
        if (!$contract) {
            throw new RuntimeException('قرارداد پیدا نشد.');
        }
        return $contract;
    }

    private static function recordChangeEvent(
        PDO $pdo,
        int $contractId,
        int $itemId,
        string $eventType,
        array $old,
        array $new,
        string $reason,
        int $actorId
    ): void {
        $pdo->prepare('INSERT INTO contract_item_change_events
            (contract_id, contract_item_id, event_type, old_item_title, new_item_title,
             old_product, new_product, old_sku, new_sku, reason, changed_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $contractId, $itemId, $eventType,
                $old['item_title'] ?? null, $new['item_title'] ?? null,
                $old['product'] ?? null, $new['product'] ?? null,
                $old['sku'] ?? null, $new['sku'] ?? null,
                $reason, $actorId,
            ]);
    }

    private static function applyReadVisibility(string &$sql, array &$params, string $contractAlias): void
    {
        $role = function_exists('current_user_role') ? current_user_role() : 'admin';
        if (in_array($role, ['admin', 'finance'], true)) {
            return;
        }
        if ($role === 'sales') {
            $sql .= " AND {$contractAlias}.owner_user_id = ?";
            $params[] = current_user_id();
            return;
        }
        $sql .= ' AND 1 = 0';
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() === 1;
    }
}
