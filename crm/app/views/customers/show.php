<div class="toolbar">
    <h2><?= e($customer['customer_name']) ?> <?= (int) ($customer['is_vip'] ?? 0) === 1 ? '<span class="badge badge-warning">VIP</span>' : '' ?></h2>
    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create', 'customer_id' => $customer['id']])) ?>">ثبت فعالیت</a>
        <a class="btn btn-light" href="<?= e(url('deals', ['action' => 'create', 'customer_id' => $customer['id']])) ?>">فرصت جدید</a>
        <a class="btn btn-light" href="<?= e(url('contracts', ['action' => 'create', 'customer_id' => $customer['id']])) ?>">قرارداد جدید</a>
        <a class="btn btn-light" href="<?= e(url('contacts', ['action' => 'create', 'customer_id' => $customer['id']])) ?>">مخاطب جدید</a>
        <a class="btn btn-light" href="<?= e(url('customers', ['action' => 'invite_contacts', 'id' => $customer['id']])) ?>">دعوتنامه مخاطب</a>
    </div>
</div>

<div class="card">
    <div class="detail-list">
        <div><span>کد مشتری</span><?= e($customer['customer_code']) ?></div>
        <div><span>نوع</span><?= e(fa_label($customer['customer_type'])) ?></div>
        <div><span>وضعیت</span><span class="badge <?= e(badge_class($customer['sales_status'])) ?>"><?= e(fa_label($customer['sales_status'])) ?></span></div>
        <div><span>سطح مشتری</span><?= (int) ($customer['is_vip'] ?? 0) === 1 ? '<span class="badge badge-warning">VIP</span>' : 'عادی' ?></div>
        <div><span>شهر</span><?= e($customer['city']) ?></div>
        <div><span>مالک</span><?= e($customer['owner_name']) ?></div>
        <div><span>پیگیری بعدی</span><?= e(fa_date($customer['next_followup_date'])) ?></div>
    </div>
    <?php if ($customer['notes']): ?><p class="muted"><?= nl2br(e($customer['notes'])) ?></p><?php endif; ?>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h3>مخاطب‌ها</h3>
        <?php foreach ($contacts as $contact): ?>
            <p>
                <strong><?= e($contact['contact_name']) ?></strong> <?= $contact['is_primary'] ? '<span class="badge badge-primary">اصلی</span>' : '' ?><br>
                <?php if (($contact['approval_status'] ?? 'approved') === 'pending'): ?><span class="badge badge-warning">در انتظار تأیید</span><?php endif; ?>
                <?php if (($contact['approval_status'] ?? 'approved') === 'rejected'): ?><span class="badge badge-danger">رد شده</span><?php endif; ?>
                <br><span class="muted"><?= e($contact['position']) ?> - <?= e($contact['mobile']) ?> - <?= e($contact['email']) ?></span>
                <span class="actions" style="margin-top:8px">
                    <a class="btn btn-small btn-light" href="<?= e(url('contacts', ['action' => 'edit', 'id' => $contact['id']])) ?>">ویرایش</a>
                    <?php if (is_admin()): ?>
                        <form method="post" action="<?= e(url('contacts', ['action' => 'delete', 'id' => $contact['id']])) ?>" data-confirm="این مخاطب و تیکت‌های مرتبط از نمایش مخفی شوند؟"><?= csrf_field() ?><button class="btn btn-small btn-danger">حذف</button></form>
                    <?php endif; ?>
                </span>
            </p>
        <?php endforeach; ?>
        <?php if (!$contacts): ?><div class="empty">مخاطبی ثبت نشده است.</div><?php endif; ?>
    </div>
    <div class="card">
        <h3>فرصت‌ها</h3>
        <?php foreach ($deals as $deal): ?>
            <p><a href="<?= e(url('deals', ['action' => 'show', 'id' => $deal['id']])) ?>"><strong><?= e($deal['deal_name']) ?></strong></a> <span class="badge <?= e(badge_class($deal['deal_stage'])) ?>"><?= e(fa_label($deal['deal_stage'])) ?></span><br><span class="muted"><?= e($deal['product']) ?> - <?= e(format_money($deal['estimated_amount'])) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$deals): ?><div class="empty">فرصتی ثبت نشده است.</div><?php endif; ?>
    </div>
    <div class="card">
        <h3>قراردادها</h3>
        <?php foreach ($contracts as $contract): ?>
            <p><a href="<?= e(url('contracts', ['action' => 'show', 'id' => $contract['id']])) ?>"><strong><?= e($contract['contract_title']) ?></strong></a> <span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(fa_label($contract['status'])) ?></span><br><span class="muted"><?= e($contract['contract_number']) ?> - پایان: <?= e(fa_date($contract['end_date'])) ?> - <?= e(format_money($contract['contract_amount'])) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$contracts): ?><div class="empty">قراردادی ثبت نشده است.</div><?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h3>فعالیت‌ها</h3>
    <?php foreach ($activities as $activity): ?>
        <?php
        $activityDetail = [
            'type' => fa_label($activity['activity_type']),
            'status' => fa_label($activity['status']),
            'date' => fa_date($activity['activity_date']),
            'summary' => $activity['summary'],
            'next_action' => $activity['next_action'],
            'next_followup' => fa_date($activity['next_followup_date']),
            'owner' => $activity['owner_name'],
            'deal' => $activity['deal_name'],
            'contract' => $activity['contract_title'],
            'notes' => $activity['notes'],
            'is_internal_task' => (int) ($activity['is_internal_task'] ?? 0) === 1,
            'attachment_url' => !empty($activity['attachment_path']) ? url('activities', ['action' => 'attachment', 'id' => $activity['id']]) : '',
            'attachment_name' => $activity['attachment_name'] ?? '',
        ];
        ?>
        <button
            class="activity-summary-button"
            type="button"
            data-activity-dialog-open
            data-activity-detail="<?= e(json_encode($activityDetail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
        >
            <span><strong><?= e(fa_label($activity['activity_type'])) ?></strong> <span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span><?php if (!empty($activity['is_internal_task'])): ?> <span class="badge badge-warning">داخلی</span><?php endif; ?></span>
            <span class="muted"><?= e(fa_date($activity['activity_date'])) ?> - <?= e($activity['summary']) ?></span>
        </button>
    <?php endforeach; ?>
    <?php if (!$activities): ?><div class="empty">فعالیتی ثبت نشده است.</div><?php endif; ?>
</div>

<dialog class="activity-dialog" data-activity-dialog>
    <div class="dialog-header">
        <div>
            <h3 data-activity-value="summary">جزئیات فعالیت</h3>
            <span class="badge badge-warning" data-activity-internal hidden>تسک داخلی</span>
        </div>
        <button class="dialog-close" type="button" data-dialog-close aria-label="بستن">×</button>
    </div>
    <div class="detail-list">
        <div><span>نوع فعالیت</span><strong data-activity-value="type"></strong></div>
        <div><span>وضعیت</span><strong data-activity-value="status"></strong></div>
        <div><span>تاریخ فعالیت</span><strong data-activity-value="date"></strong></div>
        <div><span>مسئول</span><strong data-activity-value="owner"></strong></div>
        <div><span>فرصت مرتبط</span><strong data-activity-value="deal"></strong></div>
        <div><span>قرارداد مرتبط</span><strong data-activity-value="contract"></strong></div>
        <div><span>اقدام بعدی</span><strong data-activity-value="next_action"></strong></div>
        <div><span>پیگیری بعدی</span><strong data-activity-value="next_followup"></strong></div>
    </div>
    <div class="dialog-notes">
        <span>یادداشت</span>
        <p data-activity-value="notes"></p>
    </div>
    <a class="btn btn-light" href="#" data-activity-attachment hidden>دریافت فایل پیوست</a>
    <div class="form-actions"><button class="btn btn-primary" type="button" data-dialog-close>بستن</button></div>
</dialog>
