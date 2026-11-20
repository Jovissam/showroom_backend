<?php

namespace App\Core;

// Minimal .env loader (no Composer dependency). Supports:
//   KEY=value          # comment
//   export KEY=value
//   KEY="quoted value with spaces and \n escapes"
//   KEY='literal value'
//   KEY=               (empty string)
// Real environment variables (set by the web server or the shell) always win over the file,
// so production can configure everything without a .env file at all.
final class Env
{
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $n => $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                error_log("[jovisco] .env line " . ($n + 1) . ' ignored: no "="');
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                error_log("[jovisco] .env line " . ($n + 1) . " ignored: invalid name");
                continue;
            }
            self::$values[$key] = self::parseValue(trim(substr($line, $eq + 1)));
        }
    }

    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        $q = $raw[0];
        if (($q === '"' || $q === "'") && ($end = strrpos($raw, $q)) > 0) {
            $inner = substr($raw, 1, $end - 1);
            return $q === '"' ? stripcslashes($inner) : $inner;
        }
        // Unquoted: strip an inline comment (" #…").
        $hash = strpos($raw, ' #');
        return trim($hash === false ? $raw : substr($raw, 0, $hash));
    }

    /** The real environment first, then .env, then the default. */
    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false) {
            return $real;
        }
        return self::$values[$key] ?? $default;
    }

    /** A value that must be set (and non-empty); stops with a clear error otherwise. */
    public static function required(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required setting $key (set it in .env or the server environment)");
        }
        return $v;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return $v === null || $v === '' || filter_var($v, FILTER_VALIDATE_INT) === false ? $default : (int) $v;
    }

    /** Comma-separated list, trimmed, empty items dropped. */
    public static function list(string $key, array $default = []): array
    {
        $v = self::get($key);
        return $v === null ? $default : array_values(array_filter(array_map('trim', explode(',', $v)), 'strlen'));
    }
}
