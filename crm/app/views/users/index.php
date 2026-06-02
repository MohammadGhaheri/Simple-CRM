<div class="toolbar">
    <h2>مدیریت کاربران</h2>
    <a class="btn btn-primary" href="<?= e(url('users', ['action' => 'create'])) ?>">کاربر جدید</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>
<?php if (!empty($transferred)): ?>
    <div class="alert alert-success"><?= e((string) $transferred) ?> مورد از وظایف و قراردادهای باز منتقل شد.</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <h3>انتقال وظایف فردی</h3>
    <p class="muted">فعالیت‌های باز و قراردادهای فعال یک کاربر را به کاربر دیگر منتقل کنید.</p>
    <form class="filters" method="post" action="<?= e(url('users', ['action' => 'transfer_tasks'])) ?>">
        <?= csrf_field() ?>
        <select name="from_user_id" required>
            <option value="">از کاربر</option>
            <?php foreach ($usersList as $user): ?>
                <option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="to_user_id" required>
            <option value="">به کاربر</option>
            <?php foreach ($usersList as $user): ?>
                <option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-light" data-confirm="وظایف باز و قراردادهای فعال منتقل شوند؟">انتقال</button>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>کاربر</th>
                <th>ایمیل</th>
                <th>نقش</th>
                <th>وضعیت</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usersList as $user): ?>
            <tr>
                <td class="user-cell">
                    <?php if (!empty($user['avatar_path'])): ?><img class="user-avatar" src="<?= e($user['avatar_path']) ?>" alt=""><?php endif; ?>
                    <strong><?= e($user['name']) ?></strong>
                </td>
                <td><?= e($user['email']) ?></td>
                <td><span class="badge badge-primary"><?= e(User::roleLabel($user['role'])) ?></span></td>
                <td>
                    <?php if ((int) $user['is_active'] === 1): ?>
                        <span class="badge badge-success">فعال</span>
                    <?php else: ?>
                        <span class="badge badge-muted">غیرفعال</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a class="btn btn-small btn-light" href="<?= e(url('users', ['action' => 'edit', 'id' => $user['id']])) ?>">ویرایش</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$usersList): ?><tr><td colspan="5" class="empty">کاربری ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
