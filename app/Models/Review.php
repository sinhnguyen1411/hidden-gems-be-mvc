<?php
namespace App\Models;

use App\Core\DB;

class Review
{
    public static function listByCafe(int $cafeId, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $stmt = DB::pdo()->prepare('SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE cafe_id=? ORDER BY r.id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$cafeId,\PDO::PARAM_INT);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM reviews WHERE cafe_id=?');
        $countStmt->execute([$cafeId]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function create(int $userId, int $cafeId, int $rating, string $content): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO reviews(user_id,cafe_id,rating,content) VALUES(?,?,?,?)');
        $stmt->execute([$userId,$cafeId,$rating,$content]);
        return (int)DB::pdo()->lastInsertId();
    }
}
