<?php
declare(strict_types=1);

namespace Learmingo\Core;

use PDO;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        Session::start();
        $statement = Database::connection()->prepare('SELECT u.id, u.name, u.email, u.password, u.status, r.slug AS role FROM lm_users u INNER JOIN lm_roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
            return false;
        }

        Session::regenerate();
        unset($user['password']);
        Session::put('auth_user', $user);
        return true;
    }

    public static function user(): ?array
    {
        Session::start();
        $user = Session::get('auth_user');
        return is_array($user) ? $user : null;
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
    }

    public static function role(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['role'] ?? '', $roles, true);
    }
}
