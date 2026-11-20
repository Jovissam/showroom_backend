<?php

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\AdminAuth;
use App\Repositories\AdminRepository;

// CMS admin sign-in. Admins are created with `php bin/admin.php create`; there is no sign-up.
class AdminAuthController
{
    // A real bcrypt hash of a random string, so unknown emails cost the same time as known ones.
    private const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    // POST /api/admin/auth/login
    public function login(Request $request, array $params): void
    {
        RateLimiter::hit('admin:login', 10, 900);
        $d = Validator::make($request->body(), ['email' => 'required|email|max:190', 'password' => 'required|string|max:200']);
        $repo = new AdminRepository();
        $admin = $repo->findByEmail($d['email']);
        $ok = password_verify((string) $d['password'], $admin['password_hash'] ?? self::DUMMY_HASH);
        if (!$admin || !$ok || $admin['status'] !== 'active') {
            Logger::warning('auth', 'Failed admin sign-in');
            throw new AppException('Email or password is incorrect', 401);
        }
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            getDB()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash((string) $d['password'], PASSWORD_DEFAULT), $admin['id']]);
        }
        $repo->touchLogin((int) $admin['id']);
        Response::success('Signed in', [
            'token' => AdminAuth::issue($admin),
            'expires_in' => AdminAuth::TTL,
            'admin' => self::present($admin),
        ]);
    }

    // GET /api/admin/auth/me
    public function me(Request $request, array $params): void
    {
        Response::success('Signed in', ['admin' => self::present(AdminAuth::admin($request))]);
    }

    // POST /api/admin/auth/logout: ends every session of this admin.
    public function logout(Request $request, array $params): void
    {
        $admin = AdminAuth::admin($request);
        (new AdminRepository())->bumpTokenVersion((int) $admin['id']);
        Response::success('Signed out');
    }

    private static function present(array $a): array
    {
        return ['id' => (int) $a['id'], 'name' => $a['name'], 'email' => $a['email'], 'last_login_at' => $a['last_login_at']];
    }
}
