<?php $deal = $deal ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div><label class="required">نام فرصت</label><input required name="deal_name" value="<?= e($deal['deal_name'] ?? '') ?>"></div>
    <div><label class="required">مشتری</label><select required name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= selected($deal['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>محصول</label><select name="product"><?php foreach (product_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($deal['product'] ?? '', $option) ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
    <div><label>تعداد خودرو</label><input type="number" min="0" name="vehicle_count" value="<?= e((string) ($deal['vehicle_count'] ?? 0)) ?>"></div>
    <div><label>مبلغ تخمینی</label><input type="number" min="0" step="1000" name="estimated_amount" value="<?= e((string) ($deal['estimated_amount'] ?? 0)) ?>"></div>
    <div><label>احتمال موفقیت</label><input type="number" min="0" max="100" name="probability" value="<?= e((string) ($deal['probability'] ?? 20)) ?>"></div>
    <div><label>ارزش وزنی</label><div class="card" data-weighted>0 ریال</div></div>
    <div><label>مرحله</label><select name="deal_stage"><?php foreach (deal_stage_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($deal['deal_stage'] ?? 'Lead', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>تاریخ بسته شدن مورد انتظار</label><input class="date-input" name="expected_close_date" placeholder="مثلا 1405/04/10" title="تاریخ را به شمسی و با فرمت 1405/04/10 وارد کنید" value="<?= e(fa_date($deal['expected_close_date'] ?? '')) ?>"></div>
    <div><label>مالک</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($deal['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
</div>
<div style="margin-top:14px"><label>دلیل برد/باخت</label><input name="win_loss_reason" value="<?= e($deal['win_loss_reason'] ?? '') ?>"></div>
<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($deal['notes'] ?? '') ?></textarea></div>
<div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn btn-light" href="<?= e(url('deals')) ?>">انصراف</a></div>
