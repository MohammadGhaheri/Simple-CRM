<?php $payment = $payment ?? []; ?>
<div class="grid grid-2">
    <div><label class="required">مبلغ دریافتی</label><input required inputmode="decimal" name="amount" value="<?= e((string) ($payment['amount'] ?? '')) ?>"></div>
    <div><label class="required">تاریخ دریافت</label><input required class="date-input" name="payment_date" placeholder="مثلا 1405/07/01" value="<?= e(fa_date($payment['payment_date'] ?? '')) ?>"></div>
    <div><label class="required">روش پرداخت</label><select required name="payment_method"><?php foreach (payment_method_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($payment['payment_method'] ?? 'Bank Transfer', $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label>شماره پیگیری / مرجع</label><input maxlength="120" name="reference_number" value="<?= e($payment['reference_number'] ?? '') ?>"></div>
</div>
<div style="margin-top:14px"><label>توضیحات</label><textarea name="notes"><?= e($payment['notes'] ?? '') ?></textarea></div>
