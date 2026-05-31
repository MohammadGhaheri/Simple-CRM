<div class="toolbar">
    <h2>فرصت‌های فروش</h2>
    <a class="btn btn-primary" href="<?= e(url('deals', ['action' => 'create'])) ?>">فرصت جدید</a>
</div>
<form class="filters" method="get">
    <input type="hidden" name="page" value="deals">
    <input name="q" placeholder="جستجو..." value="<?= e($filters['q'] ?? '') ?>">
    <select name="deal_stage"><option value="">همه مراحل</option><?php foreach (deal_stage_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['deal_stage'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="product"><option value="">همه محصولات</option><?php foreach (product_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filters['product'] ?? '', $option) ?>><?= e($option) ?></option><?php endforeach; ?></select>
    <select name="owner_user_id"><option value="">همه مالک‌ها</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filters['owner_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-light">اعمال فیلتر</button>
</form>
<div class="table-wrap">
    <table>
        <thead><tr><th>نام فرصت</th><th>مشتری</th><th>محصول</th><th>مرحله</th><th>مبلغ</th><th>احتمال</th><th>وزنی</th><th>مالک</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($deals as $deal): ?>
            <tr>
                <td><strong><?= e($deal['deal_name']) ?></strong></td>
                <td><?= e($deal['customer_name']) ?></td>
                <td><?= e($deal['product']) ?></td>
                <td><span class="badge <?= e(badge_class($deal['deal_stage'])) ?>"><?= e(fa_label($deal['deal_stage'])) ?></span></td>
                <td><?= e(format_money($deal['estimated_amount'])) ?></td>
                <td><?= e((string) $deal['probability']) ?>٪</td>
                <td><?= e(format_money($deal['weighted_amount'])) ?></td>
                <td><?= e($deal['owner_name']) ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-light" href="<?= e(url('deals', ['action' => 'show', 'id' => $deal['id']])) ?>">نمایش</a>
                    <a class="btn btn-small btn-light" href="<?= e(url('deals', ['action' => 'edit', 'id' => $deal['id']])) ?>">ویرایش</a>
                    <form method="post" action="<?= e(url('deals', ['action' => 'delete', 'id' => $deal['id']])) ?>" data-confirm="این فرصت حذف شود؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$deals): ?><tr><td colspan="9" class="empty">فرصتی یافت نشد.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
