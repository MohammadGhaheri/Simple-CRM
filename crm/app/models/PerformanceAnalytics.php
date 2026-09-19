<?php

declare(strict_types=1);

class PerformanceAnalytics
{
    public const INACTIVITY_THRESHOLD_SECONDS = 900;

    private const ENTITY_TYPES = ['customer', 'contact', 'deal', 'contract', 'activity', 'ticket'];

    public static function startUserSession(int $userId): int
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }

        $tokenHash = hash('sha256', session_id());
        $stmt = db()->prepare(
            'INSERT INTO user_sessions (user_id, session_token_hash, started_at, last_seen_at, active_seconds, ip_address, user_agent)
             VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $tokenHash,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        $sessionId = (int) db()->lastInsertId();
        $_SESSION['analytics_session_id'] = $sessionId;
        return $sessionId;
    }

    public static function touchUserSession(int $userId): void
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionId = (int) ($_SESSION['analytics_session_id'] ?? 0);
        if ($sessionId <= 0) {
            self::startUserSession($userId);
            return;
        }

        $stmt = db()->prepare(
            'UPDATE user_sessions
             SET active_seconds = active_seconds + CASE
                    WHEN TIMESTAMPDIFF(SECOND, last_seen_at, CURRENT_TIMESTAMP) BETWEEN 1 AND ?
                    THEN TIMESTAMPDIFF(SECOND, last_seen_at, CURRENT_TIMESTAMP)
                    ELSE 0
                 END,
                 last_seen_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ? AND ended_at IS NULL'
        );
        $stmt->execute([self::INACTIVITY_THRESHOLD_SECONDS, $sessionId, $userId]);

        if ($stmt->rowCount() === 0) {
            $exists = db()->prepare('SELECT id FROM user_sessions WHERE id = ? AND user_id = ? AND ended_at IS NULL');
            $exists->execute([$sessionId, $userId]);
            if (!$exists->fetchColumn()) {
                unset($_SESSION['analytics_session_id']);
                self::startUserSession($userId);
            }
        }
    }

    public static function endUserSession(int $userId): void
    {
        if ($userId <= 0 || (int) ($_SESSION['analytics_session_id'] ?? 0) <= 0) {
            return;
        }

        self::touchUserSession($userId);
        $sessionId = (int) ($_SESSION['analytics_session_id'] ?? 0);
        if ($sessionId <= 0) {
            return;
        }
        db()->prepare(
            'UPDATE user_sessions SET ended_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ? AND ended_at IS NULL'
        )->execute([$sessionId, $userId]);
        unset($_SESSION['analytics_session_id']);
    }

    public static function logRecordView(int $userId, string $entityType, int $entityId): void
    {
        if ($userId <= 0 || $entityId <= 0 || !in_array($entityType, self::ENTITY_TYPES, true)) {
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO record_view_events (user_id, entity_type, entity_id, viewed_at, ip_address)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)'
        );
        $stmt->execute([
            $userId,
            $entityType,
            $entityId,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        ]);
    }

    public static function activeSecondsForGap(int $gapSeconds): int
    {
        return $gapSeconds >= 1 && $gapSeconds <= self::INACTIVITY_THRESHOLD_SECONDS ? $gapSeconds : 0;
    }
}
