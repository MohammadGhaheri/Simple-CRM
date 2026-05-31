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

    <div class="form-actions"><button class="btn btn-primary">ذخیره تنظیمات</button></div>
</form>
