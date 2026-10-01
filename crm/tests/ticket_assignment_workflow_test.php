<?php

declare(strict_types=1);

final class WorkflowStatement
{
    private mixed $result = false;

    public function __construct(private WorkflowDatabase $database, public string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        if (str_starts_with($sql, 'SELECT id, name, email, mobile, role, avatar_path, is_active FROM users')) {
            $this->result = $this->database->users[(int) $params[0]] ?? false;
        } elseif (str_starts_with($sql, 'UPDATE ticket_messages') && str_contains($sql, 'assigned_user_id')) {
            [$ticketId, $userId] = array_map('intval', $params);
            if (($this->database->tickets[$ticketId]['assigned_user_id'] ?? null) === $userId) {
                $this->database->markMessagesRead($ticketId);
            }
        } elseif (str_starts_with($sql, 'UPDATE ticket_messages')) {
            $this->database->markMessagesRead((int) $params[0]);
        } elseif (str_starts_with($sql, 'SELECT assigned_user_id FROM tickets')) {
            $ticket = $this->database->tickets[(int) $params[0]] ?? null;
            $this->result = $ticket === null ? false : $ticket['assigned_user_id'];
        } elseif (str_starts_with($sql, 'UPDATE tickets SET assigned_user_id')) {
            $this->database->tickets[(int) $params[1]]['assigned_user_id'] = $params[0];
        } elseif (str_starts_with($sql, 'INSERT INTO ticket_assignment_events')) {
            if ($this->database->failEventInsert) {
                throw new RuntimeException('event insert failed');
            }
            $this->database->events[] = [
                'ticket_id' => $params[0], 'from_user_id' => $params[1], 'to_user_id' => $params[2],
                'changed_by_user_id' => $params[3], 'seen_at' => $params[4],
            ];
        } elseif (str_starts_with($sql, 'UPDATE ticket_assignment_events')) {
            foreach ($this->database->events as &$event) {
                if ($event['ticket_id'] === $params[0] && $event['to_user_id'] === $params[1]
                    && $event['seen_at'] === null && $this->database->tickets[$params[0]]['assigned_user_id'] === $params[2]) {
                    $event['seen_at'] = 'seen';
                }
            }
        } elseif (str_starts_with($sql, 'SELECT COUNT(DISTINCT t.id)')) {
            $userId = (int) $params[0];
            $count = 0;
            foreach ($this->database->tickets as $ticketId => $ticket) {
                if ($ticket['assigned_user_id'] !== $userId) {
                    continue;
                }
                $unread = array_filter($this->database->messages, fn(array $m): bool => $m['ticket_id'] === $ticketId && !$m['read']);
                $notice = array_filter($this->database->events, fn(array $e): bool => $e['ticket_id'] === $ticketId && $e['to_user_id'] === $userId && $e['seen_at'] === null);
                $count += ($unread || $notice) ? 1 : 0;
            }
            $this->result = $count;
        }
        return true;
    }

    public function fetch(): mixed { return $this->result; }
    public function fetchColumn(): mixed { return is_array($this->result) ? reset($this->result) : $this->result; }
}

final class WorkflowDatabase
{
    public array $users = [1 => ['id' => 1, 'name' => 'Ali', 'is_active' => 1], 2 => ['id' => 2, 'name' => 'Mohammad', 'is_active' => 1], 3 => ['id' => 3, 'name' => 'Inactive', 'is_active' => 0]];
    public array $tickets = [];
    public array $messages = [];
    public array $events = [];
    public bool $failEventInsert = false;
    private array $snapshot = [];

    public function prepare(string $sql): WorkflowStatement { return new WorkflowStatement($this, $sql); }
    public function beginTransaction(): bool { $this->snapshot = [$this->tickets, $this->events]; return true; }
    public function commit(): bool { $this->snapshot = []; return true; }
    public function rollBack(): bool { [$this->tickets, $this->events] = $this->snapshot; $this->snapshot = []; return true; }
    public function inTransaction(): bool { return $this->snapshot !== []; }
    public function markMessagesRead(int $ticketId): void { foreach ($this->messages as &$message) { if ($message['ticket_id'] === $ticketId) { $message['read'] = true; } } }
}

$workflowDb = new WorkflowDatabase();
function db(): WorkflowDatabase { global $workflowDb; return $workflowDb; }

require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/models/TicketMessage.php';
require __DIR__ . '/../app/models/TicketAssignmentEvent.php';

function workflow_expect(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$workflowDb->tickets = [10 => ['assigned_user_id' => 1]];
$workflowDb->messages = [['ticket_id' => 10, 'read' => false], ['ticket_id' => 10, 'read' => false], ['ticket_id' => 10, 'read' => false]];
TicketMessage::markReadForAssignedUser(10, 2);
workflow_expect(!$workflowDb->messages[0]['read'], 'Non-owner open must preserve NEW.');
TicketMessage::markReadForAssignedUser(10, 1);
workflow_expect($workflowDb->messages[0]['read'], 'Assigned owner open must clear NEW.');

$workflowDb->messages[0]['read'] = false;
TicketMessage::markHandledByInternalReply(10);
workflow_expect($workflowDb->messages[0]['read'], 'Any internal reply must clear NEW.');
$workflowDb->tickets[11] = ['assigned_user_id' => null];
$workflowDb->messages[] = ['ticket_id' => 11, 'read' => false];
TicketMessage::markReadForAssignedUser(11, 2);
workflow_expect(!$workflowDb->messages[3]['read'], 'Opening an unassigned ticket must preserve NEW.');
TicketMessage::markHandledByInternalReply(11);
workflow_expect($workflowDb->messages[3]['read'], 'Replying to an unassigned ticket must clear NEW.');

$workflowDb->messages[0]['read'] = false;
workflow_expect(Ticket::reassign(10, 2, 1), 'Ali to Mohammad must change assignment.');
workflow_expect($workflowDb->tickets[10]['assigned_user_id'] === 2 && count($workflowDb->events) === 1, 'Reassignment and event must both persist.');
workflow_expect(!$workflowDb->messages[0]['read'], 'Reassignment must not clear customer unread.');
workflow_expect(!Ticket::reassign(10, 2, 1) && count($workflowDb->events) === 1, 'Same-owner reassignment must be a no-op.');
TicketMessage::markReadForAssignedUser(10, 1);
workflow_expect(!$workflowDb->messages[0]['read'], 'Old owner open must preserve NEW.');
TicketMessage::markReadForAssignedUser(10, 2);
workflow_expect($workflowDb->messages[0]['read'], 'New owner open must clear NEW.');

workflow_expect(Ticket::reassign(10, null, 1), 'Removing an owner must be supported.');
workflow_expect($workflowDb->events[1]['to_user_id'] === null && $workflowDb->events[1]['seen_at'] !== null, 'Unassigned event must not create an unseen notice.');
workflow_expect(Ticket::reassign(10, 2, 1), 'NULL to Mohammad must be recorded.');
workflow_expect($workflowDb->events[2]['seen_at'] === null, 'Assignment to another user must be unseen.');
TicketAssignmentEvent::markSeenForTicketAndUser(10, 1);
workflow_expect($workflowDb->events[2]['seen_at'] === null, 'Another user must not see the recipient notice.');
TicketAssignmentEvent::markSeenForTicketAndUser(10, 2);
workflow_expect($workflowDb->events[2]['seen_at'] !== null, 'The assigned user must see the notice.');
workflow_expect(Ticket::reassign(10, 1, 1), 'Assignment to self must still create history.');
workflow_expect($workflowDb->events[3]['seen_at'] !== null, 'Assignment to self must not create an unread notice.');
try {
    Ticket::reassign(10, 3, 1);
    workflow_expect(false, 'Inactive users must not be assignable.');
} catch (RuntimeException $error) {
    workflow_expect(str_contains($error->getMessage(), 'فعال نیست'), 'Inactive user validation must be explicit.');
}

Ticket::reassign(10, 2, 1);

$workflowDb->messages[0]['read'] = false;
$workflowDb->events[array_key_last($workflowDb->events)]['seen_at'] = null;
workflow_expect(Ticket::attentionCountForUser(2) === 1, 'Three messages plus an event on one ticket must count once.');
$workflowDb->tickets[12] = ['assigned_user_id' => 2];
$workflowDb->messages[] = ['ticket_id' => 12, 'read' => false];
workflow_expect(Ticket::attentionCountForUser(2) === 2, 'Two distinct assigned tickets must count twice.');
workflow_expect(Ticket::attentionCountForUser(1) === 0, 'Another owner must not receive the attention count.');

$workflowDb->tickets[13] = ['assigned_user_id' => 1];
$workflowDb->failEventInsert = true;
try { Ticket::reassign(13, 2, 1); } catch (RuntimeException) {}
workflow_expect($workflowDb->tickets[13]['assigned_user_id'] === 1, 'Event failure must roll back assignment.');

$controller = file_get_contents(__DIR__ . '/../public/index.php');
$messageModel = file_get_contents(__DIR__ . '/../app/models/TicketMessage.php');
$ticketModel = file_get_contents(__DIR__ . '/../app/models/Ticket.php');
$smsService = file_get_contents(__DIR__ . '/../app/services/SmsService.php');
$replyCreateAt = strpos($messageModel, '$messageId = self::create([');
$handledAt = strpos($messageModel, 'self::markHandledByInternalReply($ticketId)', $replyCreateAt);
$replyReturnAt = strpos($messageModel, 'return $messageId', $replyCreateAt);
workflow_expect($replyCreateAt !== false && $handledAt > $replyCreateAt && $handledAt < $replyReturnAt, 'Every successful internal reply must clear NEW before returning to redirects.');
workflow_expect(str_contains($controller, 'if (!is_post())') && str_contains($controller, 'markReadForAssignedUser'), 'Only opening the ticket with GET may apply owner-read behavior.');
workflow_expect(str_contains($controller, 'Ticket::normalizePostedListContext($_POST)'), 'Reassignment must preserve prefixed queue context.');
workflow_expect(str_contains($ticketModel, ') AS unread_count') && !str_contains($ticketModel, 'WHERE t.assigned_user_id = ?\n                  AND t.deleted_at IS NULL";'), 'Shared ticket list must retain global visibility and unread indicators.');
workflow_expect(str_contains($smsService, "sms_ticket_assignment_enabled") && str_contains($smsService, "targetUser['mobile']"), 'Assignment SMS must target only the new assignee.');
$assignmentSms = substr($smsService, strpos($smsService, 'notifyTicketAssigned'), strpos($smsService, 'sendPortalCredentials') - strpos($smsService, 'notifyTicketAssigned'));
workflow_expect(!str_contains($assignmentSms, 'sms_admin_mobile'), 'Assignment SMS must not fall back to the admin mobile.');

echo "Ticket assignment workflow tests passed." . PHP_EOL;
