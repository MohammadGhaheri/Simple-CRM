<?php

declare(strict_types=1);

final class QueueTestStatement
{
    public array $params = [];

    public function __construct(public string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $this->params = $params;
        return true;
    }

    public function fetchAll(int $mode = 0): array
    {
        return $mode === PDO::FETCH_COLUMN ? ['30', '20', '10'] : [];
    }
}

final class QueueTestDatabase
{
    public ?QueueTestStatement $statement = null;

    public function prepare(string $sql): QueueTestStatement
    {
        return $this->statement = new QueueTestStatement($sql);
    }
}

$queueTestDatabase = new QueueTestDatabase();
function db(): QueueTestDatabase
{
    global $queueTestDatabase;
    return $queueTestDatabase;
}

function option_values(string $key): array
{
    return match ($key) {
        'options_ticket_statuses' => ['Open', 'Closed'],
        'options_ticket_priorities' => ['Normal', 'High'],
        'options_ticket_categories' => ['Support', 'Bug'],
        default => [],
    };
}

require __DIR__ . '/../app/models/Ticket.php';

function queue_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$context = Ticket::normalizeListContext([
    'q' => '  GPS  ',
    'status' => 'Open',
    'priority' => 'High',
    'category' => 'Support',
    'assigned_user_id' => '7',
    'from_ticket_list' => '1',
    'return_url' => 'https://evil.example',
    'unknown' => 'drop-me',
]);
queue_expect($context === [
    'q' => 'GPS',
    'status' => 'Open',
    'priority' => 'High',
    'category' => 'Support',
    'assigned_user_id' => 7,
    'from_ticket_list' => 1,
], 'Only whitelisted, normalized context values may survive.');
queue_expect(!array_key_exists('return_url', Ticket::listParams($context, true)), 'Raw redirect URLs must never enter list context.');

$invalid = Ticket::normalizeListContext([
    'status' => 'Injected',
    'priority' => 'Urgent',
    'category' => 'Unknown',
    'assigned_user_id' => '-2',
]);
queue_expect($invalid === [], 'Invalid filters must be ignored safely.');

$ids = Ticket::filteredIds($context);
queue_expect($ids === [30, 20, 10], 'Filtered IDs must preserve model queue order.');
queue_expect(str_contains($queueTestDatabase->statement->sql, 't.assigned_user_id = ?'), 'Assigned user must be a prepared filter.');
queue_expect(end($queueTestDatabase->statement->params) === 7, 'Assigned user id must be bound as an integer parameter.');

$position = Ticket::queuePosition([30, 20, 10], 20);
queue_expect($position === ['previous_id' => 30, 'next_id' => 10, 'position' => 2, 'total' => 3], 'Middle ticket navigation is incorrect.');
$last = Ticket::queuePosition([30, 20, 10], 10);
queue_expect($last['next_id'] === null && $last['position'] === 3, 'Queue end must not invent a next ticket.');
$outside = Ticket::queuePosition([30, 20], 99);
queue_expect($outside['position'] === 0 && $outside['total'] === 2, 'A ticket outside the current queue must be explicit.');

$source = file_get_contents(__DIR__ . '/../public/index.php');
queue_expect(str_contains($source, '$nextTicketId = !empty($queue[\'next_id\'])'), 'Next ticket must be captured before mutation.');
queue_expect(str_contains($source, "in_array(\$action, ['stay', 'back', 'next'], true)"), 'after_action must use a strict whitelist.');
queue_expect(!str_contains($source, 'return_url'), 'Controller must not accept a raw return URL.');

echo "Ticket queue tests passed." . PHP_EOL;
