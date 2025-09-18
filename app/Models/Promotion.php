<?php
namespace App\Models;

use App\Core\DB;

class Promotion
{
    public static function search(string $term, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $like = '%'.$term.'%';
        $stmt = DB::pdo()->prepare('SELECT * FROM khuyen_mai WHERE ten_chuong_trinh LIKE ? OR mo_ta LIKE ? ORDER BY id_khuyen_mai DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$like,\PDO::PARAM_STR);
        $stmt->bindValue(2,$like,\PDO::PARAM_STR);
        $stmt->bindValue(3,$per,\PDO::PARAM_INT);
        $stmt->bindValue(4,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM khuyen_mai WHERE ten_chuong_trinh LIKE ? OR mo_ta LIKE ?');
        $countStmt->execute([$like,$like]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function create(array $payload): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO khuyen_mai(ten_chuong_trinh,mo_ta,ngay_bat_dau,ngay_ket_thuc,loai_ap_dung,pham_vi_ap_dung,trang_thai) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $payload['ten_chuong_trinh'],
            $payload['mo_ta'] ?? null,
            $payload['ngay_bat_dau'],
            $payload['ngay_ket_thuc'],
            $payload['loai_ap_dung'] ?? null,
            $payload['pham_vi_ap_dung'] ?? 'gioi_han',
            $payload['trang_thai'] ?? 'dang_hoat_dong'
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function applyStore(int $promoId, int $storeId): bool
    {
        $scopeStmt = DB::pdo()->prepare('SELECT pham_vi_ap_dung FROM khuyen_mai WHERE id_khuyen_mai=?');
        $scopeStmt->execute([$promoId]);
        $scope = $scopeStmt->fetchColumn();
        if ($scope === 'toan_he_thong') {
            return false;
        }
        $stmt = DB::pdo()->prepare('INSERT INTO khuyen_mai_cua_hang(id_khuyen_mai,id_cua_hang,trang_thai) VALUES (?,?,?)');
        return $stmt->execute([$promoId,$storeId,'cho_duyet']);
    }

    public static function reviewApplication(int $promoId, int $storeId, string $status, ?int $approverId=null): bool
    {
        $stmt = DB::pdo()->prepare('UPDATE khuyen_mai_cua_hang SET trang_thai=?, ngay_duyet=CURRENT_TIMESTAMP, id_nguoi_duyet=? WHERE id_khuyen_mai=? AND id_cua_hang=?');
        return $stmt->execute([$status,$approverId,$promoId,$storeId]);
    }

    public static function listByStore(int $storeId): array
    {
        $sql = 'SELECT k.*, ks.trang_thai FROM khuyen_mai k
                LEFT JOIN khuyen_mai_cua_hang ks ON ks.id_khuyen_mai=k.id_khuyen_mai AND ks.id_cua_hang=?
                WHERE k.pham_vi_ap_dung = \'toan_he_thong\' OR ks.id_cua_hang IS NOT NULL
                ORDER BY k.id_khuyen_mai DESC';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public static function listGlobal(string $status='dang_hoat_dong'): array
    {
        $sql = 'SELECT * FROM khuyen_mai WHERE pham_vi_ap_dung=\'toan_he_thong\'';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND trang_thai = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY ngay_bat_dau DESC';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function searchAdmin(string $term='', ?string $status=null, int $page=1, int $per=20): array
    {
        $offset = ($page-1)*$per;
        $where = [];
        $params = [];
        if ($term !== '') {
            $where[] = '(ten_chuong_trinh LIKE ? OR mo_ta LIKE ?)';
            $like = '%'.$term.'%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'trang_thai = ?';
            $params[] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM khuyen_mai ' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $sql = 'SELECT * FROM khuyen_mai ' . $whereSql . ' ORDER BY id_khuyen_mai DESC LIMIT ? OFFSET ?';
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