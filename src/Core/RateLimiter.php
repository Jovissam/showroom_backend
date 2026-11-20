<?php

namespace App\Core;

// Lightweight fixed-window limiter backed by the filesystem. Used to protect public
// payment endpoints and authentication (SPEC §10). Not a substitute for a WAF, but
// enough to blunt brute-force/token-guessing from a single client.
class RateLimiter
{
    private static function dir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/ratelimit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    // Throws 429 when more than $max requests for $key arrive within $windowSeconds.
    public static function hit(string $key, int $max, int $windowSeconds): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $file = self::dir() . '/' . hash('sha256', $key . '|' . $ip) . '.json';
        $now = time();

        $state = ['count' => 0, 'reset' => $now + $windowSeconds];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['reset'] ?? 0) > $now) {
                $state = $decoded;
            }
        }

        $state['count']++;
        file_put_contents($file, json_encode($state), LOCK_EX);

        if ($state['count'] > $max) {
            if ($state['count'] === $max + 1) { // log once per window, not on every blocked request
                Logger::warning('security', 'Rate limit exceeded', ['key' => preg_replace('/:\d+$/', ':*', $key), 'limit' => "$max/{$windowSeconds}s"]);
            }
            throw new AppException('Too many requests. Please slow down and try again shortly.', 429);
        }
    }
}
