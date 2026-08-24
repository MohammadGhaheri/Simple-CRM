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
        <select name="audience_type" data-announcement-audience>
            <option value="all" <?= selected($announcement['audience_type'] ?? 'all', 'all') ?>>همه مشتریان</option>
            <option value="customer" <?= selected($announcement['audience_type'] ?? 'all', 'customer') ?>>یک مشتری مشخص</option>
            <option value="customers" <?= selected($announcement['audience_type'] ?? 'all', 'customers') ?>>چند مشتری منتخب</option>
        </select>
    </div>
    <div data-announcement-targets>
        <label>مشتریان گیرنده</label>
        <?php $selectedTargets = array_map('intval', (array) ($announcement['target_customer_ids'] ?? (!empty($announcement['customer_id']) ? [$announcement['customer_id']] : []))); ?>
        <input class="customer-filter-input" type="search" placeholder="جستجوی مشتری..." data-multi-select-filter>
        <select name="target_customer_ids[]" multiple size="8" data-multi-select>
            <?php foreach ($customers as $customer): ?>
                <option value="<?= e((string) $customer['id']) ?>" <?= in_array((int) $customer['id'], $selectedTargets, true) ? 'selected' : '' ?>><?= e($customer['customer_name']) ?><?= !empty($customer['customer_code']) ? ' - ' . e($customer['customer_code']) : '' ?></option>
            <?php endforeach; ?>
        </select>
        <span class="muted">برای انتخاب چند مشتری از Ctrl/Command یا جستجو استفاده کنید.</span>
    </div>
</div>
<div style="margin-top:14px">
    <label class="required">متن اطلاعیه</label>
    <div class="rich-editor" data-rich-editor>
        <div class="rich-toolbar" role="toolbar" aria-label="ویرایشگر متن">
            <button type="button" data-command="bold"><strong>B</strong></button>
            <button type="button" data-command="italic"><em>I</em></button>
            <button type="button" data-block="h3">تیتر</button>
            <button type="button" data-block="p">متن</button>
            <button type="button" data-command="insertUnorderedList">• لیست</button>
            <button type="button" data-command="insertOrderedList">۱. لیست</button>
            <button type="button" data-link>لینک</button>
            <button type="button" data-image>تصویر</button>
            <button type="button" data-command="removeFormat">پاک‌سازی</button>
        </div>
        <div class="rich-editor-area" contenteditable="true" dir="rtl"><?= sanitize_rich_html($announcement['body'] ?? '') ?></div>
        <textarea name="body" hidden data-rich-input><?= e($announcement['body'] ?? '') ?></textarea>
    </div>
</div>
<div class="announcement-upload-field">
    <label>فایل‌های پیوست</label>
    <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx">
    <span class="muted">حداکثر ۵ مگابایت برای هر فایل. فرمت‌های مجاز: تصویر، PDF و Word</span>
</div>
<?php if (!empty($attachments)): ?>
    <div class="announcement-attachments">
        <?php foreach ($attachments as $attachment): ?>
            <div class="attachment-row">
                <a class="badge badge-muted" href="<?= e(url('announcements', ['action' => 'attachment', 'id' => $attachment['id']])) ?>"><?= e($attachment['file_name'] ?: 'فایل پیوست') ?></a>
                <label class="muted"><input type="checkbox" name="remove_attachment_ids[]" value="<?= e((string) $attachment['id']) ?>" style="width:auto"> حذف شود</label>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<p><label><input type="checkbox" name="is_active" value="1" <?= checked((int) ($announcement['is_active'] ?? 1) === 1) ?> style="width:auto"> اطلاعیه فعال باشد</label></p>
<div class="form-actions">
    <button class="btn btn-primary">ذخیره اطلاعیه</button>
    <a class="btn btn-light" href="<?= e(url('announcements')) ?>">انصراف</a>
</div>
