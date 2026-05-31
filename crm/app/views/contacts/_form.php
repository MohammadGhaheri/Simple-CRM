<?php $contact = $contact ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-2">
    <div><label class="required">مشتری</label><select required name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= selected($contact['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="required">نام مخاطب</label><input required name="contact_name" value="<?= e($contact['contact_name'] ?? '') ?>"></div>
    <div><label>سمت</label><input name="position" value="<?= e($contact['position'] ?? '') ?>"></div>
    <div><label>موبایل</label><input name="mobile" value="<?= e($contact['mobile'] ?? '') ?>"></div>
    <div><label>تلفن</label><input name="phone" value="<?= e($contact['phone'] ?? '') ?>"></div>
    <div><label>ایمیل</label><input type="email" name="email" value="<?= e($contact['email'] ?? '') ?>"></div>
    <div>
        <label>رمز پرتال مشتری</label>
        <input type="password" name="portal_password" autocomplete="new-password">
        <?php if (!empty($contact['password_hash'])): ?><span class="muted">برای حفظ رمز فعلی خالی بگذارید.</span><?php endif; ?>
    </div>
</div>
<p><label><input type="checkbox" name="is_primary" value="1" <?= checked($contact['is_primary'] ?? false) ?> style="width:auto"> مخاطب اصلی</label></p>
<p><label><input type="checkbox" name="portal_enabled" value="1" <?= checked($contact['portal_enabled'] ?? false) ?> style="width:auto"> دسترسی به پرتال مشتری فعال باشد</label></p>
<div><label>یادداشت</label><textarea name="notes"><?= e($contact['notes'] ?? '') ?></textarea></div>
<div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn btn-light" href="<?= e(url('customers')) ?>">انصراف</a></div>
