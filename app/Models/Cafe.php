<?php
namespace App\Models;

use App\Core\DB;

class Cafe
{
    public static function paginate(int $page=1, int $per=10, ?int $categoryId=null): array
    {
        $offset = ($page-1)*$per;
        if ($categoryId) {
            $sql = 'SELECT c.* FROM cafes c
                    JOIN cafe_categories cc ON cc.cafe_id=c.id
                    WHERE cc.category_id=?
                    ORDER BY c.id DESC LIMIT ? OFFSET ?';
            $stmt = DB::pdo()->prepare($sql);
            $stmt->bindValue(1,$categoryId,\PDO::PARAM_INT);
            $stmt->bindValue(2,$per,\PDO::PARAM_INT);
            $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = DB::pdo()->prepare('SELECT * FROM cafes ORDER BY id DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1,$per,\PDO::PARAM_INT);
            $stmt->bindValue(2,$offset,\PDO::PARAM_INT);
            $stmt->execute();
        }
        $items = $stmt->fetchAll();
        $count = (int)DB::pdo()->query('SELECT COUNT(*) FROM cafes')->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function search(string $term, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $stmt = DB::pdo()->prepare('SELECT * FROM cafes WHERE name LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
        $like = '%'.$term.'%';
        $stmt->bindValue(1,$like,\PDO::PARAM_STR);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM cafes WHERE name LIKE ?');
        $countStmt->execute([$like]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM cafes WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
