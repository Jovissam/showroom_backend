<?php

namespace App\Middleware;

use App\Core\AppException;
use App\Core\Jwt;
use App\Core\Request;
use App\Repositories\AdminRepository;

// Authenticates CMS admins (LeasePro AdminAuth convention). Tokens are HS256 JWTs with
// aud=jovisco-admin and a token_version claim, so logout, password resets and disabling an
// admin revoke every token already issued. Every /api/admin route except login calls this.
class AdminAuth
{
    public const AUDIENCE = 'jovisco-admin';
    public const TTL = 8 * 3600;

    private static ?array $admin = null;

    public static function admin(Request $request): array
    {
        if (self::$admin !== null) {
            return self::$admin;
        }
        $token = $request->bearerToken();
        if (!$token) {
            throw new AppException('Authentication required', 401);
        }
        $claims = Jwt::decode($token);
        if (!$claims || ($claims['aud'] ?? '') !== self::AUDIENCE || empty($claims['sub'])) {
            throw new AppException('Your session has expired. Please sign in again.', 401);
        }
        $admin = (new AdminRepository())->findById((int) $claims['sub']);
        if (!$admin || $admin['status'] !== 'active' || (int) ($claims['tv'] ?? -1) !== (int) $admin['token_version']) {
            throw new AppException('Your session has ended. Please sign in again.', 401);
        }
        unset($admin['password_hash']);
        return self::$admin = $admin;
    }

    public static function issue(array $admin): string
    {
        return Jwt::encode(['sub' => (int) $admin['id'], 'aud' => self::AUDIENCE, 'tv' => (int) $admin['token_version']], self::TTL);
    }

    // The admin already resolved for this request, if any (used by the logger).
    public static function current(): ?array
    {
        return self::$admin;
    }
}
