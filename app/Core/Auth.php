<?php
declare(strict_types=1);

namespace Learmingo\Core;

use Throwable;

final class Auth
{
    private const DUMMY_HASH = '$2y$12$0TR31UTdxQl9L2aq4j0u8OYdokuV2phUHvWHdszUt0p5Di4KlQeKm';

    public static function attempt(string $email, string $password): bool
    {
        Session::start();
        $statement = Database::connection()->prepare(
            'SELECT u.id, u.name, u.email, u.password, u.status, r.slug AS role
             FROM lm_users u INNER JOIN lm_roles r ON r.id=u.role_id WHERE u.email=:email LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();
        $hash = is_array($user) && is_string($user['password'] ?? null)
            ? $user['password'] : self::DUMMY_HASH;
        $verified = password_verify($password, $hash);
        if (!is_array($user) || $user['status'] !== 'active' || !$verified) {
            return false;
        }
        Session::regenerate();
        Csrf::rotate();
        unset($user['password']);
        Session::put('auth_user', $user);
        return true;
    }

    public static function user(): ?array
    {
        Session::start();
        $current = Session::get('auth_user');
        if (!is_array($current) || !isset($current['id']) || !is_numeric($current['id'])) {
            return null;
        }
        try {
            // Refresh the authoritative role/status on every request: deactivated or
            // demoted accounts lose access immediately, not at cookie expiration.
            $statement = Database::connection()->prepare(
                'SELECT u.id,u.name,u.email,u.status,r.slug AS role
                 FROM lm_users u INNER JOIN lm_roles r ON r.id=u.role_id WHERE u.id=:id LIMIT 1'
            );
            $statement->execute(['id' => (int) $current['id']]);
            $user = $statement->fetch();
        } catch (Throwable $exception) {
            error_log('Learmingo auth revalidation unavailable: ' . get_class($exception));
            Session::forget('auth_user');
            return null;
        }
        if (!is_array($user) || $user['status'] !== 'active') {
            Session::forget('auth_user');
            Csrf::rotate();
            return null;
        }
        Session::put('auth_user', $user);
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        Session::start();
        Session::forget('auth_user');
        Session::regenerate();
        Csrf::rotate();
    }

    public static function role(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['role'] ?? '', $roles, true);
    }
}
