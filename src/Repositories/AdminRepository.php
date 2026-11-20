<?php

namespace App\Repositories;

class AdminRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        return $this->run('SELECT * FROM admins WHERE id = ?', [$id])->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->run('SELECT * FROM admins WHERE email = ?', [$email])->fetch() ?: null;
    }

    public function touchLogin(int $id): void
    {
        $this->run('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public function bumpTokenVersion(int $id): void
    {
        $this->run('UPDATE admins SET token_version = token_version + 1 WHERE id = ?', [$id]);
    }
}
