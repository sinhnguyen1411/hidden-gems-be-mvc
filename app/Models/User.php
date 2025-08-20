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

    public static function create(string $ten_dang_nhap, string $email, string $mat_khau_ma_hoa, string $vai_tro='user', string $ho_va_ten=null, string $so_dien_thoai=null): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO users(ten_dang_nhap, email, mat_khau_ma_hoa, vai_tro, ho_va_ten, so_dien_thoai) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$ten_dang_nhap, $email, $mat_khau_ma_hoa, $vai_tro, $ho_va_ten, $so_dien_thoai]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function saveRefreshToken(int $id_user, string $token): void
    {
        $stmt = DB::pdo()->prepare('UPDATE users SET refresh_token=? WHERE id_user=?');
        $stmt->execute([$token,$id_user]);
    }

    public static function findByRefreshToken(string $token): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE refresh_token=? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        $stmt = DB::pdo()->query('SELECT id_user, ten_dang_nhap, email, ho_va_ten, vai_tro, so_dien_thoai, ngay_tham_gia FROM users');
        return $stmt->fetchAll();
    }
}
