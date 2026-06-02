<?php $contract = $contract ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div><label class="required">شماره قرارداد</label><input required name="contract_number" value="<?= e($contract['contract_number'] ?? '') ?>"></div>
    <div><label class="required">عنوان قرارداد</label><input required name="contract_title" value="<?= e($contract['contract_title'] ?? '') ?>"></div>
    <div><label class="required">مشتری</label><select required name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= selected($contract['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>فرصت مرتبط</label><select name="deal_id"><option value="">بدون فرصت</option><?php foreach ($deals as $deal): ?><option value="<?= e((string) $deal['id']) ?>" <?= selected($contract['deal_id'] ?? '', $deal['id']) ?>><?= e($deal['deal_name']) ?> - <?= e($deal['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>محصول / خدمت</label><select name="product"><?php foreach (product_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($contract['product'] ?? '', $option) ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
    <div><label>تعداد خودرو در قرارداد</label><input type="number" min="0" name="vehicle_count" value="<?= e((string) ($contract['vehicle_count'] ?? 0)) ?>"></div>
    <div><label>مبلغ نهایی قرارداد</label><input type="number" min="0" step="1000" name="contract_amount" value="<?= e((string) ($contract['contract_amount'] ?? 0)) ?>"></div>
    <div><label>تاریخ شروع</label><input class="date-input" name="start_date" placeholder="مثلا 1405/04/01" value="<?= e(fa_date($contract['start_date'] ?? '')) ?>"></div>
    <div><label class="required">تاریخ پایان</label><input required class="date-input" name="end_date" placeholder="مثلا 1406/04/01" value="<?= e(fa_date($contract['end_date'] ?? '')) ?>"></div>
    <div><label>تاریخ یادآوری تمدید</label><input class="date-input" name="renewal_reminder_date" placeholder="خالی = محاسبه خودکار" value="<?= e(fa_date($contract['renewal_reminder_date'] ?? '')) ?>"></div>
    <div>
        <label>وضعیت قرارداد</label>
        <select name="status">
            <?php foreach (Contract::statuses() as $status): ?><option value="<?= e($status) ?>" <?= selected($contract['status'] ?? 'Active', $status) ?>><?= e(fa_label($status)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div><label>مسئول قرارداد</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($contract['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
</div>
<p class="muted">اگر تاریخ یادآوری تمدید را خالی بگذارید، سیستم بر اساس تنظیمات، چند روز قبل از تاریخ پایان قرارداد یک فعالیت پیگیری تمدید برای مسئول قرارداد می‌سازد.</p>
<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($contract['notes'] ?? '') ?></textarea></div>
<div class="form-actions">
    <button class="btn btn-primary" type="submit">ذخیره قرارداد</button>
    <a class="btn btn-light" href="<?= e(url('contracts')) ?>">انصراف</a>
</div>
