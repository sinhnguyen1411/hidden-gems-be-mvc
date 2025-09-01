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
        $stmt = DB::pdo()->prepare('SELECT k.*, ks.trang_thai FROM khuyen_mai k JOIN khuyen_mai_cua_hang ks ON ks.id_khuyen_mai=k.id_khuyen_mai WHERE ks.id_cua_hang=? ORDER BY k.id_khuyen_mai DESC');
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }
}

