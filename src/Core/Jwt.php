<?php

namespace App\Core;

// Self-contained HS256 JWT encode/decode using the app's JWT_SECRET.
class Jwt
{
    public static function encode(array $claims, int $ttlSeconds = 604800): string
    {
        $now = time();
        $payload = array_merge($claims, ['iat' => $now, 'exp' => $now + $ttlSeconds]);
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload)),
        ];
        $signing = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing, JWT_SECRET, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    // Returns the decoded claims, or null if invalid/expired.
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;

        $expected = self::base64UrlEncode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
        if (!hash_equals($expected, $s)) {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($p), true);
        if (!is_array($claims)) {
            return null;
        }
        if (isset($claims['exp']) && time() >= (int) $claims['exp']) {
            return null;
        }
        return $claims;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
