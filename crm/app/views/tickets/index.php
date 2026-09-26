<?php
$listContext = Ticket::listParams($filters, true);
$currentUserId = current_user_id();
$isMine = (int) ($filters['assigned_user_id'] ?? 0) === $currentUserId;
$mineParams = Ticket::listParams($filters);
if ($isMine) {
    unset($mineParams['assigned_user_id']);
} else {
    $mineParams['assigned_user_id'] = $currentUserId;
}
$activeFilters = [];
if (!empty($filters['q'])) {
    $activeFilters['q'] = 'جستجو: ' . $filters['q'];
}
foreach (['status' => 'وضعیت', 'priority' => 'اولویت', 'category' => 'دسته'] as $field => $label) {
    if (!empty($filters[$field])) {
        $activeFilters[$field] = $label . ': ' . Ticket::label($filters[$field]);
    }
}
if (!empty($filters['assigned_user_id'])) {
    $assignedLabel = 'نامشخص';
    foreach ($users as $user) {
        if ((int) $user['id'] === (int) $filters['assigned_user_id']) {
            $assignedLabel = $user['name'];
            break;
        }
    }
    $activeFilters['assigned_user_id'] = 'مسئول: ' . $assignedLabel;
}
?>

<div class="toolbar ticket-queue-toolbar">
    <h2>تیکت‌های مشتریان <span class="result-count">— <?= e((string) count($tickets)) ?> نتیجه</span></h2>
    <div class="actions">
        <?php $totalUnread = array_sum(array_map(static fn($ticket) => (int) ($ticket['unread_count'] ?? 0), $tickets)); ?>
        <?php if ($totalUnread > 0): ?><span class="badge badge-primary"><?= e((string) $totalUnread) ?> پیام جدید</span><?php endif; ?>
        <a class="btn <?= $isMine ? 'btn-primary' : 'btn-light' ?>" href="<?= e(url('tickets', $mineParams)) ?>"><?= $isMine ? 'نمایش همه مسئول‌ها' : 'تیکت‌های من' ?></a>
        <a class="btn btn-primary" href="<?= e(url('tickets', array_merge(['action' => 'create'], $listContext))) ?>">تیکت جدید برای مشتری</a>
    </div>
</div>

<?php if (!empty($notice)): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>

<form class="filters ticket-queue-filters" method="get">
    <input type="hidden" name="page" value="tickets">
    <input name="q" placeholder="جستجوی کد، موضوع، مشتری..." value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (Ticket::statuses() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['status'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select>
    <select name="priority"><option value="">همه اولویت‌ها</option><?php foreach (Ticket::priorities() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['priority'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select>
    <select name="category"><option value="">همه دسته‌ها</option><?php foreach (Ticket::categories() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['category'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select>
    <select name="assigned_user_id"><option value="">همه مسئول‌ها</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filters['assigned_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-light">اعمال فیلتر</button>
</form>

<?php if ($activeFilters): ?>
    <div class="filter-chip-row">
        <?php foreach ($activeFilters as $field => $label): ?>
            <?php $withoutFilter = Ticket::listParams($filters); unset($withoutFilter[$field]); ?>
            <a class="filter-chip" href="<?= e(url('tickets', $withoutFilter)) ?>"><span><?= e($label) ?></span><b aria-hidden="true">×</b></a>
        <?php endforeach; ?>
        <a class="clear-filters" href="<?= e(url('tickets')) ?>">پاک کردن همه فیلترها</a>
    </div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>کد</th><th>موضوع</th><th>مشتری</th><th>مخاطب</th><th>مسئول</th><th>دسته</th><th>اولویت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <?php $unreadCount = (int) ($ticket['unread_count'] ?? 0); ?>
            <tr id="ticket-<?= e((string) $ticket['id']) ?>" class="<?= $unreadCount > 0 ? 'ticket-row-unread' : '' ?>">
                <td><?= e($ticket['ticket_code']) ?><span class="ticket-list-date"><?= e(fa_datetime($ticket['created_at'])) ?></span></td>
                <td><strong><?= e($ticket['subject']) ?></strong><?php if ($unreadCount > 0): ?> <span class="badge badge-primary unread-badge"><?= e((string) $unreadCount) ?> جدید</span><?php endif; ?></td>
                <td><?= e($ticket['customer_name']) ?> <?= (int) ($ticket['is_vip'] ?? 0) === 1 ? '<span class="badge badge-warning">VIP</span>' : '' ?></td>
                <td><?= e($ticket['contact_name']) ?></td>
                <td><?= !empty($ticket['assigned_name']) ? e($ticket['assigned_name']) : '<span class="muted">بدون مسئول</span>' ?></td>
                <td><?= e(Ticket::label($ticket['category'])) ?></td>
                <td><span class="badge <?= e($ticket['priority'] === 'Urgent' || $ticket['priority'] === 'High' ? 'badge-danger' : 'badge-muted') ?>"><?= e(Ticket::label($ticket['priority'])) ?></span></td>
                <td><span class="badge <?= e(badge_class($ticket['status'])) ?>"><?= e(Ticket::label($ticket['status'])) ?></span></td>
                <td class="actions">
                    <a class="btn btn-small <?= $unreadCount > 0 ? 'btn-primary' : 'btn-light' ?>" href="<?= e(ticket_edit_url((int) $ticket['id'], $listContext)) ?>"><?= $unreadCount > 0 ? 'مشاهده پیام' : 'بررسی' ?></a>
                    <?php if (is_admin()): ?>
                        <form method="post" action="<?= e(url('tickets', ['action' => 'delete', 'id' => $ticket['id']])) ?>" data-confirm="این تیکت از نمایش مخفی شود؟">
                            <?= csrf_field() ?>
                            <?php foreach (Ticket::queuePostFields($listContext, true) as $key => $value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?>
                            <button class="btn btn-small btn-danger">حذف</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="9" class="empty">تیکتی مطابق این صف یافت نشد.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
