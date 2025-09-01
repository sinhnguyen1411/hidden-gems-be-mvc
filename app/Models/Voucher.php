<?php
namespace App\Models;

use App\Core\DB;

class Voucher
{
    public static function search(string $term, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $like = '%'.$term.'%';
        $stmt = DB::pdo()->prepare('SELECT * FROM voucher WHERE ma_voucher LIKE ? OR ten_voucher LIKE ? ORDER BY id_voucher DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$like,\PDO::PARAM_STR);
        $stmt->bindValue(2,$like,\PDO::PARAM_STR);
        $stmt->bindValue(3,$per,\PDO::PARAM_INT);
        $stmt->bindValue(4,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM voucher WHERE ma_voucher LIKE ? OR ten_voucher LIKE ?');
        $countStmt->execute([$like,$like]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function create(string $code, string $name, float $value, string $type, ?string $expiresAt, int $quantity): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO voucher(ma_voucher,ten_voucher,gia_tri_giam,loai_giam_gia,ngay_het_han,so_luong_con_lai) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$code,$name,$value,$type,$expiresAt,$quantity]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function assignToStore(int $voucherId, int $storeId): bool
    {
        $stmt = DB::pdo()->prepare('INSERT IGNORE INTO voucher_cua_hang(id_voucher,id_cua_hang) VALUES (?,?)');
        return $stmt->execute([$voucherId,$storeId]);
    }

    public static function listByStore(int $storeId): array
    {
        $stmt = DB::pdo()->prepare('SELECT v.* FROM voucher v JOIN voucher_cua_hang vs ON vs.id_voucher=v.id_voucher WHERE vs.id_cua_hang=? ORDER BY v.id_voucher DESC');
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }
}

