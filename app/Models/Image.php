<?php
namespace App\Models;

use App\Core\DB;

class Image
{
    public static function addForStore(int $storeId, ?int $userId, string $url, bool $isAvatar=false): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO hinh_anh(id_cua_hang,id_user,url_anh,is_anh_dai_dien) VALUES (?,?,?,?)');
        $stmt->execute([$storeId,$userId,$url,$isAvatar?1:0]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function listForStore(int $storeId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM hinh_anh WHERE id_cua_hang=? ORDER BY is_anh_dai_dien DESC, id_anh DESC');
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }
}

