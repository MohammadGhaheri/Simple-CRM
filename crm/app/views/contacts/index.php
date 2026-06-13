<div class="toolbar">
    <h2>مخاطبین شرکت‌ها</h2>
    <a class="btn btn-primary" href="<?= e(url('contacts', ['action' => 'create'])) ?>">مخاطب جدید</a>
</div>

<form class="filters" method="get">
    <input type="hidden" name="page" value="contacts">
    <input name="q" placeholder="جستجوی نام، موبایل، ایمیل، شرکت..." value="<?= e($filters['q'] ?? '') ?>">
    <select name="customer_id">
        <option value="">همه مشتریان</option>
        <?php foreach ($customers as $customer): ?>
            <option value="<?= e((string) $customer['id']) ?>" <?= selected($filters['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="portal_enabled">
        <option value="">همه دسترسی‌ها</option>
        <option value="1" <?= selected($filters['portal_enabled'] ?? '', '1') ?>>پرتال فعال</option>
        <option value="0" <?= selected($filters['portal_enabled'] ?? '', '0') ?>>پرتال غیرفعال</option>
    </select>
    <button class="btn btn-light">اعمال فیلتر</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>نام</th><th>شرکت</th><th>سمت</th><th>موبایل</th><th>ایمیل</th><th>نقش تماس</th><th>پرتال</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($contacts as $contact): ?>
            <tr>
                <td><strong><?= e($contact['contact_name']) ?></strong></td>
                <td><a href="<?= e(url('customers', ['action' => 'show', 'id' => $contact['customer_id']])) ?>"><?= e($contact['customer_name']) ?></a></td>
                <td><?= e($contact['position']) ?></td>
                <td><?= e($contact['mobile']) ?></td>
                <td><?= e($contact['email']) ?></td>
                <td><?= $contact['is_primary'] ? '<span class="badge badge-primary">اصلی</span>' : '<span class="badge badge-muted">عادی</span>' ?></td>
                <td><?= $contact['portal_enabled'] ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-muted">غیرفعال</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-light" href="<?= e(url('contacts', ['action' => 'edit', 'id' => $contact['id']])) ?>">ویرایش</a>
                    <?php if (is_admin()): ?>
                        <form method="post" action="<?= e(url('contacts', ['action' => 'delete', 'id' => $contact['id']])) ?>" data-confirm="این مخاطب و تیکت‌های مرتبط از نمایش مخفی شوند؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$contacts): ?><tr><td colspan="8" class="empty">مخاطبی یافت نشد.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
