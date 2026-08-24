<div class="grid grid-2">
    <div>
        <label class="required">عنوان</label>
        <input required name="title" value="<?= e($announcement['title'] ?? '') ?>">
    </div>
    <div>
        <label>تاریخ انتشار</label>
        <input class="date-input" name="published_at" value="<?= e($announcement['published_at'] ?? fa_date(date('Y-m-d'))) ?>" placeholder="1405/01/01">
    </div>
    <div>
        <label>مخاطب</label>
        <select name="audience_type">
            <option value="all" <?= selected($announcement['audience_type'] ?? 'all', 'all') ?>>همه مشتریان</option>
            <option value="customer" <?= selected($announcement['audience_type'] ?? 'all', 'customer') ?>>یک مشتری مشخص</option>
        </select>
    </div>
    <div>
        <label>مشتری</label>
        <select name="customer_id">
            <option value="">فقط در حالت مشتری مشخص</option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?= e((string) $customer['id']) ?>" <?= selected($announcement['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div style="margin-top:14px">
    <label class="required">متن اطلاعیه</label>
    <textarea required name="body" rows="8"><?= e($announcement['body'] ?? '') ?></textarea>
</div>
<p><label><input type="checkbox" name="is_active" value="1" <?= checked((int) ($announcement['is_active'] ?? 1) === 1) ?> style="width:auto"> اطلاعیه فعال باشد</label></p>
<div class="form-actions">
    <button class="btn btn-primary">ذخیره اطلاعیه</button>
    <a class="btn btn-light" href="<?= e(url('announcements')) ?>">انصراف</a>
</div>
