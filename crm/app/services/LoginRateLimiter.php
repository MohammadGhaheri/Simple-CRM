<?php

declare(strict_types=1);

class LoginRateLimiter
{
    public const PAIR_MAX_FAILURES = 5;
    public const PAIR_WINDOW_SECONDS = 900;
    public const PAIR_BLOCK_SECONDS = 900;
    public const IP_MAX_FAILURES = 20;
    public const IP_WINDOW_SECONDS = 900;
    public const IP_BLOCK_SECONDS = 1800;
    public const MAX_IDENTIFIERS_PER_IP = 50;
    private const STATE_VERSION = 1;
    private const CLEANUP_MAX_AGE = 172800;
    private const CLEANUP_SCAN_LIMIT = 100;

    private string $storagePath;
    private bool $usesDefaultStorage;
    private $clock;

    public function __construct(?string $storagePath = null, ?callable $clock = null)
    {
        $this->usesDefaultStorage = $storagePath === null;
        $this->storagePath = $storagePath ?? dirname(__DIR__, 2) . '/storage/rate-limits';
        $this->clock = $clock ?? static fn(): int => time();
    }

    public static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip !== '' ? $ip : 'unknown';
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        return function_exists('mb_strtolower')
            ? mb_strtolower($identifier, 'UTF-8')
            : strtolower($identifier);
    }

    public function check(string $scope, string $identifier, string $ip): array
    {
        $identifierHash = $this->identifierHash($identifier);
        return $this->withLockedState($scope, $ip, function (array &$state, int $now) use ($identifierHash): array {
            return $this->blockResult($state, $identifierHash, $now);
        });
    }

    public function recordFailure(string $scope, string $identifier, string $ip): array
    {
        $identifierHash = $this->identifierHash($identifier);
        $result = $this->withLockedState($scope, $ip, function (array &$state, int $now) use ($identifierHash): array {
            $state['global_attempts'][] = $now;
            if (count($state['global_attempts']) >= self::IP_MAX_FAILURES) {
                $state['global_blocked_until'] = max((int) $state['global_blocked_until'], $now + self::IP_BLOCK_SECONDS);
            }

            $pair = $state['identifiers'][$identifierHash] ?? $this->emptyPairState();
            $pair['attempts'][] = $now;
            $pair['updated_at'] = $now;
            if (count($pair['attempts']) >= self::PAIR_MAX_FAILURES) {
                $pair['blocked_until'] = max((int) $pair['blocked_until'], $now + self::PAIR_BLOCK_SECONDS);
            }
            $state['identifiers'][$identifierHash] = $pair;
            $this->capIdentifiers($state['identifiers']);

            return $this->blockResult($state, $identifierHash, $now);
        });
        $this->maybeCleanup();
        return $result;
    }

    public function recordSuccess(string $scope, string $identifier, string $ip): void
    {
        $identifierHash = $this->identifierHash($identifier);
        $this->withLockedState($scope, $ip, static function (array &$state) use ($identifierHash): array {
            unset($state['identifiers'][$identifierHash]);
            return ['blocked' => false, 'retry_after' => 0];
        });
        $this->maybeCleanup();
    }

    private function withLockedState(string $scope, string $ip, callable $callback): array
    {
        $this->assertScope($scope);
        $directory = $this->prepareStorage();
        $filePath = $directory . '/' . hash('sha256', $scope . "\0" . $this->normalizeIp($ip)) . '.json';
        if (is_link($filePath)) {
            throw new RuntimeException('فضای محافظت ورود معتبر نیست.');
        }

        $handle = @fopen($filePath, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('فضای محافظت ورود قابل نوشتن نیست.');
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($filePath, 0600);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('قفل محافظت ورود قابل دریافت نیست.');
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = $this->decodeState($raw === false ? '' : $raw);
            $now = ($this->clock)();
            $this->pruneState($state, $now);
            $result = $callback($state, $now);
            $state['updated_at'] = $now;
            $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new RuntimeException('ثبت وضعیت محافظت ورود ناموفق بود.');
            }
            return $result;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function prepareStorage(): string
    {
        if (is_link($this->storagePath)) {
            throw new RuntimeException('مسیر محافظت ورود معتبر نیست.');
        }
        if (!is_dir($this->storagePath) && !@mkdir($this->storagePath, 0700, true) && !is_dir($this->storagePath)) {
            throw new RuntimeException('ساخت فضای محافظت ورود ناموفق بود.');
        }
        $realPath = realpath($this->storagePath);
        if ($realPath === false || !is_writable($realPath)) {
            throw new RuntimeException('فضای محافظت ورود قابل نوشتن نیست.');
        }
        if ($this->usesDefaultStorage) {
            $storageRoot = realpath(dirname(__DIR__, 2) . '/storage');
            $boundary = $storageRoot === false ? '' : rtrim(str_replace('\\', '/', $storageRoot), '/') . '/';
            $normalizedPath = rtrim(str_replace('\\', '/', $realPath), '/') . '/';
            if ($boundary === '' || !str_starts_with($normalizedPath, $boundary)) {
                throw new RuntimeException('مسیر محافظت ورود معتبر نیست.');
            }
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($realPath, 0700);
        }
        return rtrim($realPath, '/\\');
    }

    private function decodeState(string $raw): array
    {
        if ($raw === '') {
            return $this->emptyState();
        }
        try {
            $state = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($state)
                || ($state['version'] ?? null) !== self::STATE_VERSION
                || !is_array($state['global_attempts'] ?? null)
                || !is_array($state['identifiers'] ?? null)) {
                throw new UnexpectedValueException('Invalid rate limit state shape.');
            }
            return $state;
        } catch (Throwable) {
            error_log('CRM login rate-limit state was malformed and has been reset.');
            return $this->emptyState();
        }
    }

    private function pruneState(array &$state, int $now): void
    {
        $state['global_attempts'] = $this->validAttempts($state['global_attempts'] ?? [], $now - self::IP_WINDOW_SECONDS, $now);
        $state['global_blocked_until'] = (int) ($state['global_blocked_until'] ?? 0);
        if ($state['global_blocked_until'] <= $now) {
            $state['global_blocked_until'] = 0;
        }

        $identifiers = [];
        foreach (($state['identifiers'] ?? []) as $hash => $pair) {
            if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash) || !is_array($pair)) {
                continue;
            }
            $attempts = $this->validAttempts($pair['attempts'] ?? [], $now - self::PAIR_WINDOW_SECONDS, $now);
            $blockedUntil = (int) ($pair['blocked_until'] ?? 0);
            if ($blockedUntil <= $now) {
                $blockedUntil = 0;
            }
            if ($attempts || $blockedUntil > 0) {
                $identifiers[$hash] = [
                    'attempts' => $attempts,
                    'blocked_until' => $blockedUntil,
                    'updated_at' => (int) ($pair['updated_at'] ?? $now),
                ];
            }
        }
        $state['identifiers'] = $identifiers;
        $this->capIdentifiers($state['identifiers']);
    }

    private function validAttempts(mixed $attempts, int $minimum, int $now): array
    {
        if (!is_array($attempts)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $attempts), static fn(int $attempt): bool => $attempt > $minimum && $attempt <= $now));
    }

    private function capIdentifiers(array &$identifiers): void
    {
        if (count($identifiers) <= self::MAX_IDENTIFIERS_PER_IP) {
            return;
        }
        uasort($identifiers, static fn(array $left, array $right): int => ((int) $right['updated_at']) <=> ((int) $left['updated_at']));
        $identifiers = array_slice($identifiers, 0, self::MAX_IDENTIFIERS_PER_IP, true);
    }

    private function blockResult(array $state, string $identifierHash, int $now): array
    {
        $globalUntil = (int) ($state['global_blocked_until'] ?? 0);
        $pairUntil = (int) ($state['identifiers'][$identifierHash]['blocked_until'] ?? 0);
        $blockedUntil = max($globalUntil, $pairUntil);
        return [
            'blocked' => $blockedUntil > $now,
            'retry_after' => max(0, $blockedUntil - $now),
        ];
    }

    private function identifierHash(string $identifier): string
    {
        return hash('sha256', self::normalizeIdentifier($identifier));
    }

    private function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        return $ip !== '' ? $ip : 'unknown';
    }

    private function assertScope(string $scope): void
    {
        if (!in_array($scope, ['internal', 'portal'], true)) {
            throw new InvalidArgumentException('Login rate-limit scope is invalid.');
        }
    }

    private function emptyState(): array
    {
        return [
            'version' => self::STATE_VERSION,
            'global_attempts' => [],
            'global_blocked_until' => 0,
            'identifiers' => [],
            'updated_at' => 0,
        ];
    }

    private function emptyPairState(): array
    {
        return ['attempts' => [], 'blocked_until' => 0, 'updated_at' => 0];
    }

    private function maybeCleanup(): void
    {
        try {
            if (random_int(1, 100) !== 1 || !is_dir($this->storagePath) || is_link($this->storagePath)) {
                return;
            }
            $cutoff = ($this->clock)() - self::CLEANUP_MAX_AGE;
            $scanned = 0;
            foreach (new FilesystemIterator($this->storagePath, FilesystemIterator::SKIP_DOTS) as $file) {
                if (++$scanned > self::CLEANUP_SCAN_LIMIT) {
                    break;
                }
                if ($file->isFile() && !$file->isLink() && $file->getMTime() < $cutoff) {
                    @unlink($file->getPathname());
                }
            }
        } catch (Throwable) {
            // Cleanup is best-effort and must not interrupt authentication.
        }
    }
}
