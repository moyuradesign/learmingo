<?php
declare(strict_types=1);

namespace Learmingo\Core;

/**
 * Shared-hosting compatible, locked-file request limiter.
 * Counters persist across PHP worker processes. Storage must remain outside public/.
 */
final class RateLimiter
{
    private static function path(string $scope, string $identifier): string
    {
        $root = dirname(__DIR__, 2) . '/storage/security/rate-limits';
        if (!is_dir($root) && !@mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Rate-limit storage is unavailable.');
        }
        if (is_link($root) || !is_writable($root)) {
            throw new \RuntimeException('Rate-limit storage is unsafe or unavailable.');
        }
        return $root . '/' . hash('sha256', $scope . "\0" . $identifier) . '.json';
    }

    /**
     * Record one attempt. Return seconds to wait, or 0 when allowed.
     */
    public static function hit(string $scope, string $identifier, int $limit, int $windowSeconds): int
    {
        if ($limit < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Invalid rate-limit policy.');
        }
        $file = self::path($scope, $identifier);
        $handle = @fopen($file, 'c+');
        if (!$handle) {
            throw new \RuntimeException('Cannot open rate-limit counter.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock rate-limit counter.');
            }
            @chmod($file, 0600);
            rewind($handle);
            $data = json_decode(stream_get_contents($handle) ?: '', true);
            $now = time();
            $start = isset($data['start']) ? (int) $data['start'] : $now;
            $count = isset($data['count']) ? (int) $data['count'] : 0;
            if ($start > $now || $now - $start >= $windowSeconds) {
                $start = $now;
                $count = 0;
            }
            if ($count >= $limit) {
                return max(1, $windowSeconds - ($now - $start));
            }
            $data = json_encode(['start' => $start, 'count' => $count + 1], JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $data) !== strlen($data) || !fflush($handle)) {
                throw new \RuntimeException('Cannot save rate-limit counter.');
            }
            return 0;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function clear(string $scope, string $identifier): void
    {
        $file = self::path($scope, $identifier);
        if (!is_file($file)) {
            return;
        }
        $handle = @fopen($file, 'c+');
        if (!$handle) {
            return;
        }
        try {
            if (flock($handle, LOCK_EX)) {
                rewind($handle);
                ftruncate($handle, 0);
                fflush($handle);
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
