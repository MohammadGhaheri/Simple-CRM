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
        <p><strong><?= e(fa_label($activity['activity_type'])) ?></strong> <span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span><br><span class="muted"><?= e(fa_date($activity['activity_date'])) ?> - <?= e($activity['summary']) ?></span></p>
    <?php endforeach; ?>
    <?php if (!$activities): ?><div class="empty">فعالیتی ثبت نشده است.</div><?php endif; ?>
</div>
