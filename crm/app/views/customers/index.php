<div class="toolbar">
    <h2>فهرست مشتریان</h2>
    <a class="btn btn-primary" href="<?= e(url('customers', ['action' => 'create'])) ?>">مشتری جدید</a>
</div>

<form class="filters" method="get">
    <input type="hidden" name="page" value="customers">
    <input name="q" placeholder="جستجو..." value="<?= e($filters['q'] ?? '') ?>">
    <select name="customer_type"><option value="">همه نوع‌ها</option><?php foreach (customer_type_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['customer_type'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="sales_status"><option value="">همه وضعیت‌ها</option><?php foreach (sales_status_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['sales_status'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="owner_user_id"><option value="">همه مالک‌ها</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filters['owner_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <input name="city" placeholder="شهر" value="<?= e($filters['city'] ?? '') ?>">
    <button class="btn btn-light" type="submit">اعمال فیلتر</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>کد</th><th>نام</th><th>نوع</th><th>شهر</th><th>وضعیت</th><th>مالک</th><th>پیگیری بعدی</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($customers as $customer): ?>
            <tr>
                <td><?= e($customer['customer_code']) ?></td>
                <td><strong><?= e($customer['customer_name']) ?></strong><br><span class="muted"><?= e($customer['industry']) ?></span></td>
                <td><?= e(fa_label($customer['customer_type'])) ?></td>
                <td><?= e($customer['city']) ?></td>
                <td><span class="badge <?= e(badge_class($customer['sales_status'])) ?>"><?= e(fa_label($customer['sales_status'])) ?></span></td>
                <td><?= e($customer['owner_name']) ?></td>
                <td><?= e(fa_date($customer['next_followup_date'])) ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-light" href="<?= e(url('customers', ['action' => 'show', 'id' => $customer['id']])) ?>">نمایش</a>
                    <a class="btn btn-small btn-light" href="<?= e(url('customers', ['action' => 'edit', 'id' => $customer['id']])) ?>">ویرایش</a>
                    <form method="post" action="<?= e(url('customers', ['action' => 'delete', 'id' => $customer['id']])) ?>" data-confirm="حذف این مشتری همه مخاطب‌ها، فرصت‌ها، قراردادها و فعالیت‌های مرتبط را حذف می‌کند. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="8" class="empty">موردی یافت نشد.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
