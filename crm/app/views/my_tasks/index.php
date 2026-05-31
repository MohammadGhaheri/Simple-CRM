<div class="toolbar">
    <h2>برنامه کاری من</h2>
    <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create'])) ?>">ثبت فعالیت جدید</a>
</div>

<div class="grid grid-4">
    <div class="card stat"><span>عقب‌افتاده</span><strong><?= e((string) $counts['overdue']) ?></strong></div>
    <div class="card stat"><span>امروز</span><strong><?= e((string) $counts['today']) ?></strong></div>
    <div class="card stat"><span>۷ روز آینده</span><strong><?= e((string) $counts['upcoming']) ?></strong></div>
    <div class="card stat"><span>فعالیت‌های باز</span><strong><?= e((string) $counts['open']) ?></strong></div>
</div>

<?php
function render_agenda_items(array $activities, string $type): void
{
    foreach ($activities as $activity): ?>
        <div class="task-item <?= e($type) ?>">
            <div>
                <div class="task-title">
                    <strong><?= e($activity['summary']) ?></strong>
                    <span class="badge <?= e(badge_class($activity['status'])) ?>"><?= e(fa_label($activity['status'])) ?></span>
                    <span class="badge badge-muted"><?= e(fa_label($activity['activity_type'])) ?></span>
                </div>
                <div class="task-meta">
                    <span>مشتری: <a href="<?= e(url('customers', ['action' => 'show', 'id' => $activity['customer_id']])) ?>"><?= e($activity['customer_name']) ?></a></span>
                    <?php if (!empty($activity['deal_id'])): ?>
                        <span> | فرصت: <a href="<?= e(url('deals', ['action' => 'show', 'id' => $activity['deal_id']])) ?>"><?= e($activity['deal_name']) ?></a></span>
                    <?php endif; ?>
                    <br>
                    <span>موعد پیگیری: <?= e(fa_date($activity['next_followup_date'])) ?></span>
                    <?php if (!empty($activity['next_action'])): ?>
                        <br><span>اقدام بعدی: <?= e($activity['next_action']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="task-actions">
                <a class="btn btn-small btn-light" href="<?= e(url('activities', ['action' => 'edit', 'id' => $activity['id']])) ?>">ویرایش</a>
                <a class="btn btn-small btn-primary" href="<?= e(url('activities', ['action' => 'create', 'complete_id' => $activity['id']])) ?>">ثبت نتیجه</a>
            </div>
        </div>
    <?php endforeach;
}
?>

<section class="agenda-section">
    <div class="agenda-header">
        <h2>عقب‌افتاده</h2>
        <span class="badge badge-danger"><?= e((string) count($overdueActivities)) ?></span>
    </div>
    <div class="task-list">
        <?php render_agenda_items($overdueActivities, 'overdue'); ?>
        <?php if (!$overdueActivities): ?><div class="card empty">پیگیری عقب‌افتاده‌ای برای شما وجود ندارد.</div><?php endif; ?>
    </div>
</section>

<section class="agenda-section">
    <div class="agenda-header">
        <h2>امروز</h2>
        <span class="badge badge-success"><?= e((string) count($todayActivities)) ?></span>
    </div>
    <div class="task-list">
        <?php render_agenda_items($todayActivities, 'today'); ?>
        <?php if (!$todayActivities): ?><div class="card empty">برای امروز کاری ثبت نشده است.</div><?php endif; ?>
    </div>
</section>

<section class="agenda-section">
    <div class="agenda-header">
        <h2>۷ روز آینده</h2>
        <span class="badge badge-warning"><?= e((string) count($upcomingActivities)) ?></span>
    </div>
    <div class="task-list">
        <?php render_agenda_items($upcomingActivities, 'upcoming'); ?>
        <?php if (!$upcomingActivities): ?><div class="card empty">پیگیری برنامه‌ریزی‌شده‌ای برای ۷ روز آینده وجود ندارد.</div><?php endif; ?>
    </div>
</section>
