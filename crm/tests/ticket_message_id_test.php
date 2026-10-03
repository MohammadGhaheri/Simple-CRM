<?php

declare(strict_types=1);

final class MessageIdTestStatement
{
    public function __construct(private MessageIdTestDatabase $database, private string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        if (str_starts_with($sql, 'INSERT INTO ticket_messages')) {
            $this->database->lastId = ++$this->database->sequence;
            $this->database->messageIds[] = $this->database->lastId;
        } elseif (str_starts_with($sql, 'UPDATE tickets SET updated_at')) {
            $this->database->ticketTouches++;
            $this->database->lastId = 0;
        } elseif (str_starts_with($sql, 'UPDATE ticket_messages')) {
            $this->database->lastId = 0;
        }
        return true;
    }
}

final class MessageIdTestDatabase
{
    public int $sequence = 100;
    public int $lastId = 0;
    public int $ticketTouches = 0;
    public array $messageIds = [];

    public function prepare(string $sql): MessageIdTestStatement
    {
        return new MessageIdTestStatement($this, $sql);
    }

    public function lastInsertId(): string
    {
        return (string) $this->lastId;
    }
}

$messageIdTestDatabase = new MessageIdTestDatabase();
function db(): MessageIdTestDatabase
{
    global $messageIdTestDatabase;
    return $messageIdTestDatabase;
}

require __DIR__ . '/../app/models/TicketMessage.php';

function message_id_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$contactMessageId = TicketMessage::createFromContact(10, 20, 'Contact reply');
$userMessageId = TicketMessage::createFromUser(10, 30, 'User reply');
message_id_expect($contactMessageId === 101, 'Contact reply must return its inserted message id.');
message_id_expect($userMessageId === 102, 'User reply must return its inserted message id.');
message_id_expect($messageIdTestDatabase->messageIds === [101, 102], 'Returned ids must match inserted rows.');
message_id_expect($messageIdTestDatabase->ticketTouches === 2, 'Each message must still update the ticket timestamp.');

$source = file_get_contents(__DIR__ . '/../app/models/TicketMessage.php');
$insertAt = strpos($source, '$pdo->prepare($sql)->execute');
$lastIdAt = strpos($source, '$messageId = (int) $pdo->lastInsertId()', $insertAt);
$ticketUpdateAt = strpos($source, "\$pdo->prepare('UPDATE tickets SET updated_at", $insertAt);
message_id_expect($insertAt !== false && $lastIdAt > $insertAt && $ticketUpdateAt > $lastIdAt, 'lastInsertId must be captured immediately after INSERT and before ticket UPDATE.');

echo "Ticket message id tests passed." . PHP_EOL;
