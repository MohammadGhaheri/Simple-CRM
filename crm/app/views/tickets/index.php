<div class="toolbar">
    <h2>تیکت‌های مشتریان</h2>
    <?php $totalUnread = array_sum(array_map(static fn($ticket) => (int) ($ticket['unread_count'] ?? 0), $tickets)); ?>
    <?php if ($totalUnread > 0): ?><span class="badge badge-primary"><?= e((string) $totalUnread) ?> پیام جدید</span><?php endif; ?>
</div>

<form class="filters" method="get">
    <input type="hidden" name="page" value="tickets">
    <input name="q" placeholder="جستجوی کد، موضوع، مشتری..." value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (Ticket::statuses() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['status'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select>
    <select name="priority"><option value="">همه اولویت‌ها</option><?php foreach (Ticket::priorities() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['priority'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select>
    <select name="category"><option value="">همه دسته‌ها</option><?php foreach (Ticket::categories() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['category'] ?? '', $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-light">اعمال فیلتر</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>کد</th><th>موضوع</th><th>مشتری</th><th>مخاطب</th><th>دسته</th><th>اولویت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <?php $unreadCount = (int) ($ticket['unread_count'] ?? 0); ?>
            <tr class="<?= $unreadCount > 0 ? 'ticket-row-unread' : '' ?>">
                <td><?= e($ticket['ticket_code']) ?></td>
                <td><strong><?= e($ticket['subject']) ?></strong><?php if ($unreadCount > 0): ?> <span class="badge badge-primary unread-badge"><?= e((string) $unreadCount) ?> جدید</span><?php endif; ?></td>
                <td><?= e($ticket['customer_name']) ?> <?= (int) ($ticket['is_vip'] ?? 0) === 1 ? '<span class="badge badge-warning">VIP</span>' : '' ?></td>
                <td><?= e($ticket['contact_name']) ?></td>
                <td><?= e(Ticket::label($ticket['category'])) ?></td>
                <td><span class="badge <?= e($ticket['priority'] === 'Urgent' || $ticket['priority'] === 'High' ? 'badge-danger' : 'badge-muted') ?>"><?= e(Ticket::label($ticket['priority'])) ?></span></td>
                <td><span class="badge <?= e(badge_class($ticket['status'])) ?>"><?= e(Ticket::label($ticket['status'])) ?></span></td>
                <td class="actions">
                    <a class="btn btn-small <?= $unreadCount > 0 ? 'btn-primary' : 'btn-light' ?>" href="<?= e(url('tickets', ['action' => 'edit', 'id' => $ticket['id']])) ?>"><?= $unreadCount > 0 ? 'مشاهده پیام' : 'بررسی' ?></a>
                    <?php if (is_admin()): ?>
                        <form method="post" action="<?= e(url('tickets', ['action' => 'delete', 'id' => $ticket['id']])) ?>" data-confirm="این تیکت از نمایش مخفی شود؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="8" class="empty">تیکتی یافت نشد.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
