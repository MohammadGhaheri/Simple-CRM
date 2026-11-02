<?php

declare(strict_types=1);

/**
 * Additive sales line-item model. The legacy contracts.vehicle_count and
 * contracts.contract_amount remain unchanged until an explicit migration and
 * rollout of the full multi-item UI.
 */
class ContractItem
{
    public static function byContract(int $contractId): array
    {
        $stmt = db()->prepare('SELECT * FROM contract_items WHERE contract_id = ? AND deleted_at IS NULL ORDER BY line_number, id');
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
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
        $lineAmount = normalize_money_decimal((string) ($data['line_amount'] ?? '0'), true);
        $sku = trim((string) ($data['sku'] ?? ''));
        $product = trim((string) ($data['product'] ?? 'Other'));
        if ($sku !== '' && mb_strlen($sku) > 120) {
            throw new InvalidArgumentException('شناسه کالا طولانی است.');
        }
        if ($product === '' || mb_strlen($product) > 120) {
            throw new InvalidArgumentException('محصول معتبر نیست.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // This lock serializes line numbering even when several operators
            // add items to the same contract simultaneously.
            $lock = $pdo->prepare('SELECT id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$contractId]);
            if (!$lock->fetchColumn()) {
                throw new RuntimeException('قرارداد یا فروش پیدا نشد.');
            }
            $next = $pdo->prepare('SELECT COALESCE(MAX(line_number),0) + 1 FROM contract_items WHERE contract_id = ?');
            $next->execute([$contractId]);
            $lineNumber = (int) $next->fetchColumn();

            $stmt = $pdo->prepare('INSERT INTO contract_items
                (contract_id, line_number, item_type, product, sku, item_title, quantity, unit_amount, line_amount, requires_installation, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $contractId,
                $lineNumber,
                $itemType,
                $product,
                $sku === '' ? null : $sku,
                $title,
                $quantity,
                $unitAmount,
                $lineAmount,
                !empty($data['requires_installation']) ? 1 : 0,
                trim((string) ($data['notes'] ?? '')) ?: null,
            ]);
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
}
