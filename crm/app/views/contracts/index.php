<div class="toolbar">
    <h2>قراردادها</h2>
    <a class="btn btn-primary" href="<?= e(url('contracts', ['action' => 'create'])) ?>">قرارداد جدید</a>
</div>

<form class="filters" method="get">
    <input type="hidden" name="page" value="contracts">
    <input name="q" placeholder="جستجو در قرارداد، شماره یا مشتری" value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (Contract::statuses() as $status): ?><option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e(fa_label($status)) ?></option><?php endforeach; ?></select>
    <select name="owner_user_id"><option value="">همه مسئول‌ها</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filters['owner_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <label class="check-row"><input type="checkbox" name="renewal_due" value="1" <?= checked(!empty($filters['renewal_due'])) ?>> نیازمند پیگیری تمدید</label>
    <button class="btn btn-light">فیلتر</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>قرارداد</th><th>مشتری</th><th>محصول</th><th>مبلغ</th><th>پایان</th><th>یادآوری تمدید</th><th>وضعیت</th><th>مسئول</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($contracts as $contract): ?>
            <tr>
                <td><strong><?= e($contract['contract_title']) ?></strong><br><span class="muted"><?= e($contract['contract_number']) ?></span></td>
                <td><?= e($contract['customer_name']) ?></td>
                <td><?= e($contract['product']) ?></td>
                <td><?= e(format_money($contract['contract_amount'])) ?></td>
                <td><?= e(fa_date($contract['end_date'])) ?></td>
                <td><?= e(fa_date($contract['renewal_reminder_date'])) ?></td>
                <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(fa_label($contract['status'])) ?></span></td>
                <td><?= e($contract['owner_name'] ?? '') ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-light" href="<?= e(url('contracts', ['action' => 'show', 'id' => $contract['id']])) ?>">نمایش</a>
                    <a class="btn btn-small btn-light" href="<?= e(url('contracts', ['action' => 'edit', 'id' => $contract['id']])) ?>">ویرایش</a>
                    <form method="post" action="<?= e(url('contracts', ['action' => 'delete', 'id' => $contract['id']])) ?>" data-confirm="این قرارداد حذف شود؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$contracts): ?><tr><td colspan="9" class="empty">قراردادی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
