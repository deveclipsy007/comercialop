<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class User
{
    public const TIER_STARTER = 'starter';
    public const TIER_PRO     = 'pro';
    public const TIER_ELITE   = 'elite';

    public static function findById(string $id): ?array
    {
        return Database::selectFirst('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::selectFirst('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function create(array $data): string
    {
        $id = self::generateId();
        Database::execute(
            'INSERT INTO users (id, tenant_id, name, email, password, role, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, datetime("now"))',
            [
                $id,
                $data['tenant_id'],
                $data['name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                $data['role'] ?? 'agent',
            ]
        );
        return $id;
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password']);
    }

    public static function updateProfile(string $id, array $data): bool
    {
        return Database::execute(
            'UPDATE users SET name = ?, email = ?, updated_at = datetime("now") WHERE id = ?',
            [
                $data['name'],
                $data['email'],
                $id
            ]
        ) > 0;
    }

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT id, tenant_id, name, email, password, role, active, created_at FROM users WHERE tenant_id = ?',
            [$tenantId]
        );
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
