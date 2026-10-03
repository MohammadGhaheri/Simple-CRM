<div class="toolbar">
    <h2>نمای مالی قراردادها</h2>
</div>

<div class="stats-grid">
    <div class="stat-card"><span>مبلغ قراردادها</span><strong><?= e(format_money($totals['contract_amount'])) ?></strong></div>
    <div class="stat-card"><span>دریافتی ثبت‌شده</span><strong><?= e(format_money($totals['received'])) ?></strong></div>
    <div class="stat-card"><span>مانده قراردادها</span><strong><?= e(format_money($totals['balance'])) ?></strong></div>
</div>

<form class="filters" method="get">
    <input type="hidden" name="page" value="finance">
    <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="جستجوی مشتری، قرارداد یا شماره قرارداد...">
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (Contract::statuses() as $status): ?><option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e(fa_label($status)) ?></option><?php endforeach; ?></select>
    <select name="owner_user_id"><option value="">همه مسئولان</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filters['owner_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-light">اعمال فیلتر</button>
</form>

<div class="table-wrap card">
    <table>
        <thead><tr><th>مشتری</th><th>قرارداد</th><th>شماره قرارداد</th><th>وضعیت</th><th>مبلغ قرارداد</th><th>دریافتی</th><th>مانده</th><th>مالک قرارداد</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['customer_name']) ?></td><td><?= e($row['contract_title']) ?></td><td><?= e($row['contract_number']) ?></td>
                <td><span class="badge <?= e(badge_class($row['status'])) ?>"><?= e(fa_label($row['status'])) ?></span></td>
                <td><?= e(format_money($row['contract_amount'])) ?></td><td><?= e(format_money($row['received_amount'])) ?></td><td><?= e(format_money($row['balance_amount'])) ?></td>
                <td><?= e($row['owner_name'] ?? '') ?></td>
                <td><a class="btn btn-small btn-light" href="<?= e(url('contracts', ['action' => 'show', 'id' => $row['id']])) ?>"><?= can_manage_finance() ? 'مدیریت دریافتی' : 'مشاهده قرارداد' ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$rows): ?><div class="empty">قراردادی مطابق فیلترها پیدا نشد.</div><?php endif; ?>
</div>
