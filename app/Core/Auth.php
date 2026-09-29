<?php
namespace App\Core;

class Auth
{
    public static function login(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
    }

    public static function id(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function user()
    {
        $id = self::id();
        if ($id <= 0) {
            return null;
        }
        $user = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [$id]);
        if (!$user || (int) $user['status'] !== 1) {
            self::logout();
            return null;
        }
        return $user;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'admin';
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }
}
