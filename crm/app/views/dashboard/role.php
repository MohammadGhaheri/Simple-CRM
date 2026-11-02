<?php
$metrics = $dashboard['metrics'] ?? [];
$tasks = $dashboard['tasks'] ?? [];
$role = $dashboard['role'] ?? '';
?>
<div class="toolbar">
    <h2><?= e($dashboard['title']) ?></h2>
    <div class="actions">
        <?php if ($role === 'sales'): ?>
            <a class="btn btn-primary" href="<?= e(url('deals')) ?>">فرصت‌های فروش</a>
        <?php elseif ($role === 'support'): ?>
            <a class="btn btn-primary" href="<?= e(url('tickets')) ?>">تیکت‌ها</a>
        <?php elseif ($role === 'finance'): ?>
            <a class="btn btn-primary" href="<?= e(url('finance')) ?>">امور مالی</a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(url('my_tasks')) ?>">برنامه من</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-4">
    <?php foreach ($metrics as $metric): ?>
        <div class="card stat">
            <span><?= e($metric['label']) ?></span>
            <strong><?= e(!empty($metric['money']) ? format_money($metric['value']) : (string) $metric['value']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($dashboard['note'])): ?>
    <div class="card" style="margin-top:16px">
        <p class="muted"><?= e($dashboard['note']) ?></p>
    </div>
<?php endif; ?>

<?php if ($role !== 'finance'): ?>
    <div class="card" style="margin-top:16px">
        <div class="toolbar">
            <h3>پیگیری‌های من</h3>
            <a class="btn btn-light" href="<?= e(url('my_tasks')) ?>">مشاهده برنامه کامل</a>
        </div>
        <?php foreach ($tasks as $task): ?>
            <p>
                <strong><?= e($task['customer_name']) ?></strong>
                <span class="badge badge-muted"><?= e(fa_label($task['activity_type'])) ?></span>
                <span class="muted"><?= e(fa_date($task['next_followup_date'] ?: $task['activity_date'])) ?></span>
                <br>
                <?= e($task['next_action'] ?: $task['summary']) ?>
            </p>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><div class="empty">در حال حاضر پیگیری سررسیدشده یا برنامه‌ریزی‌شده‌ای برای شما وجود ندارد.</div><?php endif; ?>
    </div>
<?php endif; ?>
