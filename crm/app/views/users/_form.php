<?php $user = $user ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="grid grid-2">
    <div>
        <label class="required">نام</label>
        <input required name="name" value="<?= e($user['name'] ?? '') ?>">
    </div>
    <div>
        <label class="required">ایمیل</label>
        <input required type="email" name="email" value="<?= e($user['email'] ?? '') ?>">
    </div>
    <div>
        <label class="<?= empty($user['id']) ? 'required' : '' ?>">رمز عبور</label>
        <input <?= empty($user['id']) ? 'required' : '' ?> type="password" name="password" autocomplete="new-password">
        <?php if (!empty($user['id'])): ?><span class="muted">برای حفظ رمز فعلی خالی بگذارید.</span><?php endif; ?>
    </div>
    <div>
        <label>نقش</label>
        <select name="role">
            <?php foreach (User::roles() as $role => $label): ?>
                <option value="<?= e($role) ?>" <?= selected($user['role'] ?? 'sales', $role) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>تصویر کاربر</label>
        <input type="file" name="avatar_file" accept="image/*">
        <?php if (!empty($user['avatar_path'])): ?><img class="avatar-preview" src="<?= e($user['avatar_path']) ?>" alt=""><?php endif; ?>
    </div>
</div>

<p>
    <label>
        <input type="checkbox" name="is_active" value="1" <?= checked((int) ($user['is_active'] ?? 1)) ?> style="width:auto">
        کاربر فعال باشد
    </label>
</p>

<div class="form-actions">
    <button class="btn btn-primary">ذخیره</button>
    <a class="btn btn-light" href="<?= e(url('users')) ?>">انصراف</a>
</div>
