<div class="grid grid-4">
    <div class="card stat"><span>کل مشتریان</span><strong><?= e((string) $stats['customers']) ?></strong></div>
    <div class="card stat"><span>فرصت‌های باز</span><strong><?= e((string) $stats['open_deals']) ?></strong></div>
    <div class="card stat"><span>ارزش پایپ‌لاین</span><strong><?= e(format_money($stats['pipeline'])) ?></strong></div>
    <div class="card stat"><span>ارزش وزنی</span><strong><?= e(format_money($stats['weighted'])) ?></strong></div>
    <div class="card stat"><span>فروش برنده</span><strong><?= e(format_money($stats['won_value'])) ?></strong></div>
    <div class="card stat"><span>تعداد باخت</span><strong><?= e((string) $stats['lost_count']) ?></strong></div>
    <div class="card stat"><span>پیگیری‌های عقب‌افتاده</span><strong><?= e((string) $stats['overdue']) ?></strong></div>
    <div class="card stat"><span>تمدیدهای نیازمند پیگیری</span><strong><?= e((string) $stats['renewal_due']) ?></strong></div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h3>فرصت‌ها بر اساس مرحله</h3>
        <?php $max = max(array_column($dealsByStage ?: [['total' => 1]], 'total')); ?>
        <?php foreach ($dealsByStage as $row): ?>
            <div class="bar-row">
                <span><?= e(fa_label($row['deal_stage'])) ?></span>
                <div class="bar"><i style="width:<?= e((string) max(5, ($row['total'] / $max) * 100)) ?>%"></i></div>
                <strong><?= e((string) $row['total']) ?></strong>
            </div>
        <?php endforeach; ?>
        <?php if (!$dealsByStage): ?><div class="empty">هنوز فرصتی ثبت نشده است.</div><?php endif; ?>
    </div>
    <div class="card">
        <h3>مشتریان بر اساس نوع</h3>
        <?php $max = max(array_column($customersByType ?: [['total' => 1]], 'total')); ?>
        <?php foreach ($customersByType as $row): ?>
            <div class="bar-row">
                <span><?= e(fa_label($row['customer_type'])) ?></span>
                <div class="bar"><i style="width:<?= e((string) max(5, ($row['total'] / $max) * 100)) ?>%"></i></div>
                <strong><?= e((string) $row['total']) ?></strong>
            </div>
        <?php endforeach; ?>
        <?php if (!$customersByType): ?><div class="empty">هنوز مشتری ثبت نشده است.</div><?php endif; ?>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h3>فعالیت‌های اخیر</h3>
        <?php foreach ($recentActivities as $activity): ?>
            <p><strong><?= e(fa_label($activity['activity_type'])) ?></strong> برای <?= e($activity['customer_name']) ?><br><span class="muted"><?= e($activity['summary']) ?> - <?= e(fa_date($activity['activity_date'])) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$recentActivities): ?><div class="empty">فعالیتی ثبت نشده است.</div><?php endif; ?>
    </div>
    <div class="card">
        <h3>پیگیری‌های آینده</h3>
        <?php foreach ($upcomingActivities as $activity): ?>
            <p><strong><?= e(fa_date($activity['next_followup_date'])) ?></strong> - <?= e($activity['customer_name']) ?><br><span class="muted"><?= e($activity['next_action'] ?: $activity['summary']) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$upcomingActivities): ?><div class="empty">پیگیری آینده‌ای وجود ندارد.</div><?php endif; ?>
    </div>
    <div class="card">
        <h3>تمدید قراردادها</h3>
        <?php foreach ($renewalContracts as $contract): ?>
            <p>
                <strong><a href="<?= e(url('contracts', ['action' => 'show', 'id' => $contract['id']])) ?>"><?= e($contract['contract_title']) ?></a></strong>
                <span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(fa_label($contract['status'])) ?></span><br>
                <span class="muted"><?= e($contract['customer_name']) ?> - یادآوری: <?= e(fa_date($contract['renewal_reminder_date'])) ?> - پایان: <?= e(fa_date($contract['end_date'])) ?></span>
            </p>
        <?php endforeach; ?>
        <?php if (!$renewalContracts): ?><div class="empty">قراردادی در موعد تمدید نیست.</div><?php endif; ?>
    </div>
</div>
