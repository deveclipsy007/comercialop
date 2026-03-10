<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('operon_session');
            session_set_cookie_params([
                'lifetime' => 86400 * 7, // 7 dias
                'path'     => '/',
                'secure'   => env('APP_ENV') === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION[$key]) && isset($_SESSION['auth_user'][$key])) {
            return $_SESSION['auth_user'][$key];
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        self::flush();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Flash: dados disponíveis apenas na próxima requisição.
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Usuário autenticado.
     */
    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['auth_user']);
    }

    /**
     * Exige autenticação. Redireciona para /login se não autenticado.
     */
    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            self::flash('error', 'Você precisa estar logado para acessar esta página.');
            header('Location: /login');
            exit;
        }
    }

    /**
     * Retorna o tenant_id do usuário autenticado.
     */
    public static function tenantId(): ?string
    {
        return $_SESSION['auth_user']['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = $user;
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['role']      = $user['role'] ?? 'agent';
        $_SESSION['name']      = $user['name'] ?? 'Usuário';
    }

    public static function logout(): void
    {
        self::destroy();
    }

    /**
     * CSRF token.
     */
    public static function csrf(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validateCsrf(string $token): bool
    {
        return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }
}
