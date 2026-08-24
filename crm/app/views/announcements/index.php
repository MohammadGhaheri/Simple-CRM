<div class="toolbar">
    <h2>مرکز اطلاعیه‌ها</h2>
    <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(url('announcements', ['action' => 'create'])) ?>">اطلاعیه جدید</a><?php endif; ?>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>عنوان</th><th>مخاطب</th><th>انتشار</th><th>وضعیت</th><th>خوانده‌شده</th><th>ایجادکننده</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($announcements as $announcement): ?>
            <tr>
                <td>
                    <strong><?= e($announcement['title']) ?></strong>
                    <span class="ticket-list-date"><?= e(text_excerpt($announcement['body'] ?? '', 90)) ?></span>
                </td>
                <td><?= e(Announcement::audienceLabel($announcement)) ?></td>
                <td><?= e(fa_datetime($announcement['published_at'])) ?></td>
                <td><span class="badge <?= (int) $announcement['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $announcement['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></td>
                <td><?= e((string) ($announcement['read_count'] ?? 0)) ?></td>
                <td><?= e($announcement['created_by_name'] ?? '') ?></td>
                <td class="actions">
                    <?php if (is_admin()): ?>
                        <a class="btn btn-small btn-light" href="<?= e(url('announcements', ['action' => 'edit', 'id' => $announcement['id']])) ?>">ویرایش</a>
                        <form method="post" action="<?= e(url('announcements', ['action' => 'delete', 'id' => $announcement['id']])) ?>" data-confirm="این اطلاعیه از نمایش مخفی شود؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$announcements): ?><tr><td colspan="7" class="empty">هنوز اطلاعیه‌ای ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
