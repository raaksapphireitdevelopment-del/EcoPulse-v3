<?php
declare(strict_types=1);

namespace EcoPulse\Core;

/**
 * Enterprise Secure Session Manager
 */
class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        if (!headers_sent()) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

            $cookieParams = [
                'lifetime' => 7200, // 2 hours
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ];

            session_set_cookie_params($cookieParams);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_name('ecopulse_v3_sess');
        }

        if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
            session_start();
        } elseif (session_status() === PHP_SESSION_NONE && php_sapi_name() === 'cli') {
            @session_start();
        }
        self::$started = true;

        // Auto initialize CSRF token if missing
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Auto expire stale sessions
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
            self::destroy();
            session_start();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $_SESSION['last_activity'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
            self::$started = false;
        }
    }

    public static function getCsrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        $validToken = self::getCsrfToken();
        return hash_equals($validToken, $token);
    }

    public static function getAuthUserId(): ?int
    {
        $id = self::get('auth_user_id');
        return $id !== null ? (int)$id : null;
    }

    public static function getActiveOrgId(): ?int
    {
        $id = self::get('active_org_id');
        return $id !== null ? (int)$id : null;
    }

    public static function setActiveOrgId(int $orgId): void
    {
        self::set('active_org_id', $orgId);
    }
}
