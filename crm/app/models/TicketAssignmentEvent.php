<?php

declare(strict_types=1);

class TicketAssignmentEvent
{
    public static function historyForTicket(int $ticketId): array
    {
        $stmt = db()->prepare(
            'SELECT e.*, from_user.name AS from_user_name, to_user.name AS to_user_name,
                    actor.name AS changed_by_user_name
             FROM ticket_assignment_events e
             LEFT JOIN users from_user ON from_user.id = e.from_user_id
             LEFT JOIN users to_user ON to_user.id = e.to_user_id
             LEFT JOIN users actor ON actor.id = e.changed_by_user_id
             WHERE e.ticket_id = ?
             ORDER BY e.created_at DESC, e.id DESC'
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public static function markSeenForTicketAndUser(int $ticketId, int $userId): void
    {
        db()->prepare(
            'UPDATE ticket_assignment_events e
             JOIN tickets t ON t.id = e.ticket_id
             SET e.seen_at = COALESCE(e.seen_at, CURRENT_TIMESTAMP)
             WHERE e.ticket_id = ? AND e.to_user_id = ? AND e.seen_at IS NULL
               AND t.assigned_user_id = ? AND t.deleted_at IS NULL'
        )->execute([$ticketId, $userId, $userId]);
    }
}
