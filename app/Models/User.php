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
        
        Database::beginTransaction();
        try {
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

            // Also link the new user in the pivot table automatically
            Database::execute(
                'INSERT INTO tenant_user (id, user_id, tenant_id, role) VALUES (?, ?, ?, ?)',
                [
                    bin2hex(random_bytes(8)),
                    $id,
                    $data['tenant_id'],
                    $data['role'] ?? 'agent'
                ]
            );

            Database::commit();
            return $id;
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
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

    public static function updateWhiteLabel(string $id, array $data): bool
    {
        return Database::execute(
            'UPDATE users SET wl_color = ?, wl_logo = ?, wl_features = ?, wl_allow_setup = ?, updated_at = datetime("now") WHERE id = ?',
            [
                $data['wl_color'] ?? '#a3e635',
                $data['wl_logo'] ?? null,
                $data['wl_features'] ?? null,
                $data['wl_allow_setup'] ?? 0,
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

    public static function getLinkedTenants(string $userId): array
    {
        return Database::select(
            'SELECT t.id, t.name, t.slug, t.settings, tu.role as pivot_role 
             FROM tenants t
             JOIN tenant_user tu ON tu.tenant_id = t.id
             WHERE tu.user_id = ? AND t.active = 1
             ORDER BY t.name ASC',
            [$userId]
        );
    }

    public static function hasTenantAccess(string $userId, string $tenantId): bool
    {
        $link = Database::selectFirst(
            'SELECT id FROM tenant_user WHERE user_id = ? AND tenant_id = ?',
            [$userId, $tenantId]
        );
        return $link !== null;
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
