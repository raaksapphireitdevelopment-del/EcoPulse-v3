<?php
declare(strict_types=1);

namespace EcoPulse\Core;

/**
 * Authentication service handling password hashing, credentials verification, and session binding
 */
class Auth
{
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function login(array $user, ?int $orgId = null): void
    {
        Session::start();
        Session::regenerate();

        Session::set('auth_user_id', (int)$user['id']);
        Session::set('auth_user_email', $user['email']);
        Session::set('auth_user_role', $user['system_role']);

        if ($orgId !== null) {
            Session::setActiveOrgId($orgId);
        }

        // Update last login timestamp
        $db = Database::getInstance();
        $db->update('users', [
            'last_login_at' => gmdate('Y-m-d H:i:s'),
        ], '`id` = :id', [':id' => $user['id']]);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function id(): ?int
    {
        return Session::getAuthUserId();
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }
}
