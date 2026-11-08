<?php

declare(strict_types=1);

/**
 * Create a contract/direct sale together with its initial line items in one
 * database transaction. Existing legacy callers may still use Contract::create.
 */
final class ContractSaleService
{
    private const MAX_ITEMS = 40;

    public static function create(array $contractData, array $rawItems): int
    {
        if (count($rawItems) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('تعداد ردیف‌های فروش از حد مجاز بیشتر است.');
        }

        $items = [];
        $total = '0.00';
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('قالب اطلاعات ردیف کالا معتبر نیست.');
            }
            $title = trim((string) ($row['item_title'] ?? ''));
            $quantity = trim((string) ($row['quantity'] ?? ''));
            $unit = trim((string) ($row['unit_amount'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $product = trim((string) ($row['product'] ?? ''));
            $install = (string) ($row['requires_installation'] ?? '0');

            // An untouched blank row in the create form is not an item.
            if ($title === '' && $quantity === '' && $unit === '' && $sku === '' && $product === '' && $install !== '1') {
                continue;
            }
            if ($title === '' || $quantity === '' || $unit === '') {
                throw new InvalidArgumentException('عنوان، تعداد و قیمت واحد هر ردیف باید کامل باشد.');
            }
            $count = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($count === false || $count > 1000000000) {
                throw new InvalidArgumentException('تعداد ردیف فروش معتبر نیست.');
            }
            $itemType = trim((string) ($row['item_type'] ?? 'product'));
            if (!in_array($itemType, ['device', 'product', 'service'], true)) {
                throw new InvalidArgumentException('نوع ردیف فروش معتبر نیست.');
            }
            if ($itemType !== 'device' && $install === '1') {
                throw new InvalidArgumentException('الزام نصب فقط برای دستگاه قابل ثبت است.');
            }
            if (mb_strlen($title) > 190 || mb_strlen($sku) > 120 || mb_strlen($product) > 120) {
                throw new InvalidArgumentException('عنوان یا مدل ردیف طولانی است.');
            }

            $amount = ContractItem::lineAmount($unit, (int) $count);
            $total = money_add($total, $amount);
            $items[] = [
                'item_type' => $itemType,
                'product' => $product === '' ? 'Other' : $product,
                'sku' => $sku,
                'item_title' => $title,
                'quantity' => (int) $count,
                'unit_amount' => $unit,
                'requires_installation' => $install === '1' ? 1 : 0,
            ];
        }

        $contractAmount = normalize_money_decimal((string) ($contractData['contract_amount'] ?? '0'), true);
        if (money_compare($total, $contractAmount) > 0) {
            throw new DomainException('مجموع ردیف‌ها از مبلغ قرارداد بیشتر است.');
        }

        // No-item legacy creates are supported without auto-inventing lines.
        if (!$items) {
            return Contract::create($contractData);
        }

        $pdo = db();
        if ($pdo->inTransaction()) {
            throw new LogicException('ایجاد قرارداد جدید باید تراکنش مستقل داشته باشد.');
        }
        $pdo->beginTransaction();
        try {
            $contractId = Contract::create($contractData);
            foreach ($items as $item) {
                ContractItem::add(['contract_id' => $contractId] + $item);
            }
            $pdo->commit();
            return $contractId;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
