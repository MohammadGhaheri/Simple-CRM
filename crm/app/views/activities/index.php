<div class="toolbar">
    <h2>فعالیت‌ها</h2>
    <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create'])) ?>">فعالیت جدید</a>
</div>
<form class="filters" method="get">
    <input type="hidden" name="page" value="activities">
    <input name="q" placeholder="جستجو..." value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (activity_status_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['status'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="activity_type"><option value="">همه نوع‌ها</option><?php foreach (activity_type_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['activity_type'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="owner_user_id"><option value="">همه مالک‌ها</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filters['owner_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <input class="date-input" name="date_from" placeholder="از تاریخ شمسی" value="<?= e($filters['date_from'] ?? '') ?>">
    <input class="date-input" name="date_to" placeholder="تا تاریخ شمسی" value="<?= e($filters['date_to'] ?? '') ?>">
    <button class="btn btn-light">اعمال فیلتر</button>
</form>
<div class="table-wrap">
    <table>
        <thead><tr><th>تاریخ</th><th>مشتری</th><th>فرصت</th><th>نوع</th><th>خلاصه</th><th>پیگیری بعدی</th><th>وضعیت</th><th>مالک</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($activities as $activity): ?>
            <tr>
                <td><?= e(fa_date($activity['activity_date'])) ?></td>
                <td><?= e($activity['customer_name']) ?></td>
                <td><?= e($activity['deal_name']) ?></td>
                <td><?= e(fa_label($activity['activity_type'])) ?></td>
                <td><strong><?= e($activity['summary']) ?></strong><br><span class="muted"><?= e($activity['next_action']) ?></span></td>
                <td><?= e(fa_date($activity['next_followup_date'])) ?></td>
                <td><span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span></td>
                <td><?= e($activity['owner_name']) ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-light" href="<?= e(url('activities', ['action' => 'edit', 'id' => $activity['id']])) ?>">ویرایش</a>
                    <form method="post" action="<?= e(url('activities', ['action' => 'delete', 'id' => $activity['id']])) ?>" data-confirm="این فعالیت حذف شود؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$activities): ?><tr><td colspan="9" class="empty">فعالیتی یافت نشد.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
