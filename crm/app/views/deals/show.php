<div class="toolbar">
    <h2><?= e($deal['deal_name']) ?></h2>
    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create', 'customer_id' => $deal['customer_id'], 'deal_id' => $deal['id']])) ?>">ثبت فعالیت</a>
        <a class="btn btn-light" href="<?= e(url('deals', ['action' => 'edit', 'id' => $deal['id']])) ?>">ویرایش</a>
    </div>
</div>
<div class="card">
    <div class="detail-list">
        <div><span>مشتری</span><a href="<?= e(url('customers', ['action' => 'show', 'id' => $deal['customer_id']])) ?>"><?= e($deal['customer_name']) ?></a></div>
        <div><span>محصول</span><?= e($deal['product']) ?></div>
        <div><span>مرحله</span><span class="badge <?= e(badge_class($deal['deal_stage'])) ?>"><?= e(fa_label($deal['deal_stage'])) ?></span></div>
        <div><span>مبلغ تخمینی</span><?= e(format_money($deal['estimated_amount'])) ?></div>
        <div><span>احتمال</span><?= e((string) $deal['probability']) ?>٪</div>
        <div><span>ارزش وزنی</span><?= e(format_money($deal['weighted_amount'])) ?></div>
        <div><span>تعداد خودرو</span><?= e((string) $deal['vehicle_count']) ?></div>
        <div><span>تاریخ بسته شدن</span><?= e(fa_date($deal['expected_close_date'])) ?></div>
        <div><span>مالک</span><?= e($deal['owner_name']) ?></div>
    </div>
    <?php if ($deal['win_loss_reason']): ?><p><strong>دلیل برد/باخت:</strong> <?= e($deal['win_loss_reason']) ?></p><?php endif; ?>
    <?php if ($deal['notes']): ?><p class="muted"><?= nl2br(e($deal['notes'])) ?></p><?php endif; ?>
</div>
<div class="card" style="margin-top:16px">
    <h3>فعالیت‌های مرتبط</h3>
    <?php foreach ($activities as $activity): ?><p><strong><?= e(fa_label($activity['activity_type'])) ?></strong> <span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span><br><span class="muted"><?= e(fa_date($activity['activity_date'])) ?> - <?= e($activity['summary']) ?></span></p><?php endforeach; ?>
    <?php if (!$activities): ?><div class="empty">فعالیتی برای این فرصت ثبت نشده است.</div><?php endif; ?>
</div>
