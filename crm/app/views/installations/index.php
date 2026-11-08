<?php
$statusLabels = [
    'ready' => 'آماده ارجاع', 'in_progress' => 'در حال اجرا', 'pending_approval' => 'در انتظار تأیید', 'completed' => 'تکمیل‌شده',
    'scheduled' => 'برنامه‌ریزی‌شده', 'failed' => 'نصب ناموفق', 'installed' => 'نصب انجام‌شده',
    'approved' => 'تأییدشده', 'cancelled' => 'لغوشده',
];
?>
<div class="toolbar"><h2>تعهدات نصب</h2></div>
<p class="muted">این نما فقط اطلاعات اجرایی را نمایش می‌دهد و شامل مبلغ قرارداد، دریافتی یا مانده مالی نیست.</p>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">تغییرات عملیاتی با موفقیت ثبت شد.</div><?php endif; ?>
<?php if (!empty($_SESSION['installation_error'])): ?><div class="alert alert-danger"><?= e((string) $_SESSION['installation_error']) ?></div><?php unset($_SESSION['installation_error']); endif; ?>

<form class="filters" method="get">
    <input type="hidden" name="page" value="installations">
    <input name="customer_id" type="number" min="1" placeholder="شناسه مشتری" value="<?= e((string) ($filters['customer_id'] ?? '')) ?>">
    <input name="device" placeholder="دستگاه / مدل / SKU" value="<?= e((string) ($filters['device'] ?? '')) ?>">
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (['ready','in_progress','pending_approval','completed'] as $value): ?><option value="<?= e($value) ?>" <?= selected($filters['status'] ?? '', $value) ?>><?= e($statusLabels[$value]) ?></option><?php endforeach; ?></select>
    <input class="date-input" name="date_from" placeholder="از تاریخ" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
    <input class="date-input" name="date_to" placeholder="تا تاریخ" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
    <button class="btn btn-primary">فیلتر</button>
</form>

<p class="installation-mobile-hint">برای دیدن ستون‌های بیشتر، جدول را به چپ بکشید.</p>
<div class="table-wrap"><table>
    <thead><tr><th>مشتری</th><th>قرارداد / فروش</th><th>دستگاه</th><th>تعهد</th><th>نصب‌شده</th><th>تأییدشده</th><th>تحویل قطعی</th><th>مانده</th><th>وضعیت</th><th>مسئول اجرا</th><th>آخرین فعالیت</th><th></th></tr></thead>
    <tbody><?php foreach ($obligations as $row): ?><tr>
        <td><?= e($row['customer_name']) ?></td>
        <td><?= e($row['contract_number'] ?: $row['contract_title']) ?></td>
        <td><?= e($row['item_title']) ?><div class="muted"><?= e((string) ($row['sku'] ?: $row['product'])) ?></div></td>
        <td><?= e((string) $row['committed_quantity']) ?></td><td><?= e((string) $row['installed_quantity']) ?></td>
        <td><?= e((string) $row['approved_quantity']) ?></td><td><?= e((string) $row['delivered_quantity']) ?></td>
        <td><strong><?= e((string) $row['remaining_quantity']) ?></strong></td><td><?= e($statusLabels[$row['obligation_status']] ?? $row['obligation_status']) ?></td>
        <td><?= e((string) ($row['operations_assignees'] ?: 'تخصیص‌نیافته')) ?></td><td><?= e(fa_datetime($row['last_activity_at'])) ?></td>
        <td><a class="btn btn-small btn-light" href="<?= e(url('installations', ['action' => 'show', 'id' => $row['contract_item_id']])) ?>">جزئیات</a></td>
    </tr><?php endforeach; ?><?php if (!$obligations): ?><tr><td colspan="12" class="empty">تعهد نصب مجازی مطابق فیلترها پیدا نشد.</td></tr><?php endif; ?></tbody>
</table></div>

<?php if ($obligation): ?>
<div class="card" style="margin-top:18px">
    <h3><?= e($obligation['customer_name']) ?> — <?= e($obligation['item_title']) ?></h3>
    <div class="installation-stats">
        <div class="installation-stat"><span>تعداد تعهد</span><strong><?= e((string) $obligation['committed_quantity']) ?></strong></div>
        <div class="installation-stat"><span>نصب تأییدشده</span><strong><?= e((string) $obligation['approved_quantity']) ?></strong></div>
        <div class="installation-stat"><span>تحویل قطعی</span><strong><?= e((string) $obligation['delivered_quantity']) ?></strong></div>
        <div class="installation-stat"><span>مانده</span><strong><?= e((string) $obligation['remaining_quantity']) ?></strong></div>
    </div>
</div>

<?php if ($canMutateInstallations): ?>
<details class="card" style="margin-top:16px"><summary class="btn btn-primary">ثبت نصب دستگاه</summary>
<form method="post" action="<?= e(url('installations', ['action' => 'record_create', 'id' => $obligation['contract_item_id']])) ?>">
    <?= csrf_field() ?><input type="hidden" name="contract_item_id" value="<?= e((string) $obligation['contract_item_id']) ?>">
    <div class="grid grid-3">
        <div><label>مدل دستگاه</label><input name="device_model" value="<?= e($obligation['item_title']) ?>" required></div>
        <div><label>IMEI</label><input name="imei" maxlength="64"></div><div><label>Serial Number</label><input name="serial_number" maxlength="120"></div>
        <div><label>پلاک خودرو</label><input name="license_plate" maxlength="40"></div><div><label>VIN / شاسی</label><input name="vin" maxlength="80"></div>
        <div><label>نوع خودرو</label><input name="vehicle_type" maxlength="120"></div>
        <div><label>تکنسین</label><select name="technician_user_id"><option value="">انتخاب نشده</option><?php foreach ($operationsUsers as $user): ?><option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>مسئول اجرا</label><select name="assigned_operations_user_id"><option value="">انتخاب نشده</option><?php foreach ($operationsUsers as $user): ?><option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>زمان برنامه‌ریزی</label><input type="datetime-local" name="scheduled_at"></div>
        <div><label>وضعیت اولیه</label><select name="status"><option value="scheduled">برنامه‌ریزی‌شده</option><option value="in_progress">در حال اجرا</option></select></div>
    </div><label>توضیحات</label><textarea name="notes"></textarea><button class="btn btn-primary">ثبت نصب</button>
</form></details>
<?php endif; ?>

<div class="card" style="margin-top:16px"><h3>دستگاه‌ها و نصب‌ها</h3><div class="table-wrap"><table>
<thead><tr><th>شناسه دستگاه</th><th>خودرو</th><th>تکنسین</th><th>وضعیت</th><th>تحویل</th><?php if ($canMutateInstallations): ?><th>تغییر وضعیت</th><?php endif; ?></tr></thead>
<tbody><?php foreach ($installationRows as $record): ?><tr>
<td><?= e($record['imei'] ?: $record['serial_number']) ?><div class="muted"><?= e($record['device_model']) ?></div></td>
<td><?= e((string) ($record['license_plate'] ?: $record['vin'])) ?></td><td><?= e((string) ($record['technician_name'] ?: 'ثبت‌نشده')) ?></td>
<td><?= e($statusLabels[$record['status']] ?? $record['status']) ?></td><td><?= !empty($record['is_delivered']) ? 'تحویل قطعی' : '—' ?></td>
<?php if ($canMutateInstallations): ?>
<td>
<?php $nextStatuses = InstallationRecord::allowedNextStatuses((string) $record['status']); ?>
<?php if ($nextStatuses): ?>
<form method="post" action="<?= e(url('installations', ['action' => 'transition', 'id' => $obligation['contract_item_id']])) ?>">
<?= csrf_field() ?>
<input type="hidden" name="contract_item_id" value="<?= e((string) $obligation['contract_item_id']) ?>">
<input type="hidden" name="installation_id" value="<?= e((string) $record['id']) ?>">
<label>وضعیت بعدی</label>
<select name="to_status" required>
<?php foreach ($nextStatuses as $nextStatus): ?>
<option value="<?= e($nextStatus) ?>"><?= e($statusLabels[$nextStatus] ?? $nextStatus) ?></option>
<?php endforeach; ?>
</select>
<input name="reason" maxlength="500" required placeholder="دلیل تغییر"><button class="btn btn-small btn-light">ثبت</button>
</form>
<?php else: ?><span class="muted">بدون تغییر مجاز</span><?php endif; ?>
</td>
<?php endif; ?>
</tr><?php endforeach; ?><?php if (!$installationRows): ?><tr><td colspan="6" class="empty">هنوز نصب دستگاهی ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div></div>

<?php if ($canMutateInstallations): $deliverable = array_filter($installationRows, static fn(array $row): bool => $row['status'] === 'approved' && empty($row['is_delivered'])); ?>
<details class="card" style="margin-top:16px"><summary class="btn btn-primary">ثبت رسید تحویل</summary>
<form method="post" enctype="multipart/form-data" action="<?= e(url('installations', ['action' => 'delivery_create', 'id' => $obligation['contract_item_id']])) ?>">
<?= csrf_field() ?><input type="hidden" name="contract_item_id" value="<?= e((string) $obligation['contract_item_id']) ?>">
<div class="grid grid-3"><div><label>شماره رسید</label><input name="receipt_number" maxlength="120" required></div><div><label>تاریخ تحویل</label><input type="datetime-local" name="delivered_at" required></div><div><label>تحویل‌گیرنده</label><input name="receiver_name" maxlength="190" required></div><div><label>فایل رسید</label><input type="file" name="evidence_file"></div></div>
<label>دستگاه‌های تأییدشده</label><?php foreach ($deliverable as $record): ?><label style="display:block"><input type="checkbox" name="installation_ids[]" value="<?= e((string) $record['id']) ?>"> <?= e((string) ($record['imei'] ?: $record['serial_number'])) ?></label><?php endforeach; ?>
<button class="btn btn-primary">ثبت و تأیید رسید</button></form></details>
<?php endif; ?>

<div class="card" style="margin-top:16px"><h3>رسیدهای تحویل</h3><div class="table-wrap"><table><thead><tr><th>شماره</th><th>تاریخ</th><th>تحویل‌گیرنده</th><th>وضعیت</th><th>مدرک</th><?php if ($canMutateInstallations): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php foreach ($deliveryReceipts as $receipt): ?><tr><td><?= e($receipt['receipt_number']) ?></td><td><?= e(fa_datetime($receipt['delivered_at'])) ?></td><td><?= e($receipt['receiver_name']) ?></td><td><?= $receipt['status'] === 'approved' ? 'تأییدشده' : 'باطل‌شده' ?></td>
<td><?php if ($receipt['evidence_path']): ?><a href="<?= e(url('installations', ['action' => 'evidence_download', 'receipt_id' => $receipt['id']])) ?>">دانلود</a><?php else: ?>—<?php endif; ?></td>
<?php if ($canMutateInstallations): ?><td><?php if ($receipt['status'] === 'approved'): ?><form method="post" action="<?= e(url('installations', ['action' => 'delivery_cancel', 'id' => $obligation['contract_item_id']])) ?>"><?= csrf_field() ?><input type="hidden" name="contract_item_id" value="<?= e((string) $obligation['contract_item_id']) ?>"><input type="hidden" name="receipt_id" value="<?= e((string) $receipt['id']) ?>"><input name="reason" maxlength="500" required placeholder="دلیل ابطال"><button class="btn btn-small btn-light">ابطال</button></form><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
