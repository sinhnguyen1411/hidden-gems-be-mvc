<?php
namespace App\Models;

use App\Core\DB;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $email, string $passwordHash, string $role='customer'): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');
        $stmt->execute([$name,$email,$passwordHash,$role]);
        return (int)DB::pdo()->lastInsertId();
    }
}
