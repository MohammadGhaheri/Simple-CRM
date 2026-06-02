<div class="toolbar">
    <h2><?= e($contract['contract_title']) ?></h2>
    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create', 'customer_id' => $contract['customer_id'], 'deal_id' => $contract['deal_id'], 'contract_id' => $contract['id']])) ?>">ثبت فعالیت</a>
        <a class="btn btn-light" href="<?= e(url('contracts', ['action' => 'edit', 'id' => $contract['id']])) ?>">ویرایش</a>
    </div>
</div>
<div class="card">
    <div class="detail-list">
        <div><span>شماره قرارداد</span><?= e($contract['contract_number']) ?></div>
        <div><span>مشتری</span><a href="<?= e(url('customers', ['action' => 'show', 'id' => $contract['customer_id']])) ?>"><?= e($contract['customer_name']) ?></a></div>
        <div><span>فرصت</span><?= !empty($contract['deal_id']) ? '<a href="' . e(url('deals', ['action' => 'show', 'id' => $contract['deal_id']])) . '">' . e($contract['deal_name']) . '</a>' : 'بدون فرصت' ?></div>
        <div><span>محصول / خدمت</span><?= e($contract['product']) ?></div>
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

<div class="card" style="margin-top:16px">
    <h3>فعالیت‌های مرتبط با قرارداد</h3>
    <?php foreach ($activities as $activity): ?>
        <p><strong><?= e(fa_label($activity['activity_type'])) ?></strong> <span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span><br><span class="muted"><?= e(fa_date($activity['next_followup_date'] ?: $activity['activity_date'])) ?> - <?= e($activity['summary']) ?></span></p>
    <?php endforeach; ?>
    <?php if (!$activities): ?><div class="empty">فعالیتی برای این قرارداد ثبت نشده است.</div><?php endif; ?>
</div>
