<?php

declare(strict_types=1);

require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/PerformanceAnalytics.php';
require __DIR__ . '/../app/models/Ticket.php';

$failures = [];

function expect(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$roles = User::roles();
expect(array_keys($roles) === ['admin', 'sales', 'support', 'operations'], 'All four internal roles must be available.');
expect(User::validRole('admin') === 'admin', 'Admin role must remain valid.');
expect(User::validRole('sales') === 'sales', 'Sales role must remain valid.');
expect(User::validRole('support') === 'support', 'Support role must be valid.');
expect(User::validRole('operations') === 'operations', 'Operations role must be valid.');
expect(User::validRole('invalid-role') === 'sales', 'Invalid roles must safely fall back to sales.');

expect(PerformanceAnalytics::activeSecondsForGap(0) === 0, 'Zero-second gap must not add active time.');
expect(PerformanceAnalytics::activeSecondsForGap(420) === 420, 'Gap below threshold must add exact active seconds.');
expect(PerformanceAnalytics::activeSecondsForGap(900) === 900, 'Threshold gap must count as active.');
expect(PerformanceAnalytics::activeSecondsForGap(901) === 0, 'Gap above threshold must not count as active.');

$closedAtMethod = new ReflectionMethod(Ticket::class, 'closedAtSql');
$closedAtMethod->setAccessible(true);
expect($closedAtMethod->invoke(null, 'Open', 'Closed') === 'COALESCE(closed_at, CURRENT_TIMESTAMP)', 'Closing must set closed_at once.');
expect($closedAtMethod->invoke(null, 'Resolved', 'Open') === 'NULL', 'Reopening must clear closed_at.');
expect($closedAtMethod->invoke(null, 'Closed', 'Resolved') === 'closed_at', 'Closed-to-resolved must preserve closed_at.');

$root = dirname(__DIR__);
$activitySource = file_get_contents($root . '/app/models/Activity.php');
$ticketSource = file_get_contents($root . '/app/models/Ticket.php');
$indexSource = file_get_contents($root . '/public/index.php');
$migrationSource = file_get_contents($root . '/database/add_performance_analytics_instrumentation.sql');

expect(str_contains($activitySource, "'created_by_user_id' =") === false, 'Activity edit must not assign created_by_user_id.');
expect(str_contains($activitySource, "\$payload['created_by_user_id'] = \$createdByUserId"), 'Activity create must use the server-provided creator.');
expect(str_contains($indexSource, "Activity::create(\$_POST, current_user_id())"), 'CRM activity creation must pass the authenticated user.');

foreach (['customer', 'ticket', 'deal', 'contract'] as $entityType) {
    expect(
        substr_count($indexSource, "logRecordView(current_user_id(), '{$entityType}', \$id)") === 1,
        "{$entityType} detail must record exactly one raw view event."
    );
}
expect(!str_contains($indexSource, "logRecordView(current_user_id(), 'activity'"), 'Activity has no independent detail page and must not be tracked.');
expect(!str_contains($indexSource, "logRecordView(current_user_id(), 'contact'"), 'Contact has no independent detail page and must not be tracked.');

expect(substr_count($ticketSource, "'origin_type' => 'contact'") === 1, 'Portal-created ticket must have contact origin.');
expect(substr_count($ticketSource, "'origin_type' => 'user'") === 1, 'Internally-created ticket must have user origin.');
expect(substr_count($ticketSource, "'origin_type' => 'system'") === 1, 'Activation ticket must have system origin.');
expect(str_contains($ticketSource, 'if ($fromStatus === $toStatus)'), 'Unchanged ticket status must not create a history event.');
expect(str_contains($ticketSource, "self::changeStatus(\$id, 'Closed', 'contact'"), 'Contact close must record the contact actor.');

expect(!preg_match('/\b(?:DROP|TRUNCATE)\b/i', $migrationSource), 'Migration must not contain destructive DROP/TRUNCATE operations.');
expect(str_contains($migrationSource, "ENUM('admin','sales','support','operations')"), 'Migration must safely extend the role enum.');
expect(str_contains($migrationSource, 'No historical Activity creator, record view, active-time, or closed_at values are inferred.'), 'Migration must document non-backfilled metrics.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Performance analytics instrumentation tests passed." . PHP_EOL;
