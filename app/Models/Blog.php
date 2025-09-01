<?php
namespace App\Models;

use App\Core\DB;

class Blog
{
    public static function search(string $term, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $like = '%'.$term.'%';
        $stmt = DB::pdo()->prepare('SELECT b.*, u.username AS author FROM blog b JOIN users u ON u.id_user=b.id_user WHERE b.tieu_de LIKE ? OR b.noi_dung LIKE ? ORDER BY b.id_blog DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$like,\PDO::PARAM_STR);
        $stmt->bindValue(2,$like,\PDO::PARAM_STR);
        $stmt->bindValue(3,$per,\PDO::PARAM_INT);
        $stmt->bindValue(4,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM blog WHERE tieu_de LIKE ? OR noi_dung LIKE ?');
        $countStmt->execute([$like,$like]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function create(int $userId, string $title, string $content): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO blog(id_user,tieu_de,noi_dung) VALUES (?,?,?)');
        $stmt->execute([$userId,$title,$content]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function update(int $id, string $title, string $content): bool
    {
        $stmt = DB::pdo()->prepare('UPDATE blog SET tieu_de=?, noi_dung=? WHERE id_blog=?');
        return $stmt->execute([$title,$content,$id]);
    }
}

