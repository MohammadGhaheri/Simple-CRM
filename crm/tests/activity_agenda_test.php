<?php

declare(strict_types=1);

final class AgendaTestStatement
{
    private array $params = [];

    public function __construct(private AgendaTestDatabase $database, public string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $this->params = $params;
        return true;
    }

    public function fetchAll(): array
    {
        $rows = $this->database->eligibleRows((int) ($this->params[0] ?? 0));
        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime('+7 days'));
        if (str_contains($this->sql, 'a.next_followup_date IS NULL')) {
            $rows = array_filter($rows, static fn(array $row): bool => $row['next_followup_date'] === null && $row['status'] !== 'Done');
            usort($rows, static fn(array $a, array $b): int => [$b['activity_date'], $b['id']] <=> [$a['activity_date'], $a['id']]);
            return array_values($rows);
        }
        $rows = array_filter($rows, function (array $row) use ($today, $limit): bool {
            $date = $row['next_followup_date'];
            if ($date === null || $row['status'] === 'Done') {
                return false;
            }
            return match (true) {
                str_contains($this->sql, 'a.next_followup_date < CURDATE()') => $date < $today,
                str_contains($this->sql, 'a.next_followup_date = CURDATE()') => $date === $today,
                str_contains($this->sql, 'a.next_followup_date > DATE_ADD') => $date > $limit,
                default => $date > $today && $date <= $limit,
            };
        });
        usort($rows, static fn(array $a, array $b): int => [$a['next_followup_date'], $a['id']] <=> [$b['next_followup_date'], $b['id']]);
        return array_values($rows);
    }

    public function fetch(): array
    {
        $rows = $this->database->eligibleRows((int) ($this->params[0] ?? 0));
        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime('+7 days'));
        $counts = ['overdue_count' => 0, 'today_count' => 0, 'upcoming_count' => 0, 'later_count' => 0, 'nodate_count' => 0, 'open_count' => 0];
        foreach ($rows as $row) {
            if ($row['status'] === 'Open') {
                $counts['open_count']++;
            }
            if ($row['status'] === 'Done') {
                continue;
            }
            $date = $row['next_followup_date'];
            if ($date === null) {
                $counts['nodate_count']++;
            } elseif ($date < $today) {
                $counts['overdue_count']++;
            } elseif ($date === $today) {
                $counts['today_count']++;
            } elseif ($date <= $limit) {
                $counts['upcoming_count']++;
            } else {
                $counts['later_count']++;
            }
        }
        return $counts;
    }
}

final class AgendaTestDatabase
{
    public function __construct(public array $rows)
    {
    }

    public function prepare(string $sql): AgendaTestStatement
    {
        return new AgendaTestStatement($this, $sql);
    }

    public function eligibleRows(int $ownerId): array
    {
        return array_values(array_filter($this->rows, static fn(array $row): bool =>
            $row['owner_user_id'] === $ownerId && $row['deleted_at'] === null && $row['customer_deleted_at'] === null
        ));
    }
}

$today = date('Y-m-d');
$agendaTestDatabase = new AgendaTestDatabase([
    ['id' => 1, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('-1 day')), 'activity_date' => date('Y-m-d', strtotime('-3 days')), 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 2, 'owner_user_id' => 1, 'next_followup_date' => $today, 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 3, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('+1 day')), 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 4, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('+7 days')), 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 5, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('+8 days')), 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 6, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('+3 months')), 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 7, 'owner_user_id' => 1, 'next_followup_date' => null, 'activity_date' => date('Y-m-d', strtotime('-2 days')), 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 8, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('+1 day')), 'activity_date' => $today, 'status' => 'Done', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 9, 'owner_user_id' => 1, 'next_followup_date' => date('Y-m-d', strtotime('+1 day')), 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => '2026-10-01 10:00:00', 'customer_deleted_at' => null],
    ['id' => 10, 'owner_user_id' => 2, 'next_followup_date' => date('Y-m-d', strtotime('+1 day')), 'activity_date' => $today, 'status' => 'Open', 'deleted_at' => null, 'customer_deleted_at' => null],
    ['id' => 11, 'owner_user_id' => 1, 'next_followup_date' => null, 'activity_date' => date('Y-m-d', strtotime('-1 day')), 'status' => 'Cancelled', 'deleted_at' => null, 'customer_deleted_at' => null],
]);

function db(): AgendaTestDatabase
{
    global $agendaTestDatabase;
    return $agendaTestDatabase;
}

require __DIR__ . '/../app/models/Activity.php';

function agenda_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

agenda_expect(array_column(Activity::agendaForOwner(1, 'overdue'), 'id') === [1], 'Yesterday must be overdue.');
agenda_expect(array_column(Activity::agendaForOwner(1, 'today'), 'id') === [2], 'Today must be isolated.');
agenda_expect(array_column(Activity::agendaForOwner(1, 'upcoming'), 'id') === [3, 4], 'Tomorrow and day seven must be upcoming.');
agenda_expect(array_column(Activity::agendaForOwner(1, 'later'), 'id') === [5, 6], 'Day eight and distant dates must be later.');
agenda_expect(array_column(Activity::agendaForOwner(1, 'nodate'), 'id') === [11, 7], 'No-date activities must use deterministic activity date ordering.');

$counts = Activity::agendaCountsForOwner(1);
agenda_expect($counts === ['overdue' => 1, 'today' => 1, 'upcoming' => 2, 'later' => 2, 'nodate' => 2, 'open' => 7], 'Agenda counts must match visible buckets and preserve Open semantics.');
agenda_expect(array_sum(array_intersect_key($counts, array_flip(['overdue', 'today', 'upcoming', 'later', 'nodate']))) === 8, 'Each non-Done activity must belong to exactly one bucket.');

try {
    Activity::agendaForOwner(1, 'invalid');
    agenda_expect(false, 'Invalid buckets must be rejected.');
} catch (InvalidArgumentException) {
}

echo "Activity agenda tests passed." . PHP_EOL;
