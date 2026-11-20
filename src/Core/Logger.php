<?php

namespace App\Core;

// Writes application logs to `app_logs` (LeasePro convention). Context is recursively redacted
// so passwords and tokens never reach storage. Logging must never break the request: on DB
// failure it falls back to PHP's error_log.
class Logger
{
    private const SENSITIVE = '/pass(word)?|secret|token|authorization|api[_-]?key|private|credential|hash|cookie|signature/i';

    public static function info(string $channel, string $message, array $context = []): void     { self::log('info', $channel, $message, $context); }
    public static function warning(string $channel, string $message, array $context = []): void  { self::log('warning', $channel, $message, $context); }
    public static function error(string $channel, string $message, array $context = []): void    { self::log('error', $channel, $message, $context); }
    public static function critical(string $channel, string $message, array $context = []): void { self::log('critical', $channel, $message, $context); }

    private static bool $writing = false;

    public static function log(string $level, string $channel, string $message, array $context = []): void
    {
        if (self::$writing) {
            error_log("[jovisco][$level][$channel] $message");
            return;
        }
        self::$writing = true;
        try {
            $admin = \App\Middleware\AdminAuth::current();
            $clean = self::redact($context);
            getDB()->prepare('INSERT INTO app_logs (level, channel, message, context, admin_id, ip) VALUES (?, ?, ?, ?, ?, ?)')->execute([
                $level, mb_substr($channel, 0, 40), mb_substr($message, 0, 500),
                $clean ? json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                $admin['id'] ?? null, self::ip(),
            ]);
        } catch (\Throwable $e) {
            error_log("[jovisco][$level][$channel] $message");
        } finally {
            self::$writing = false;
        }
    }

    public static function redact(array $data, int $depth = 0): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE, $key)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = $depth < 4 ? self::redact($value, $depth + 1) : '[truncated]';
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
            } else {
                $out[$key] = '[' . gettype($value) . ']';
            }
        }
        return $out;
    }

    public static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return $ip ? mb_substr($ip, 0, 45) : null;
    }
}
