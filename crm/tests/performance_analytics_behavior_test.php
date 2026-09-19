<?php

declare(strict_types=1);

final class TestStatement
{
    private array $result = [];
    private int $affected = 0;

    public function __construct(private TestDatabase $database, private string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $this->affected = 0;
        $this->result = [];
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));

        if (str_starts_with($sql, 'INSERT INTO user_sessions')) {
            $id = ++$this->database->lastId;
            $this->database->sessions[$id] = [
                'id' => $id,
                'user_id' => $params[0],
                'token' => $params[1],
                'ended' => false,
            ];
            $this->affected = 1;
        } elseif (str_starts_with($sql, 'UPDATE user_sessions SET active_seconds')) {
            $id = (int) $params[1];
            if (isset($this->database->sessions[$id]) && !$this->database->sessions[$id]['ended']) {
                $this->affected = 1;
            }
        } elseif (str_starts_with($sql, 'SELECT id FROM user_sessions')) {
            $id = (int) $params[0];
            if (isset($this->database->sessions[$id]) && !$this->database->sessions[$id]['ended']) {
                $this->result = [$id];
            }
        } elseif (str_starts_with($sql, 'UPDATE user_sessions SET ended_at')) {
            $id = (int) $params[0];
            if (isset($this->database->sessions[$id])) {
                $this->database->sessions[$id]['ended'] = true;
                $this->affected = 1;
            }
        } elseif (str_starts_with($sql, 'INSERT INTO record_view_events')) {
            $this->database->views[] = ['user_id' => $params[0], 'entity_type' => $params[1], 'entity_id' => $params[2]];
            $this->affected = 1;
        } elseif (str_starts_with($sql, 'INSERT INTO activities')) {
            $id = ++$this->database->lastId;
            $this->database->activities[$id] = $params;
            $this->affected = 1;
        } elseif (str_starts_with($sql, 'UPDATE activities SET')) {
            $id = (int) $params['id'];
            $creator = $this->database->activities[$id]['created_by_user_id'] ?? null;
            $this->database->activities[$id] = array_merge($this->database->activities[$id], $params);
            $this->database->activities[$id]['created_by_user_id'] = $creator;
            $this->affected = 1;
        } elseif (str_starts_with($sql, 'UPDATE customers SET')) {
            $this->affected = 1;
        } elseif (str_starts_with($sql, 'SELECT status FROM tickets')) {
            $id = (int) $params[0];
            if (isset($this->database->tickets[$id])) {
                $this->result = [$this->database->tickets[$id]['status']];
            }
        } elseif (str_starts_with($sql, 'SELECT id FROM tickets')) {
            $id = (int) $params[0];
            $contactId = (int) $params[1];
            if (($this->database->tickets[$id]['contact_id'] ?? 0) === $contactId) {
                $this->result = [$id];
            }
        } elseif (str_starts_with($sql, 'UPDATE tickets SET status=:status')) {
            $this->applyTicketStatus((int) $params['id'], (string) $params['status'], $sql);
        } elseif (str_starts_with($sql, 'UPDATE tickets SET status = ?')) {
            $this->applyTicketStatus((int) $params[1], (string) $params[0], $sql);
        } elseif (str_starts_with($sql, 'INSERT INTO ticket_status_events')) {
            $this->database->statusEvents[] = [
                'ticket_id' => $params[0],
                'from_status' => $params[1],
                'to_status' => $params[2],
                'actor_type' => $params[3],
                'user_id' => $params[4],
                'contact_id' => $params[5],
            ];
            $this->affected = 1;
        } else {
            throw new RuntimeException('Unhandled test SQL: ' . $sql);
        }

        return true;
    }

    public function fetchColumn(): mixed
    {
        return $this->result[0] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }

    private function applyTicketStatus(int $id, string $newStatus, string $sql): void
    {
        $oldStatus = $this->database->tickets[$id]['status'];
        $this->database->tickets[$id]['status'] = $newStatus;
        if (str_contains($sql, 'closed_at=NULL')) {
            $this->database->tickets[$id]['closed_at'] = null;
        } elseif (str_contains($sql, 'COALESCE(closed_at, CURRENT_TIMESTAMP)')) {
            $this->database->tickets[$id]['closed_at'] ??= 'test-time-' . ++$this->database->clock;
        } elseif (!in_array($oldStatus, ['Closed', 'Resolved'], true) && in_array($newStatus, ['Closed', 'Resolved'], true)) {
            $this->database->tickets[$id]['closed_at'] ??= 'test-time-' . ++$this->database->clock;
        }
        $this->affected = 1;
    }
}

final class TestDatabase
{
    public int $lastId = 0;
    public int $clock = 0;
    public array $sessions = [];
    public array $views = [];
    public array $activities = [];
    public array $tickets = [];
    public array $statusEvents = [];
    private bool $transaction = false;

    public function prepare(string $sql): TestStatement
    {
        return new TestStatement($this, $sql);
    }

    public function lastInsertId(): string
    {
        return (string) $this->lastId;
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

$testDatabase = new TestDatabase();
function db(): TestDatabase
{
    global $testDatabase;
    return $testDatabase;
}

require __DIR__ . '/../app/models/PerformanceAnalytics.php';
require __DIR__ . '/../app/models/Activity.php';
require __DIR__ . '/../app/models/Ticket.php';

function test_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function option_values(string $key): array
{
    return match ($key) {
        'options_ticket_statuses' => ['Open', 'In Progress', 'Waiting Customer', 'Resolved', 'Closed'],
        'options_ticket_priorities' => ['Normal'],
        'options_ticket_categories' => ['Support'],
        default => [],
    };
}

function db_date(?string $value): ?string
{
    return $value ?: null;
}

ini_set('session.save_path', sys_get_temp_dir());
session_id('performance-test-a');
session_start();
$firstSession = PerformanceAnalytics::startUserSession(10);
PerformanceAnalytics::touchUserSession(10);
PerformanceAnalytics::endUserSession(10);
test_expect($testDatabase->sessions[$firstSession]['ended'] === true, 'Explicit logout must end the analytics session.');

session_write_close();
session_id('performance-test-b');
session_start();
$secondSession = PerformanceAnalytics::startUserSession(10);
test_expect($secondSession !== $firstSession, 'Concurrent/device logins must create independent sessions.');
test_expect($testDatabase->sessions[$firstSession]['token'] !== $testDatabase->sessions[$secondSession]['token'], 'Session hashes must be independent.');

PerformanceAnalytics::logRecordView(10, 'customer', 5);
PerformanceAnalytics::logRecordView(10, 'customer', 5);
PerformanceAnalytics::logRecordView(10, 'invalid', 5);
test_expect(count($testDatabase->views) === 2, 'Each valid detail open must append a raw record view event.');

$activityId = Activity::create([
    'customer_id' => 4,
    'owner_user_id' => 20,
    'activity_date' => '2026-09-19',
    'summary' => 'Test activity',
], 10);
test_expect($testDatabase->activities[$activityId]['owner_user_id'] === 20, 'Activity owner must remain independently assignable.');
test_expect($testDatabase->activities[$activityId]['created_by_user_id'] === 10, 'Activity creator must be the authenticated creator.');
Activity::update($activityId, [
    'customer_id' => 4,
    'owner_user_id' => 30,
    'activity_date' => '2026-09-20',
    'summary' => 'Edited activity',
]);
test_expect($testDatabase->activities[$activityId]['owner_user_id'] === 30, 'Activity owner must be editable.');
test_expect($testDatabase->activities[$activityId]['created_by_user_id'] === 10, 'Activity edit must preserve creator.');

$testDatabase->tickets[1] = ['status' => 'Open', 'closed_at' => null, 'contact_id' => 50];
$meta = ['status' => 'In Progress', 'priority' => 'Normal', 'category' => 'Support'];
Ticket::updateMeta(1, $meta, 10);
test_expect(count($testDatabase->statusEvents) === 1, 'A real status transition must create one event.');
test_expect($testDatabase->statusEvents[0]['actor_type'] === 'user' && $testDatabase->statusEvents[0]['user_id'] === 10, 'Internal status actor must be the authenticated user.');
Ticket::updateMeta(1, $meta, 10);
test_expect(count($testDatabase->statusEvents) === 1, 'Unchanged status must not create another event.');

Ticket::close(1, 'user', 10);
test_expect($testDatabase->tickets[1]['closed_at'] !== null, 'Closing must set closed_at.');
$firstClosedAt = $testDatabase->tickets[1]['closed_at'];
Ticket::updateMeta(1, ['status' => 'Open', 'priority' => 'Normal', 'category' => 'Support'], 10);
test_expect($testDatabase->tickets[1]['closed_at'] === null, 'Reopening must clear closed_at.');
Ticket::closeForContact(1, 50);
test_expect($testDatabase->tickets[1]['closed_at'] !== null, 'Closing again must set a new closed_at.');
$secondClosedAt = $testDatabase->tickets[1]['closed_at'];
$lastEvent = end($testDatabase->statusEvents);
test_expect($lastEvent['actor_type'] === 'contact' && $lastEvent['contact_id'] === 50, 'Contact close must record the contact actor.');
test_expect($secondClosedAt !== $firstClosedAt, 'Closing after a reopen must set a new closed_at.');

session_write_close();
echo "Performance analytics behavior tests passed." . PHP_EOL;
