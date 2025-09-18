<?php
namespace App\Models;

use App\Core\DB;

class Comment
{
    public static function paginateAdmin(array $filters, int $page=1, int $per=20): array
    {
        $page = max(1,$page);
        $per = max(1,$per);
        $offset = ($page-1)*$per;
        $where = [];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = "(c.noi_dung LIKE ? OR u.username LIKE ? OR COALESCE(s.ten_cua_hang, b.tieu_de, '') LIKE ?)";
            $like = '%'.$filters['q'].'%';
            $params = array_merge($params, [$like,$like,$like]);
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.trang_thai = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'c.loai_doi_tuong = ?';
            $params[] = $filters['type'];
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $base = "FROM binh_luan c
                JOIN users u ON u.id_user = c.id_user
                LEFT JOIN cua_hang s ON s.id_cua_hang = c.id_tham_chieu AND c.loai_doi_tuong = 'store'
                LEFT JOIN blog b ON b.id_blog = c.id_tham_chieu AND c.loai_doi_tuong = 'blog'";
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) '.$base.' '.$whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $sql = 'SELECT c.*, u.username, s.ten_cua_hang, b.tieu_de '.$base.' '.$whereSql.' ORDER BY c.id_binh_luan DESC LIMIT ? OFFSET ?';
        $stmt = DB::pdo()->prepare($sql);
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++,$param,\PDO::PARAM_STR);
        }
        $stmt->bindValue($i++,$per,\PDO::PARAM_INT);
        $stmt->bindValue($i,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = array_map(function(array $row){
            $row['id_binh_luan'] = (int)$row['id_binh_luan'];
            $row['id_user'] = (int)$row['id_user'];
            $row['id_tham_chieu'] = (int)$row['id_tham_chieu'];
            if ($row['loai_doi_tuong'] === 'store') {
                $row['target_name'] = $row['ten_cua_hang'] ?? null;
            } elseif ($row['loai_doi_tuong'] === 'blog') {
                $row['target_name'] = $row['tieu_de'] ?? null;
            } else {
                $row['target_name'] = null;
            }
            return $row;
        }, $stmt->fetchAll());
        return ['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$per];
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = DB::pdo()->prepare('UPDATE binh_luan SET trang_thai=? WHERE id_binh_luan=?');
        return $stmt->execute([$status,$id]);
    }
}