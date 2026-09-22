<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/models/PerformanceReport.php';

function report_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

report_expect(PerformanceReport::median([]) === null, 'Empty median must be null.');
report_expect(PerformanceReport::median([10, 30, 20]) === 20.0, 'Odd median must use the middle value.');
report_expect(PerformanceReport::median([10, 20, 30, 40]) === 25.0, 'Even median must average two middle values.');
report_expect(PerformanceReport::median([null, 10, 20]) === 15.0, 'Median must ignore null values.');

report_expect(PerformanceReport::formatDuration(null) === '—', 'Unknown duration must use an em dash.');
report_expect(PerformanceReport::formatDuration(45) === '45 ثانیه', 'Seconds formatting failed.');
report_expect(PerformanceReport::formatDuration(750) === '12 دقیقه', 'Minute formatting failed.');
report_expect(PerformanceReport::formatDuration(5100) === '1 ساعت و 25 دقیقه', 'Hour formatting failed.');
report_expect(PerformanceReport::formatDuration(183600) === '2 روز و 3 ساعت', 'Day formatting failed.');

$filters = PerformanceReport::normalizeFilters([
    'date_from' => '1405/06/31',
    'date_to' => '1405/06/01',
    'user_id' => '-4',
    'role' => 'owner',
], new DateTimeImmutable('2026-09-22 12:00:00'));
report_expect($filters['start'] < $filters['end'], 'Date range must normalize in ascending inclusive order.');
report_expect(str_ends_with($filters['start'], '00:00:00'), 'Range start must begin at midnight.');
report_expect($filters['user_id'] === 0, 'Invalid user id must be rejected.');
report_expect($filters['role'] === '', 'Unknown role must be rejected.');

$validRole = PerformanceReport::normalizeFilters(['role' => 'support'], new DateTimeImmutable('2026-09-22'));
report_expect($validRole['role'] === 'support', 'Whitelisted role must be preserved.');

report_expect(PerformanceReport::uniqueRecordCount([
    ['entity_type' => 'customer', 'entity_id' => 1],
    ['entity_type' => 'customer', 'entity_id' => 1],
    ['entity_type' => 'ticket', 'entity_id' => 1],
]) === 2, 'Unique records must combine entity type and entity id.');

$source = file_get_contents(__DIR__ . '/../app/models/PerformanceReport.php');
report_expect(str_contains($source, 'created_by_user_id user_id'), 'Activities must be attributed to creator, not owner.');
report_expect(str_contains($source, "tm.sender_type='user' ORDER BY tm.created_at, tm.id LIMIT 1"), 'First response must use the first user message.');
report_expect(str_contains($source, "t.origin_type='contact'"), 'Ticket KPIs must only use customer-origin tickets.');
report_expect(str_contains($source, "t.status IN ('Closed','Resolved')"), 'Final closer policy must require a currently closed ticket.');
report_expect(str_contains($source, 'NOT EXISTS ('), 'Final closer policy must prevent reopen double counting.');

echo "Performance report tests passed." . PHP_EOL;
