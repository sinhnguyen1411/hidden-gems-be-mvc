<?php
namespace App\Models;

use App\Core\DB;

class Review
{
    public static function listByCafe(int $cafeId, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $stmt = DB::pdo()->prepare('SELECT r.*, u.username as user_name FROM danh_gia r JOIN users u ON u.id_user=r.id_user WHERE r.id_cua_hang=? ORDER BY r.id_danh_gia DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$cafeId,\PDO::PARAM_INT);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM danh_gia WHERE id_cua_hang=?');
        $countStmt->execute([$cafeId]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function create(int $userId, int $cafeId, int $rating, string $content): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO danh_gia(id_user,id_cua_hang,diem_danh_gia,binh_luan) VALUES(?,?,?,?)');
        $stmt->execute([$userId,$cafeId,$rating,$content]);
        return (int)DB::pdo()->lastInsertId();
    }
}
