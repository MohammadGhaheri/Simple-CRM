<?php $contract = $contract ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div><label class="required">نوع ثبت</label><select name="contract_type" data-contract-type><?php foreach (Contract::types() as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($contract['contract_type'] ?? 'formal', $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label class="required" data-contract-required-label>شماره قرارداد</label><input data-contract-required name="contract_number" value="<?= e($contract['contract_number'] ?? '') ?>"></div>
    <div><label class="required">عنوان قرارداد / فروش</label><input required name="contract_title" value="<?= e($contract['contract_title'] ?? '') ?>"></div>
    <div><label class="required">مشتری</label><select required name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= selected($contract['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>فرصت مرتبط</label><select name="deal_id"><option value="">بدون فرصت</option><?php foreach ($deals as $deal): ?><option value="<?= e((string) $deal['id']) ?>" <?= selected($contract['deal_id'] ?? '', $deal['id']) ?>><?= e($deal['deal_name']) ?> - <?= e($deal['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>محصول / خدمت</label><select name="product"><?php foreach (product_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($contract['product'] ?? '', $option) ?>><?= e(product_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>تعداد خودرو در قرارداد</label><input type="number" min="0" name="vehicle_count" value="<?= e((string) ($contract['vehicle_count'] ?? 0)) ?>"></div>
    <div><label>مبلغ قرارداد / فروش</label><input type="number" min="0" step="0.01" name="contract_amount" value="<?= e((string) ($contract['contract_amount'] ?? 0)) ?>"></div>
    <div><label>تاریخ شروع</label><input class="date-input" name="start_date" placeholder="مثلا 1405/04/01" value="<?= e(fa_date($contract['start_date'] ?? '')) ?>"></div>
    <div><label class="required" data-contract-required-label>تاریخ پایان</label><input data-contract-required class="date-input" name="end_date" placeholder="برای فروش مستقیم اختیاری است" value="<?= e(fa_date($contract['end_date'] ?? '')) ?>"></div>
    <div data-renewal-field><label>تاریخ یادآوری تمدید</label><input class="date-input" name="renewal_reminder_date" placeholder="خالی = محاسبه خودکار" value="<?= e(fa_date($contract['renewal_reminder_date'] ?? '')) ?>"></div>
    <div>
        <label>وضعیت قرارداد / فروش</label>
        <select name="status">
            <?php foreach (Contract::statuses() as $status): ?><option value="<?= e($status) ?>" data-formal-status="<?= in_array($status, ['Active', 'Cancelled'], true) ? '0' : '1' ?>" <?= selected($contract['status'] ?? 'Active', $status) ?>><?= e(fa_label($status)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div><label>مسئول قرارداد / فروش</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($contract['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
</div>
<p class="muted" data-renewal-field>برای قرارداد رسمی، تاریخ یادآوری خالی به‌صورت خودکار از تاریخ پایان محاسبه می‌شود. فروش بدون قرارداد رسمی فعالیت تمدید ندارد.</p>
<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($contract['notes'] ?? '') ?></textarea></div>
<div class="form-actions">
    <button class="btn btn-primary" type="submit">ذخیره</button>
    <a class="btn btn-light" href="<?= e(url('contracts')) ?>">انصراف</a>
</div>
