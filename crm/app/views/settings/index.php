<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>تنظیمات عمومی</h2>
    <div class="grid grid-2">
        <div><label class="required">عنوان سامانه</label><input required name="app_title" value="<?= e($settings['app_title'] ?? '') ?>"></div>
        <div><label>زیرعنوان سامانه</label><input name="app_subtitle" value="<?= e($settings['app_subtitle'] ?? '') ?>"></div>
        <div><label>واحد پول</label><input name="currency_unit" value="<?= e($settings['currency_unit'] ?? 'ریال') ?>"></div>
        <div><label>رنگ اصلی</label><input type="color" name="primary_color" value="<?= e($settings['primary_color'] ?? '#155eef') ?>"></div>
        <div><label>رنگ نوار کناری</label><input type="color" name="sidebar_color" value="<?= e($settings['sidebar_color'] ?? '#111827') ?>"></div>
        <div>
            <label>آیکن سامانه</label>
            <input type="file" name="app_icon_file" accept="image/*">
            <?php if (!empty($settings['app_icon'])): ?><img class="avatar-preview" src="<?= e($settings['app_icon']) ?>" alt=""><?php endif; ?>
        </div>
    </div>

    <h2 style="margin-top:22px">هوم‌پیج</h2>
    <div class="grid grid-2">
        <div><label>عنوان هوم‌پیج</label><input name="home_title" value="<?= e($settings['home_title'] ?? '') ?>"></div>
    </div>
    <div style="margin-top:14px"><label>متن هوم‌پیج</label><textarea name="home_text"><?= e($settings['home_text'] ?? '') ?></textarea></div>

    <h2 style="margin-top:22px">کدگذاری مشتری</h2>
    <div class="grid grid-2">
        <div>
            <label>روش ثبت کد مشتری</label>
            <select name="customer_code_mode">
                <option value="manual" <?= selected($settings['customer_code_mode'] ?? 'manual', 'manual') ?>>دستی</option>
                <option value="auto" <?= selected($settings['customer_code_mode'] ?? 'manual', 'auto') ?>>خودکار</option>
            </select>
        </div>
        <div><label>فرمت کد خودکار</label><input name="customer_code_format" value="<?= e($settings['customer_code_format'] ?? 'CUS-{YYYY}-{SEQ4}') ?>"></div>
    </div>
    <p class="muted">Placeholderهای فرمت کد: <code>{YYYY}</code>، <code>{YY}</code>، <code>{MM}</code>، <code>{DD}</code>، <code>{SEQ}</code>، <code>{SEQ3}</code>، <code>{SEQ4}</code>، <code>{SEQ5}</code></p>

    <h2 style="margin-top:22px">گزینه‌های چندانتخابی فرم‌ها</h2>
    <p class="muted">هر گزینه را در یک خط بنویسید. قالب پیشنهادی: <code>value|label</code>. مقدار سمت چپ در دیتابیس ذخیره می‌شود و متن سمت راست در رابط نمایش داده می‌شود.</p>
    <div class="grid grid-2">
        <div><label>نوع مشتری</label><textarea name="options_customer_types"><?= e($settings['options_customer_types'] ?? '') ?></textarea></div>
        <div><label>وضعیت فروش مشتری</label><textarea name="options_sales_statuses"><?= e($settings['options_sales_statuses'] ?? '') ?></textarea></div>
        <div><label>محصولات</label><textarea name="options_products"><?= e($settings['options_products'] ?? '') ?></textarea></div>
        <div><label>مراحل فرصت فروش</label><textarea name="options_deal_stages"><?= e($settings['options_deal_stages'] ?? '') ?></textarea></div>
        <div><label>نوع فعالیت</label><textarea name="options_activity_types"><?= e($settings['options_activity_types'] ?? '') ?></textarea></div>
        <div><label>وضعیت فعالیت</label><textarea name="options_activity_statuses"><?= e($settings['options_activity_statuses'] ?? '') ?></textarea></div>
        <div><label>وضعیت تیکت</label><textarea name="options_ticket_statuses"><?= e($settings['options_ticket_statuses'] ?? '') ?></textarea></div>
        <div><label>اولویت تیکت</label><textarea name="options_ticket_priorities"><?= e($settings['options_ticket_priorities'] ?? '') ?></textarea></div>
        <div><label>دسته‌بندی تیکت</label><textarea name="options_ticket_categories"><?= e($settings['options_ticket_categories'] ?? '') ?></textarea></div>
    </div>

    <h2 style="margin-top:22px">تنظیمات پیامک sms.ir</h2>
    <div class="grid grid-2">
        <div><label>توکن API</label><input name="sms_api_key" value="<?= e($settings['sms_api_key'] ?? '') ?>"></div>
        <div><label>خط ارسال کننده</label><input name="sms_line_number" value="<?= e($settings['sms_line_number'] ?? '') ?>"></div>
        <div><label>موبایل مدیر برای خلاصه روزانه</label><input name="sms_admin_mobile" value="<?= e($settings['sms_admin_mobile'] ?? '') ?>"></div>
        <div><label>آدرس عمومی پرتال مشتری</label><input name="portal_public_url" placeholder="https://your-domain.com/portal.php?action=login" value="<?= e($settings['portal_public_url'] ?? '') ?>"></div>
        <div>
            <label>مسئول پیش‌فرض تیکت جدید</label>
            <select name="sms_default_assigned_user_id">
                <option value="">بدون مسئول پیش‌فرض</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= selected($settings['sms_default_assigned_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <p><label><input type="checkbox" name="sms_enabled" value="1" <?= checked(($settings['sms_enabled'] ?? '0') === '1') ?> style="width:auto"> فعال‌سازی کلی پیامک</label></p>
    <p><label><input type="checkbox" name="sms_ticket_created_enabled" value="1" <?= checked(($settings['sms_ticket_created_enabled'] ?? '0') === '1') ?> style="width:auto"> ارسال پیامک به مسئول هنگام ثبت تیکت جدید</label></p>
    <p><label><input type="checkbox" name="sms_ticket_answered_enabled" value="1" <?= checked(($settings['sms_ticket_answered_enabled'] ?? '0') === '1') ?> style="width:auto"> ارسال پیامک به مخاطب هنگام پاسخ تیکت</label></p>
    <p><label><input type="checkbox" name="sms_portal_credentials_enabled" value="1" <?= checked(($settings['sms_portal_credentials_enabled'] ?? '0') === '1') ?> style="width:auto"> ارسال پیامک اطلاعات ورود پرتال برای مخاطب</label></p>
    <p><label><input type="checkbox" name="sms_daily_summary_enabled" value="1" <?= checked(($settings['sms_daily_summary_enabled'] ?? '0') === '1') ?> style="width:auto"> ارسال خلاصه روزانه با cron</label></p>
    <div style="margin-top:14px">
        <label>متن پیامک اطلاعات ورود پرتال</label>
        <textarea name="sms_portal_credentials_template"><?= e($settings['sms_portal_credentials_template'] ?? '') ?></textarea>
        <p class="muted">Placeholderهای قابل استفاده: <code>{app_title}</code>، <code>{contact_name}</code>، <code>{customer_name}</code>، <code>{email}</code>، <code>{password}</code>، <code>{portal_url}</code></p>
    </div>

    <div class="form-actions"><button class="btn btn-primary">ذخیره تنظیمات</button></div>
</form>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2>پشتیبان‌گیری</h2>
        <p class="muted">یک فایل SQL از ساختار و داده‌های سیستم دریافت کنید.</p>
        <a class="btn btn-primary" href="<?= e(url('backup', ['action' => 'download'])) ?>">دانلود فایل بکاپ</a>
    </div>
    <form class="card" method="post" enctype="multipart/form-data" data-confirm="بازگردانی بکاپ داده‌های فعلی را تغییر می‌دهد. ادامه می‌دهید؟">
        <?= csrf_field() ?>
        <h2>بازگردانی بکاپ</h2>
        <p class="muted">فقط فایل بکاپ تولیدشده توسط Elm Simple CRM را بارگذاری کنید.</p>
        <input type="file" name="backup_file" accept=".sql" required>
        <div class="form-actions"><button class="btn btn-danger" name="restore_backup" value="1">بازگردانی بکاپ</button></div>
    </form>
</div>
