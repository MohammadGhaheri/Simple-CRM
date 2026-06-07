<?php $customer = $customer ?? []; ?>
<?php $customerCodeAuto = empty($customer['id']) && class_exists('Setting') && Setting::get('customer_code_mode') === 'auto'; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div>
        <label class="<?= $customerCodeAuto ? '' : 'required' ?>">کد مشتری</label>
        <input name="customer_code" <?= $customerCodeAuto ? 'placeholder="به صورت خودکار ساخته می‌شود"' : 'required' ?> value="<?= e($customer['customer_code'] ?? '') ?>">
        <?php if ($customerCodeAuto): ?><span class="muted">فرمت فعلی: <?= e(Setting::get('customer_code_format')) ?></span><?php endif; ?>
    </div>
    <div><label class="required">نام مشتری</label><input name="customer_name" required value="<?= e($customer['customer_name'] ?? '') ?>"></div>
    <div><label>نوع مشتری</label><select name="customer_type"><?php foreach (customer_type_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($customer['customer_type'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>صنعت</label><input name="industry" value="<?= e($customer['industry'] ?? '') ?>"></div>
    <div><label>شهر</label><input name="city" value="<?= e($customer['city'] ?? '') ?>"></div>
    <div><label>منبع جذب</label><input name="lead_source" value="<?= e($customer['lead_source'] ?? '') ?>"></div>
    <div><label>وضعیت فروش</label><select name="sales_status"><?php foreach (sales_status_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($customer['sales_status'] ?? 'New', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>مالک</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($customer['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>آخرین پیگیری</label><input class="date-input" name="last_followup_date" placeholder="مثلا 1405/03/12" title="تاریخ را به شمسی و با فرمت 1405/03/12 وارد کنید" value="<?= e(fa_date($customer['last_followup_date'] ?? '')) ?>"></div>
    <div><label>پیگیری بعدی</label><input class="date-input" name="next_followup_date" placeholder="مثلا 1405/03/12" title="تاریخ را به شمسی و با فرمت 1405/03/12 وارد کنید" value="<?= e(fa_date($customer['next_followup_date'] ?? '')) ?>"></div>
</div>
<p><label><input type="checkbox" name="is_vip" value="1" <?= checked($customer['is_vip'] ?? false) ?> style="width:auto"> مشتری VIP</label></p>
<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($customer['notes'] ?? '') ?></textarea></div>
<div class="form-actions">
    <button class="btn btn-primary" type="submit">ذخیره</button>
    <a class="btn btn-light" href="<?= e(url('customers')) ?>">انصراف</a>
</div>
