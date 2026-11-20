<?php

namespace App\Core;

// Consistent JSON envelope: { success, message, data, errors? } (same convention as LeasePro).
class Response
{
    public static function json(bool $success, string $message, $data = null, int $status = 200, array $errors = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        $payload = ['success' => $success, 'message' => $message, 'data' => $data];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(string $message, $data = null, int $status = 200): void
    {
        self::json(true, $message, $data, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        self::json(false, $message, null, $status, $errors);
    }
}
