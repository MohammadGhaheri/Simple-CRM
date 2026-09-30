<?php

declare(strict_types=1);

final class Setting
{
    public static function get(string $key): string
    {
        return match ($key) {
            'options_activity_types' => "Meeting|جلسه\nCall|تماس\nFollow-up|پیگیری",
            'options_activity_statuses' => "Open|باز\nDone|انجام شده\nCancelled|لغو شده",
            default => '',
        };
    }
}

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/models/Activity.php';

function calendar_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$month = jalali_month_context('1405-07', '2026-09-30');
calendar_expect($month['key'] === '1405-07', 'Requested Jalali month was not preserved.');
calendar_expect($month['start'] === '2026-09-23' && $month['end'] === '2026-10-23', 'Jalali month Gregorian range is incorrect.');
calendar_expect($month['previous'] === '1405-06' && $month['next'] === '1405-08', 'Month navigation is incorrect.');
calendar_expect($month['current'] === '1405-07' && $month['today_day'] === 8, 'Today navigation or highlight is incorrect.');
calendar_expect(jalali_month_length(1405, 1) === 31, 'A 31-day Jalali month is incorrect.');
calendar_expect(jalali_month_length(1405, 7) === 30, 'A 30-day Jalali month is incorrect.');
calendar_expect(jalali_month_length(1403, 12) === 30, 'Leap Esfand is incorrect.');
calendar_expect(jalali_month_length(1404, 12) === 29, 'Non-leap Esfand is incorrect.');

$fallback = jalali_month_context('invalid', '2026-09-30');
calendar_expect($fallback['key'] === '1405-07', 'Invalid month must safely fall back to the current month.');
calendar_expect(normalize_jalali_date('۱۴۰۵/۰۷/۱۵') === '1405/07/15', 'Persian digit date normalization failed.');
calendar_expect(normalize_jalali_date('1405/07/31') === null, 'Invalid day must be rejected.');

$rows = [
    [
        'id' => 1,
        'activity_date' => '2026-09-23',
        'next_followup_date' => '2026-09-23',
        'summary' => 'جلسه آغاز ماه',
        'next_action' => 'پیگیری تکراری',
        'status' => 'Open',
        'activity_type' => 'Meeting',
        'is_internal_task' => 0,
        'customer_name' => 'مشتری اول',
    ],
    [
        'id' => 2,
        'activity_date' => '2026-10-22',
        'next_followup_date' => '2026-10-23',
        'summary' => 'آخرین روز ماه',
        'next_action' => 'خارج از ماه',
        'status' => 'Done',
        'activity_type' => 'Call',
        'is_internal_task' => 1,
        'customer_name' => 'مشتری دوم',
    ],
    [
        'id' => 3,
        'activity_date' => '2026-09-22',
        'next_followup_date' => '2026-10-01',
        'summary' => 'فعالیت خارج ماه',
        'next_action' => 'پیگیری داخل ماه',
        'status' => 'Open',
        'activity_type' => 'Follow-up',
        'is_internal_task' => 0,
        'customer_name' => 'مشتری سوم',
    ],
];
$occurrences = Activity::buildCalendarOccurrences($rows, $month['start'], $month['end']);
calendar_expect(count($occurrences) === 3, 'Occurrences must include in-range activity/follow-up without same-day duplicates.');
calendar_expect($occurrences[0]['id'] === 1 && $occurrences[0]['occurrence_type'] === 'activity', 'First-day activity is missing.');
calendar_expect($occurrences[1]['id'] === 3 && $occurrences[1]['occurrence_type'] === 'followup', 'Different-date follow-up is missing.');
calendar_expect($occurrences[1]['occurrence_summary'] === 'پیگیری داخل ماه', 'Follow-up must use the next action summary.');
calendar_expect($occurrences[2]['id'] === 2 && $occurrences[2]['occurrence_date'] === '2026-10-22', 'Last-day activity is missing.');
calendar_expect($occurrences[2]['status'] === 'Done' && (int) $occurrences[2]['is_internal_task'] === 1, 'Completed/internal activity metadata must remain visible.');

$filters = Activity::normalizeCalendarFilters([
    'owner_user_id' => '7',
    'activity_type' => 'Meeting',
    'status' => 'Open',
    'is_internal_task' => '1',
    'unknown' => 'drop-me',
]);
calendar_expect($filters === [
    'activity_type' => 'Meeting',
    'status' => 'Open',
    'owner_user_id' => 7,
    'is_internal_task' => 1,
], 'Calendar filters were not normalized correctly.');
calendar_expect(Activity::normalizeCalendarFilters([
    'owner_user_id' => '-1',
    'activity_type' => 'Injected',
    'status' => 'Injected',
    'is_internal_task' => '2',
]) === [], 'Invalid calendar filters must be ignored.');

$invalidRangeRejected = false;
try {
    Activity::calendarForRange('2026-10-23', '2026-09-23');
} catch (InvalidArgumentException) {
    $invalidRangeRejected = true;
}
calendar_expect($invalidRangeRejected, 'Invalid or reversed calendar ranges must be rejected before querying.');

$modelSource = file_get_contents(__DIR__ . '/../app/models/Activity.php');
calendar_expect(str_contains($modelSource, 'a.activity_date >= ? AND a.activity_date < ?'), 'Activity range must use bounded prepared comparisons.');
calendar_expect(str_contains($modelSource, 'a.next_followup_date >= ? AND a.next_followup_date < ?'), 'Follow-up range must use bounded prepared comparisons.');
calendar_expect(!str_contains($modelSource, 'DATE(a.activity_date)'), 'Calendar query must not wrap indexed dates in DATE().');
$controllerSource = file_get_contents(__DIR__ . '/../public/index.php');
calendar_expect(str_contains($controllerSource, 'normalize_jalali_date((string) ($_GET[\'activity_date\'] ?? \'\'))'), 'Create-from-day must validate the Jalali date.');

$calendar = $month;
$filters = ['owner_user_id' => 7];
$users = [['id' => 7, 'name' => 'مالک تست']];
$occurrencesByDate = [];
foreach ($occurrences as $occurrence) {
    $occurrencesByDate[$occurrence['occurrence_date']][] = $occurrence;
}
$_SESSION['user']['id'] = 7;
ob_start();
require __DIR__ . '/../app/views/activities/calendar.php';
$calendarHtml = (string) ob_get_clean();
calendar_expect(str_contains($calendarHtml, 'activity_date=1405%2F07%2F01'), 'Create-from-day link must prefill the selected Jalali date.');
calendar_expect(str_contains($calendarHtml, 'calendar-event is-completed'), 'Completed activities must remain visible with subdued styling.');
calendar_expect(str_contains($calendarHtml, '<em>داخلی</em>'), 'Internal task badge must be rendered.');
calendar_expect(str_contains($calendarHtml, 'پیگیری داخل ماه'), 'Follow-up occurrence must be rendered in the calendar.');

echo "Activity calendar tests passed." . PHP_EOL;
