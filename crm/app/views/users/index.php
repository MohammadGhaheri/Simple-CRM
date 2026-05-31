<div class="toolbar">
    <h2>مدیریت کاربران</h2>
    <a class="btn btn-primary" href="<?= e(url('users', ['action' => 'create'])) ?>">کاربر جدید</a>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>نام</th>
                <th>ایمیل</th>
                <th>نقش</th>
                <th>وضعیت</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usersList as $user): ?>
            <tr>
                <td><strong><?= e($user['name']) ?></strong></td>
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
