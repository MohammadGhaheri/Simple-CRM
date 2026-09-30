<?php
$filterParams = Activity::normalizeCalendarFilters($filters);
$currentUserId = current_user_id();
$isMine = (int) ($filterParams['owner_user_id'] ?? 0) === $currentUserId;
$mineParams = $filterParams;
if ($isMine) {
    unset($mineParams['owner_user_id']);
} else {
    $mineParams['owner_user_id'] = $currentUserId;
}
$baseParams = array_merge(['view' => 'calendar', 'month' => $calendar['key']], $filterParams);
$navigationParams = static fn(string $month): array => array_merge(['view' => 'calendar', 'month' => $month], $filterParams);
$weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
$totalCells = (int) (ceil(($calendar['first_weekday'] + $calendar['days']) / 7) * 7);
?>

<div class="toolbar activity-calendar-header">
    <div>
        <h2>تقویم فعالیت‌ها</h2>
        <span class="muted"><?= e((string) count($occurrences)) ?> مورد در تقویم <?= e($calendar['label']) ?></span>
    </div>
    <div class="actions">
        <div class="view-switch" aria-label="نوع نمایش">
            <a class="btn btn-light" href="<?= e(url('activities', $filterParams)) ?>">لیست</a>
            <a class="btn btn-primary" href="<?= e(url('activities', $baseParams)) ?>">تقویم</a>
        </div>
        <a class="btn <?= $isMine ? 'btn-primary' : 'btn-light' ?>" href="<?= e(url('activities', array_merge(['view' => 'calendar', 'month' => $calendar['key']], $mineParams))) ?>"><?= $isMine ? 'نمایش همه' : 'فعالیت‌های من' ?></a>
        <a class="btn btn-primary" href="<?= e(url('activities', ['action' => 'create'])) ?>">فعالیت جدید</a>
    </div>
</div>

<form class="filters activity-calendar-filters" method="get">
    <input type="hidden" name="page" value="activities">
    <input type="hidden" name="view" value="calendar">
    <input type="hidden" name="month" value="<?= e($calendar['key']) ?>">
    <select name="owner_user_id"><option value="">همه مالک‌ها</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($filterParams['owner_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select>
    <select name="activity_type"><option value="">همه نوع‌ها</option><?php foreach (activity_type_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filterParams['activity_type'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (activity_status_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($filterParams['status'] ?? '', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select>
    <select name="is_internal_task"><option value="">همه فعالیت‌ها</option><option value="1" <?= selected($filterParams['is_internal_task'] ?? '', '1') ?>>فقط داخلی</option><option value="0" <?= selected($filterParams['is_internal_task'] ?? '', '0') ?>>فقط مشتری</option></select>
    <button class="btn btn-light">اعمال فیلتر</button>
</form>

<div class="calendar-toolbar">
    <a class="btn btn-light" href="<?= e(url('activities', $navigationParams($calendar['previous']))) ?>">ماه قبل</a>
    <strong><?= e($calendar['label']) ?></strong>
    <a class="btn btn-light" href="<?= e(url('activities', $navigationParams($calendar['next']))) ?>">ماه بعد</a>
    <a class="btn btn-light calendar-today" href="<?= e(url('activities', $navigationParams($calendar['current']))) ?>">امروز</a>
</div>

<div class="activity-calendar-scroll">
    <div class="activity-calendar" role="grid" aria-label="<?= e('تقویم ' . $calendar['label']) ?>">
        <?php foreach ($weekdays as $weekday): ?><div class="calendar-weekday" role="columnheader"><?= e($weekday) ?></div><?php endforeach; ?>
        <?php for ($cell = 0; $cell < $totalCells; $cell++): ?>
            <?php $day = $cell - $calendar['first_weekday'] + 1; ?>
            <?php if ($day < 1 || $day > $calendar['days']): ?>
                <div class="calendar-day is-outside" role="gridcell"></div>
            <?php else: ?>
                <?php
                [$gy, $gm, $gd] = jalali_to_gregorian((int) $calendar['year'], (int) $calendar['month'], $day);
                $databaseDate = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
                $jalaliDate = sprintf('%04d/%02d/%02d', $calendar['year'], $calendar['month'], $day);
                $dayOccurrences = $occurrencesByDate[$databaseDate] ?? [];
                $visibleOccurrences = array_slice($dayOccurrences, 0, 3);
                ?>
                <div class="calendar-day <?= (int) ($calendar['today_day'] ?? 0) === $day ? 'is-today' : '' ?>" role="gridcell">
                    <div class="calendar-day-head">
                        <span class="calendar-day-number"><?= e((string) $day) ?></span>
                        <a class="calendar-add" href="<?= e(url('activities', ['action' => 'create', 'activity_date' => $jalaliDate])) ?>" title="ثبت فعالیت در این روز" aria-label="<?= e('ثبت فعالیت در ' . $jalaliDate) ?>">+</a>
                    </div>
                    <div class="calendar-events">
                        <?php foreach ($visibleOccurrences as $occurrence): ?>
                            <?php $isCompleted = in_array($occurrence['status'], ['Done', 'Cancelled'], true); ?>
                            <a class="calendar-event <?= $isCompleted ? 'is-completed' : '' ?> <?= $occurrence['occurrence_type'] === 'followup' ? 'is-followup' : '' ?>" href="<?= e(url('activities', ['action' => 'edit', 'id' => $occurrence['id']])) ?>">
                                <span class="calendar-event-meta">
                                    <b><?= e($occurrence['occurrence_type'] === 'followup' ? 'پیگیری' : fa_label($occurrence['activity_type'])) ?></b>
                                    <?php if (!empty($occurrence['is_internal_task'])): ?><em>داخلی</em><?php endif; ?>
                                </span>
                                <strong><?= e(text_excerpt($occurrence['occurrence_summary'], 42)) ?></strong>
                                <span><?= e(text_excerpt($occurrence['customer_name'], 32)) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($dayOccurrences) > 3): ?>
                            <a class="calendar-more" href="<?= e(url('activities', array_merge($filterParams, ['date_from' => $jalaliDate, 'date_to' => $jalaliDate]))) ?>">+ <?= e((string) (count($dayOccurrences) - 3)) ?> مورد دیگر</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
