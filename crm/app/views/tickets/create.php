<div class="toolbar">
    <h2>تیکت جدید برای مشتری</h2>
    <a class="btn btn-light" href="<?= e(url('tickets')) ?>">بازگشت</a>
</div>

<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form class="card" method="post" enctype="multipart/form-data" data-ticket-create-form>
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <div>
            <label class="required">مشتری</label>
            <select required name="customer_id" data-ticket-customer-select>
                <option value="">انتخاب مشتری</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer['id']) ?>" <?= selected($ticket['customer_id'] ?? '', $customer['id']) ?>>
                        <?= e($customer['customer_name']) ?><?= !empty($customer['customer_code']) ? ' - ' . e($customer['customer_code']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="required">مخاطب همان مشتری</label>
            <select required name="contact_id" data-ticket-contact-select data-selected="<?= e((string) ($ticket['contact_id'] ?? '')) ?>">
                <option value="">ابتدا مشتری را انتخاب کنید</option>
                <?php foreach ($contacts as $contact): ?>
                    <option value="<?= e((string) $contact['id']) ?>" <?= selected($ticket['contact_id'] ?? '', $contact['id']) ?>>
                        <?= e($contact['contact_name']) ?><?= !empty($contact['position']) ? ' - ' . e($contact['position']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="muted">برای جلوگیری از خطای دسترسی، مخاطب فقط از بین افراد همین مشتری انتخاب می‌شود.</span>
        </div>
        <div>
            <label class="required">موضوع</label>
            <input required name="subject" value="<?= e($ticket['subject'] ?? '') ?>">
        </div>
        <div>
            <label>پشتیبان مسئول</label>
            <select name="assigned_user_id">
                <option value="">بدون مسئول</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= selected($ticket['assigned_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>دسته‌بندی</label>
            <select name="category">
                <?php foreach (Ticket::categories() as $option): ?>
                    <option value="<?= e($option) ?>" <?= selected($ticket['category'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>اولویت</label>
            <select name="priority">
                <?php foreach (Ticket::priorities() as $option): ?>
                    <option value="<?= e($option) ?>" <?= selected($ticket['priority'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div style="margin-top:14px">
        <label class="required">متن اولین پیام</label>
        <textarea required name="message" placeholder="متنی که در پرتال مشتری به عنوان اولین پیام پشتیبانی دیده می‌شود..."><?= e($ticket['message'] ?? '') ?></textarea>
    </div>

    <div class="ticket-upload-field">
        <label>تصویر پیوست</label>
        <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp">
        <span class="muted">حداکثر ۲ مگابایت. فرمت‌های مجاز: jpg، png، webp</span>
    </div>

    <div class="form-actions">
        <button class="btn btn-primary">ایجاد و ارسال برای مشتری</button>
    </div>
</form>
