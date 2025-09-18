<?php
namespace App\Models;

use App\Core\DB;

class Blog
{
    private const STATUS_DRAFT = 'nhap';
    private const STATUS_PUBLISHED = 'cong_bo';
    private const STATUS_HIDDEN = 'an';

    public static function paginatePublic(string $term, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $params = [];
        $where = "WHERE b.trang_thai = '" . self::STATUS_PUBLISHED . "'";
        if ($term !== '') {
            $where .= ' AND (b.tieu_de LIKE ? OR b.noi_dung LIKE ?)';
            $like = '%'.$term.'%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql = 'SELECT b.*, u.username AS author FROM blog b JOIN users u ON u.id_user=b.id_user '
             . $where . ' ORDER BY b.id_blog DESC LIMIT ? OFFSET ?';
        $stmt = DB::pdo()->prepare($sql);
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++,$param,\PDO::PARAM_STR);
        }
        $stmt->bindValue($i++,$per,\PDO::PARAM_INT);
        $stmt->bindValue($i,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countSql = 'SELECT COUNT(*) FROM blog b ' . $where;
        $countStmt = DB::pdo()->prepare($countSql);
        $countStmt->execute($params);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function search(string $term, int $page=1, int $per=10): array
    {
        return self::paginatePublic($term,$page,$per);
    }

    public static function create(int $userId, string $title, string $content, ?string $status=null): int
    {
        $status = $status ?: self::STATUS_DRAFT;
        $stmt = DB::pdo()->prepare('INSERT INTO blog(id_user,tieu_de,noi_dung,trang_thai) VALUES (?,?,?,?)');
        $stmt->execute([$userId,$title,$content,$status]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function update(int $id, string $title, string $content, ?string $status=null): bool
    {
        $fields = ['tieu_de'=>$title,'noi_dung'=>$content];
        if ($status !== null && $status !== '') {
            $fields['trang_thai'] = $status;
        }
        $sets = [];
        $params = [];
        foreach ($fields as $key=>$value) {
            $sets[] = "$key=?";
            $params[] = $value;
        }
        $params[] = $id;
        $stmt = DB::pdo()->prepare('UPDATE blog SET '.implode(',',$sets).' WHERE id_blog=?');
        return $stmt->execute($params);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = DB::pdo()->prepare('UPDATE blog SET trang_thai=? WHERE id_blog=?');
        return $stmt->execute([$status,$id]);
    }
}