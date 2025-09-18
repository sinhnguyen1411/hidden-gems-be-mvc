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

    public static function findByPhoneNumber(string $phoneNumber): ?array
    {
        if ($phoneNumber === "") {
            return null;
        }
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE phone_number = ? LIMIT 1');
        $stmt->execute([$phoneNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE id_user = ? LIMIT 1');
        $stmt->execute([$id]);
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

    public static function deleteById(int $id): bool
    {
        $stmt = DB::pdo()->prepare('DELETE FROM users WHERE id_user=?');
        return $stmt->execute([$id]);
    }

    public static function search(array $filters, int $page=1, int $per=20): array
    {
        $page = max(1,$page);
        $per = max(1,$per);
        $offset = ($page-1)*$per;
        $where = [];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR phone_number LIKE ?)';
            $like = '%'.$filters['q'].'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['role'])) {
            $where[] = 'role = ?';
            $params[] = $filters['role'];
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM users ' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $sql = 'SELECT id_user, username, email, full_name, role, phone_number, joined_at FROM users ' . $whereSql . ' ORDER BY id_user DESC LIMIT ? OFFSET ?';
        $stmt = DB::pdo()->prepare($sql);
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++,$param,\PDO::PARAM_STR);
        }
        $stmt->bindValue($i++,$per,\PDO::PARAM_INT);
        $stmt->bindValue($i,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$per];
    }
}
