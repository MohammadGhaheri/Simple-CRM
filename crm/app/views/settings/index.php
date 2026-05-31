<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>تنظیمات عمومی</h2>
    <div class="grid grid-2">
        <div><label class="required">عنوان سامانه</label><input required name="app_title" value="<?= e($settings['app_title'] ?? '') ?>"></div>
        <div><label>زیرعنوان سامانه</label><input name="app_subtitle" value="<?= e($settings['app_subtitle'] ?? '') ?>"></div>
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

    <h2 style="margin-top:22px">تنظیمات پیامک sms.ir</h2>
    <div class="grid grid-2">
        <div><label>توکن API</label><input name="sms_api_key" value="<?= e($settings['sms_api_key'] ?? '') ?>"></div>
        <div><label>خط ارسال کننده</label><input name="sms_line_number" value="<?= e($settings['sms_line_number'] ?? '') ?>"></div>
        <div><label>موبایل مدیر برای خلاصه روزانه</label><input name="sms_admin_mobile" value="<?= e($settings['sms_admin_mobile'] ?? '') ?>"></div>
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
    <p><label><input type="checkbox" name="sms_daily_summary_enabled" value="1" <?= checked(($settings['sms_daily_summary_enabled'] ?? '0') === '1') ?> style="width:auto"> ارسال خلاصه روزانه با cron</label></p>

    <div class="form-actions"><button class="btn btn-primary">ذخیره تنظیمات</button></div>
</form>
