<?php
namespace App\Models;

use App\Core\DB;

class Cafe
{
    public static function paginate(int $page=1, int $per=10, ?int $categoryId=null): array
    {
        $offset = ($page-1)*$per;
        if ($categoryId) {
            $sql = 'SELECT c.* FROM cua_hang c
                    JOIN cua_hang_chuyen_muc cc ON cc.id_cua_hang=c.id_cua_hang
                    WHERE cc.id_chuyen_muc=?
                    ORDER BY c.id_cua_hang DESC LIMIT ? OFFSET ?';
            $stmt = DB::pdo()->prepare($sql);
            $stmt->bindValue(1,$categoryId,\PDO::PARAM_INT);
            $stmt->bindValue(2,$per,\PDO::PARAM_INT);
            $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();
            $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM cua_hang c JOIN cua_hang_chuyen_muc cc ON cc.id_cua_hang=c.id_cua_hang WHERE cc.id_chuyen_muc=?');
            $countStmt->execute([$categoryId]);
            $count = (int)$countStmt->fetchColumn();
        } else {
            $stmt = DB::pdo()->prepare('SELECT * FROM cua_hang ORDER BY id_cua_hang DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1,$per,\PDO::PARAM_INT);
            $stmt->bindValue(2,$offset,\PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();
            $count = (int)DB::pdo()->query('SELECT COUNT(*) FROM cua_hang')->fetchColumn();
        }
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function search(string $term, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $stmt = DB::pdo()->prepare('SELECT * FROM cua_hang WHERE ten_cua_hang LIKE ? ORDER BY id_cua_hang DESC LIMIT ? OFFSET ?');
        $like = '%'.$term.'%';
        $stmt->bindValue(1,$like,\PDO::PARAM_STR);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM cua_hang WHERE ten_cua_hang LIKE ?');
        $countStmt->execute([$like]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM cua_hang WHERE id_cua_hang=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $ownerId, string $name, ?string $desc=null, ?int $statusId=null, ?int $locationId=null, ?int $parentId=null): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO cua_hang(id_chu_so_huu,ten_cua_hang,mo_ta,id_trang_thai,id_vi_tri,id_cua_hang_cha) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$ownerId,$name,$desc,$statusId,$locationId,$parentId]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function updateStore(int $id, array $fields): bool
    {
        $allowed = ['ten_cua_hang','mo_ta','id_trang_thai','id_vi_tri','id_cua_hang_cha'];
        $sets = [];$vals=[];
        foreach ($fields as $k=>$v) {
            if (in_array($k,$allowed,true)) { $sets[] = "$k=?"; $vals[]=$v; }
        }
        if (!$sets) return false;
        $vals[] = $id;
        $stmt = DB::pdo()->prepare('UPDATE cua_hang SET '.implode(',',$sets).' WHERE id_cua_hang=?');
        return $stmt->execute($vals);
    }

    public static function listOwned(int $ownerId, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $stmt = DB::pdo()->prepare('SELECT * FROM cua_hang WHERE id_chu_so_huu=? ORDER BY id_cua_hang DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$ownerId,\PDO::PARAM_INT);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM cua_hang WHERE id_chu_so_huu=?');
        $countStmt->execute([$ownerId]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }
}
