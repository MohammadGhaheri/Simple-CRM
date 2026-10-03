<div class="toolbar">
    <h2><?= e($contract['contract_title']) ?></h2>
    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create', 'customer_id' => $contract['customer_id'], 'deal_id' => $contract['deal_id'], 'contract_id' => $contract['id']])) ?>">ثبت فعالیت</a>
        <a class="btn btn-light" href="<?= e(url('contracts', ['action' => 'edit', 'id' => $contract['id']])) ?>">ویرایش</a>
    </div>
</div>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if (!empty($notice)): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<div class="card">
    <div class="detail-list">
        <div><span>شماره قرارداد</span><?= e($contract['contract_number']) ?></div>
        <div><span>مشتری</span><a href="<?= e(url('customers', ['action' => 'show', 'id' => $contract['customer_id']])) ?>"><?= e($contract['customer_name']) ?></a></div>
        <div><span>فرصت</span><?= !empty($contract['deal_id']) ? '<a href="' . e(url('deals', ['action' => 'show', 'id' => $contract['deal_id']])) . '">' . e($contract['deal_name']) . '</a>' : 'بدون فرصت' ?></div>
        <div><span>محصول / خدمت</span><?= e(product_label($contract['product'])) ?></div>
        <div><span>تعداد خودرو</span><?= e((string) $contract['vehicle_count']) ?></div>
        <div><span>مبلغ قرارداد</span><?= e(format_money($contract['contract_amount'])) ?></div>
        <div><span>شروع</span><?= e(fa_date($contract['start_date'])) ?></div>
        <div><span>پایان</span><?= e(fa_date($contract['end_date'])) ?></div>
        <div><span>یادآوری تمدید</span><?= e(fa_date($contract['renewal_reminder_date'])) ?></div>
        <div><span>وضعیت</span><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(fa_label($contract['status'])) ?></span></div>
        <div><span>مسئول</span><?= e($contract['owner_name'] ?? '') ?></div>
    </div>
    <?php if ($contract['notes']): ?><p class="muted"><?= nl2br(e($contract['notes'])) ?></p><?php endif; ?>
</div>

<?php if (can_view_finance()): ?>
<div class="card" style="margin-top:16px">
    <h3>وضعیت مالی قرارداد</h3>
    <div class="stats-grid">
        <div class="stat-card"><span>مبلغ قرارداد</span><strong><?= e(format_money($financialSummary['contract_amount'])) ?></strong></div>
        <div class="stat-card"><span>دریافتی</span><strong><?= e(format_money($financialSummary['received'])) ?></strong></div>
        <div class="stat-card"><span>مانده</span><strong><?= e(format_money($financialSummary['balance'])) ?></strong></div>
    </div>
    <?php if (can_manage_finance()): ?>
        <details style="margin-top:16px" <?= !empty($errors) ? 'open' : '' ?>><summary class="btn btn-primary">ثبت دریافتی</summary>
            <form method="post" action="<?= e(url('contracts', ['action' => 'payment_create', 'id' => $contract['id']])) ?>" style="margin-top:16px">
                <?= csrf_field() ?><?php $payment = $_POST; require __DIR__ . '/_payment_form.php'; ?>
                <div class="form-actions"><button class="btn btn-primary">ثبت دریافتی</button></div>
            </form>
        </details>
    <?php endif; ?>
    <div class="table-wrap" style="margin-top:16px"><table>
        <thead><tr><th>تاریخ</th><th>مبلغ</th><th>روش پرداخت</th><th>شماره پیگیری / مرجع</th><th>ثبت‌کننده</th><th>توضیحات</th><?php if (can_manage_finance()): ?><th>عملیات</th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($payments as $item): ?><tr>
            <td><?= e(fa_date($item['payment_date'])) ?></td><td><?= e(format_money($item['amount'])) ?></td><td><?= e(payment_method_label($item['payment_method'])) ?></td><td><?= e($item['reference_number'] ?? '') ?></td><td><?= e($item['created_by_name'] ?? 'کاربر حذف‌شده') ?></td><td><?= nl2br(e($item['notes'] ?? '')) ?></td>
            <?php if (can_manage_finance()): ?><td><span class="actions"><a class="btn btn-small btn-light" href="<?= e(url('contracts', ['action' => 'payment_edit', 'payment_id' => $item['id']])) ?>">ویرایش</a><form method="post" action="<?= e(url('contracts', ['action' => 'payment_delete'])) ?>" data-confirm="این دریافتی از محاسبات مالی حذف شود؟"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= e((string) $item['id']) ?>"><button class="btn btn-small btn-danger">حذف</button></form></span></td><?php endif; ?>
        </tr><?php endforeach; ?></tbody>
    </table><?php if (!$payments): ?><div class="empty">هنوز دریافتی‌ای برای این قرارداد ثبت نشده است.</div><?php endif; ?></div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <h3>اسناد قرارداد</h3>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('contracts', ['action' => 'document_upload', 'id' => $contract['id']])) ?>">
        <?= csrf_field() ?>
        <div class="grid grid-2">
            <div>
                <label class="required">نوع سند</label>
                <select name="document_type" required>
                    <?php foreach ($documentTypes as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected($_POST['document_type'] ?? 'Contract', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>عنوان</label><input name="title" maxlength="190" value="<?= e($_POST['title'] ?? '') ?>" placeholder="در صورت خالی بودن از نام فایل استفاده می‌شود"></div>
            <div><label class="required">فایل</label><input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required></div>
            <div><label>توضیحات</label><textarea name="notes"><?= e($_POST['notes'] ?? '') ?></textarea></div>
        </div>
        <p class="muted">فرمت‌های مجاز: تصویر، PDF و Word؛ حداکثر حجم ۱۰ مگابایت.</p>
        <div class="form-actions"><button class="btn btn-primary" type="submit">افزودن سند</button></div>
    </form>

    <?php if ($documents): ?>
        <div class="table-wrap" style="margin-top:16px">
            <table>
                <thead><tr><th>عنوان</th><th>نوع</th><th>فایل</th><th>حجم</th><th>آپلودکننده</th><th>تاریخ</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td><strong><?= e($document['title']) ?></strong><?php if (!empty($document['notes'])): ?><br><span class="muted"><?= nl2br(e($document['notes'])) ?></span><?php endif; ?></td>
                        <td><?= e(ContractDocument::label($document['document_type'])) ?></td>
                        <td><?= e($document['original_name']) ?></td>
                        <td><?= e(format_file_size((int) $document['file_size'])) ?></td>
                        <td><?= e($document['uploaded_by_name'] ?? 'کاربر حذف‌شده') ?></td>
                        <td><?= e(fa_datetime($document['created_at'])) ?></td>
                        <td>
                            <span class="actions">
                                <a class="btn btn-small btn-light" href="<?= e(url('contracts', ['action' => 'document_download', 'document_id' => $document['id']])) ?>">دانلود</a>
                                <?php if (is_admin()): ?>
                                    <form method="post" action="<?= e(url('contracts', ['action' => 'document_delete'])) ?>" data-confirm="این سند از نمایش حذف شود؟">
                                        <?= csrf_field() ?><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>"><button class="btn btn-small btn-danger">حذف</button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">هنوز سندی برای این قرارداد ثبت نشده است.</div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <h3>فعالیت‌های مرتبط با قرارداد</h3>
    <?php foreach ($activities as $activity): ?>
        <p><strong><?= e(fa_label($activity['activity_type'])) ?></strong> <span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span><br><span class="muted"><?= e(fa_date($activity['next_followup_date'] ?: $activity['activity_date'])) ?> - <?= e($activity['summary']) ?></span></p>
    <?php endforeach; ?>
    <?php if (!$activities): ?><div class="empty">فعالیتی برای این قرارداد ثبت نشده است.</div><?php endif; ?>
</div>
