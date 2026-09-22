<?php

declare(strict_types=1);

class PerformanceReport
{
    public const ROLES = ['admin', 'sales', 'support', 'operations'];

    public static function normalizeFilters(array $input, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        [$jy, $jm] = gregorian_to_jalali((int) $now->format('Y'), (int) $now->format('n'), (int) $now->format('j'));
        [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, 1);
        $defaultFrom = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        $defaultTo = $now->format('Y-m-d');

        $from = self::validDateInput((string) ($input['date_from'] ?? '')) ?? $defaultFrom;
        $to = self::validDateInput((string) ($input['date_to'] ?? '')) ?? $defaultTo;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $role = (string) ($input['role'] ?? '');
        if (!in_array($role, self::ROLES, true)) {
            $role = '';
        }
        $userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'date_from' => fa_date($from),
            'date_to' => fa_date($to),
            'start' => $from . ' 00:00:00',
            'end' => (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
            'user_id' => $userId ? (int) $userId : 0,
            'role' => $role,
        ];
    }

    public static function build(array $filters): array
    {
        $users = self::users($filters);
        $userIds = array_map(static fn(array $user): int => (int) $user['id'], $users);
        $rows = [];
        foreach ($users as $user) {
            $id = (int) $user['id'];
            $rows[$id] = array_merge($user, [
                'last_login' => null,
                'login_count' => 0,
                'session_count' => 0,
                'active_seconds' => 0,
                'last_seen' => null,
                'total_views' => 0,
                'unique_views' => 0,
                'activities_created' => 0,
                'first_responses' => 0,
                'assigned_unanswered' => 0,
                'tickets_closed' => 0,
                'first_response_seconds' => [],
                'resolution_seconds' => [],
            ]);
        }

        if ($userIds) {
            self::merge($rows, self::loginMetrics($filters, $userIds));
            self::merge($rows, self::sessionMetrics($filters, $userIds));
            self::merge($rows, self::viewMetrics($filters, $userIds));
            self::merge($rows, self::activityMetrics($filters, $userIds));
        }

        $tickets = $userIds ? self::customerTickets($filters, $userIds) : [];
        foreach ($tickets as $ticket) {
            $firstResponder = (int) ($ticket['first_responder_id'] ?? 0);
            $firstSeconds = self::secondsBetween($ticket['created_at'], $ticket['first_response_at'] ?? null);
            if ($firstResponder > 0 && isset($rows[$firstResponder])) {
                $rows[$firstResponder]['first_responses']++;
                if ($firstSeconds !== null) {
                    $rows[$firstResponder]['first_response_seconds'][] = $firstSeconds;
                }
            }
            $assigned = (int) ($ticket['assigned_user_id'] ?? 0);
            if (!$ticket['first_response_at'] && $assigned > 0 && isset($rows[$assigned])) {
                $rows[$assigned]['assigned_unanswered']++;
            }
        }

        $closings = self::finalClosings($filters, $userIds);
        foreach ($closings as $closing) {
            $closer = (int) ($closing['closer_user_id'] ?? 0);
            if ($closer <= 0 || !isset($rows[$closer])) {
                continue;
            }
            $rows[$closer]['tickets_closed']++;
            $seconds = self::secondsBetween($closing['ticket_created_at'], $closing['closed_event_at']);
            if ($seconds !== null) {
                $rows[$closer]['resolution_seconds'][] = $seconds;
            }
        }

        foreach ($rows as &$row) {
            $row['avg_first_response'] = self::average($row['first_response_seconds']);
            $row['avg_resolution'] = self::average($row['resolution_seconds']);
        }
        unset($row);

        uasort($rows, static function (array $a, array $b): int {
            $active = (int) $b['is_active'] <=> (int) $a['is_active'];
            return $active !== 0 ? $active : strnatcasecmp((string) $a['name'], (string) $b['name']);
        });

        $summary = self::summary($rows, $tickets);
        $summary['unique_views'] = self::globalUniqueViews($filters, $userIds);

        return [
            'filters' => $filters,
            'summary' => $summary,
            'users' => array_values($rows),
            'support_users' => array_values(array_filter($rows, static fn(array $row): bool => $row['role'] === 'support')),
            'ticket_statuses' => self::ticketStatuses($tickets),
            'view_breakdown' => self::viewBreakdown($filters, $userIds),
            'ticket_durations' => self::ticketDurations($tickets),
        ];
    }

    public static function median(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn($value): bool => $value !== null && is_numeric($value)));
        if (!$values) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        return $count % 2 ? (float) $values[$middle] : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    public static function formatDuration(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        $seconds = max(0, (int) round($seconds));
        if ($seconds < 60) {
            return $seconds . ' ثانیه';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . ' دقیقه';
        }
        if ($seconds < 86400) {
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            return $hours . ' ساعت' . ($minutes ? ' و ' . $minutes . ' دقیقه' : '');
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        return $days . ' روز' . ($hours ? ' و ' . $hours . ' ساعت' : '');
    }

    public static function uniqueRecordCount(array $events): int
    {
        $unique = [];
        foreach ($events as $event) {
            $unique[(string) ($event['entity_type'] ?? '') . ':' . (int) ($event['entity_id'] ?? 0)] = true;
        }
        return count($unique);
    }

    private static function validDateInput(string $value): ?string
    {
        $date = db_date(trim($value));
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year) ? $date : null;
    }

    private static function users(array $filters): array
    {
        $sql = 'SELECT id, name, role, is_active FROM users WHERE 1=1';
        $params = [];
        if ($filters['role'] !== '') {
            $sql .= ' AND role = ?';
            $params[] = $filters['role'];
        }
        if ($filters['user_id'] > 0) {
            $sql .= ' AND id = ?';
            $params[] = $filters['user_id'];
        }
        $sql .= ' ORDER BY is_active DESC, name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function loginMetrics(array $filters, array $userIds): array
    {
        [$in, $params] = self::inParams($userIds);
        $sql = "SELECT u.id AS user_id,
                    MAX(le.created_at) AS last_login,
                    SUM(CASE WHEN le.created_at >= ? AND le.created_at < ? THEN 1 ELSE 0 END) AS login_count
                FROM users u
                LEFT JOIN login_events le ON le.actor_type='user' AND le.actor_id=u.id
                WHERE u.id IN ($in)
                GROUP BY u.id";
        return self::keyedQuery($sql, array_merge([$filters['start'], $filters['end']], $params));
    }

    private static function sessionMetrics(array $filters, array $userIds): array
    {
        [$in, $params] = self::inParams($userIds);
        // active_seconds is session-level; sessions are attributed by started_at because no per-second timeline exists.
        $sql = "SELECT user_id, COUNT(*) session_count, COALESCE(SUM(active_seconds),0) active_seconds, MAX(last_seen_at) last_seen
                FROM user_sessions WHERE started_at >= ? AND started_at < ? AND user_id IN ($in) GROUP BY user_id";
        return self::keyedQuery($sql, array_merge([$filters['start'], $filters['end']], $params));
    }

    private static function viewMetrics(array $filters, array $userIds): array
    {
        [$in, $params] = self::inParams($userIds);
        $sql = "SELECT user_id, COUNT(*) total_views,
                    COUNT(DISTINCT CONCAT(entity_type, ':', entity_id)) unique_views
                FROM record_view_events WHERE viewed_at >= ? AND viewed_at < ? AND user_id IN ($in) GROUP BY user_id";
        return self::keyedQuery($sql, array_merge([$filters['start'], $filters['end']], $params));
    }

    private static function activityMetrics(array $filters, array $userIds): array
    {
        [$in, $params] = self::inParams($userIds);
        // Soft-deleted activities remain counted as historical contribution by their recorded creator.
        $sql = "SELECT created_by_user_id user_id, COUNT(*) activities_created FROM activities
                WHERE created_at >= ? AND created_at < ? AND created_by_user_id IN ($in) GROUP BY created_by_user_id";
        return self::keyedQuery($sql, array_merge([$filters['start'], $filters['end']], $params));
    }

    private static function customerTickets(array $filters, array $userIds): array
    {
        $params = [$filters['start'], $filters['end']];
        $userClause = '';
        if ($userIds && ($filters['user_id'] > 0 || $filters['role'] !== '')) {
            [$in, $ids] = self::inParams($userIds);
            $userClause = " AND (t.assigned_user_id IN ($in) OR EXISTS (SELECT 1 FROM ticket_messages um WHERE um.ticket_id=t.id AND um.sender_type='user' AND um.sender_user_id IN ($in)))";
            $params = array_merge($params, $ids, $ids);
        }
        $sql = "SELECT t.id, t.status, t.created_at, t.closed_at, t.assigned_user_id,
                    (SELECT tm.created_at FROM ticket_messages tm WHERE tm.ticket_id=t.id AND tm.sender_type='user' ORDER BY tm.created_at, tm.id LIMIT 1) first_response_at,
                    (SELECT tm.sender_user_id FROM ticket_messages tm WHERE tm.ticket_id=t.id AND tm.sender_type='user' ORDER BY tm.created_at, tm.id LIMIT 1) first_responder_id
                FROM tickets t
                WHERE t.origin_type='contact' AND t.created_at >= ? AND t.created_at < ?{$userClause}";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function finalClosings(array $filters, array $userIds): array
    {
        if (!$userIds) {
            return [];
        }
        [$in, $ids] = self::inParams($userIds);
        $sql = "SELECT e.ticket_id, e.changed_by_user_id closer_user_id, e.created_at closed_event_at, t.created_at ticket_created_at
                FROM ticket_status_events e
                JOIN tickets t ON t.id=e.ticket_id
                WHERE t.status IN ('Closed','Resolved')
                  AND e.changed_by_type='user' AND e.changed_by_user_id IN ($in)
                  AND e.to_status IN ('Closed','Resolved')
                  AND e.created_at >= ? AND e.created_at < ?
                  AND NOT EXISTS (
                    SELECT 1 FROM ticket_status_events later
                    WHERE later.ticket_id=e.ticket_id AND later.to_status IN ('Closed','Resolved')
                      AND (later.created_at > e.created_at OR (later.created_at=e.created_at AND later.id>e.id))
                  )";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge($ids, [$filters['start'], $filters['end']]));
        return $stmt->fetchAll();
    }

    private static function viewBreakdown(array $filters, array $userIds): array
    {
        if (!$userIds) {
            return [];
        }
        [$in, $ids] = self::inParams($userIds);
        $stmt = db()->prepare("SELECT entity_type, COUNT(*) total FROM record_view_events WHERE viewed_at >= ? AND viewed_at < ? AND user_id IN ($in) GROUP BY entity_type ORDER BY total DESC");
        $stmt->execute(array_merge([$filters['start'], $filters['end']], $ids));
        return $stmt->fetchAll();
    }

    private static function globalUniqueViews(array $filters, array $userIds): int
    {
        if (!$userIds) {
            return 0;
        }
        [$in, $ids] = self::inParams($userIds);
        $stmt = db()->prepare("SELECT COUNT(DISTINCT CONCAT(entity_type, ':', entity_id)) FROM record_view_events WHERE viewed_at >= ? AND viewed_at < ? AND user_id IN ($in)");
        $stmt->execute(array_merge([$filters['start'], $filters['end']], $ids));
        return (int) $stmt->fetchColumn();
    }

    private static function summary(array $rows, array $tickets): array
    {
        $first = [];
        $resolution = [];
        $answered = 0;
        $closed = 0;
        foreach ($tickets as $ticket) {
            $seconds = self::secondsBetween($ticket['created_at'], $ticket['first_response_at'] ?? null);
            if ($seconds !== null) {
                $answered++;
                $first[] = $seconds;
            }
            $seconds = self::secondsBetween($ticket['created_at'], $ticket['closed_at'] ?? null);
            if ($seconds !== null) {
                $resolution[] = $seconds;
            }
            if (in_array($ticket['status'], ['Closed', 'Resolved'], true)) {
                $closed++;
            }
        }
        return [
            'active_users' => array_sum(array_map(static fn($row): int => (int) $row['is_active'], $rows)),
            'logins' => array_sum(array_column($rows, 'login_count')),
            'active_seconds' => array_sum(array_column($rows, 'active_seconds')),
            'total_views' => array_sum(array_column($rows, 'total_views')),
            'unique_views' => array_sum(array_column($rows, 'unique_views')),
            'activities' => array_sum(array_column($rows, 'activities_created')),
            'customer_tickets' => count($tickets),
            'answered' => $answered,
            'unanswered' => count($tickets) - $answered,
            'closed' => $closed,
            'avg_first_response' => self::average($first),
            'median_first_response' => self::median($first),
            'avg_resolution' => self::average($resolution),
            'median_resolution' => self::median($resolution),
        ];
    }

    private static function ticketStatuses(array $tickets): array
    {
        $counts = array_fill_keys(['Open', 'In Progress', 'Waiting Customer', 'Resolved', 'Closed'], 0);
        foreach ($tickets as $ticket) {
            $status = (string) $ticket['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return $counts;
    }

    private static function ticketDurations(array $tickets): array
    {
        $first = [];
        $resolution = [];
        foreach ($tickets as $ticket) {
            $firstSeconds = self::secondsBetween($ticket['created_at'], $ticket['first_response_at'] ?? null);
            if ($firstSeconds !== null) {
                $first[] = $firstSeconds;
            }
            $resolutionSeconds = self::secondsBetween($ticket['created_at'], $ticket['closed_at'] ?? null);
            if ($resolutionSeconds !== null) {
                $resolution[] = $resolutionSeconds;
            }
        }
        return ['first' => $first, 'resolution' => $resolution];
    }

    private static function secondsBetween(?string $from, ?string $to): ?int
    {
        if (!$from || !$to) {
            return null;
        }
        $seconds = strtotime($to) - strtotime($from);
        return $seconds >= 0 ? $seconds : null;
    }

    private static function average(array $values): ?float
    {
        return $values ? array_sum($values) / count($values) : null;
    }

    private static function merge(array &$rows, array $metrics): void
    {
        foreach ($metrics as $userId => $values) {
            if (isset($rows[$userId])) {
                $rows[$userId] = array_merge($rows[$userId], $values);
            }
        }
    }

    private static function keyedQuery(string $sql, array $params): array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['user_id']] = $row;
        }
        return $result;
    }

    private static function inParams(array $ids): array
    {
        return [implode(',', array_fill(0, count($ids), '?')), array_values($ids)];
    }
}
