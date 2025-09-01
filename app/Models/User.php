<?php
namespace App\Models;

use App\Core\DB;

class User
{
    public static function findByUsername(string $username): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public static function findByEmail(string $email): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $username, string $email, string $passwordHash, string $role='customer', string $fullName=null, string $phoneNumber=null): int
    {
        $allowed = ['admin','shop','customer'];
        if (!in_array($role, $allowed, true)) {
            $role = 'customer';
        }
        $stmt = DB::pdo()->prepare('INSERT INTO users(username, email, password_hash, role, full_name, phone_number) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$username, $email, $passwordHash, $role, $fullName, $phoneNumber]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function saveRefreshToken(int $id_user, string $token): void
    {
        $hash = hash('sha256', $token);
        $stmt = DB::pdo()->prepare('UPDATE users SET refresh_token=? WHERE id_user=?');
        $stmt->execute([$hash,$id_user]);
    }

    public static function findByRefreshToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE refresh_token=? LIMIT 1');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        $stmt = DB::pdo()->query('SELECT id_user, username, email, full_name, role, phone_number, joined_at FROM users');
        return $stmt->fetchAll();
    }
}
