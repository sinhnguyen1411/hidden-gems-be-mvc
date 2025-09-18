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

    public static function create(string $code, string $name, float $value, string $type, ?string $expiresAt, int $quantity, bool $isGlobal=false): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO voucher(ma_voucher,ten_voucher,gia_tri_giam,loai_giam_gia,ngay_het_han,so_luong_con_lai,is_global) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$code,$name,$value,$type,$expiresAt,$quantity,$isGlobal?1:0]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function assignToStore(int $voucherId, int $storeId): bool
    {
        $stmt = DB::pdo()->prepare('INSERT IGNORE INTO voucher_cua_hang(id_voucher,id_cua_hang) VALUES (?,?)');
        return $stmt->execute([$voucherId,$storeId]);
    }

    public static function listByStore(int $storeId): array
    {
        $sql = 'SELECT DISTINCT v.*,
                CASE WHEN vs.id_cua_hang IS NULL THEN 1 ELSE 0 END AS applies_globally
                FROM voucher v
                LEFT JOIN voucher_cua_hang vs ON vs.id_voucher=v.id_voucher AND vs.id_cua_hang=?
                WHERE v.is_global=1 OR vs.id_cua_hang IS NOT NULL
                ORDER BY v.id_voucher DESC';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public static function listGlobal(): array
    {
        $stmt = DB::pdo()->query('SELECT * FROM voucher WHERE is_global=1 ORDER BY id_voucher DESC');
        return $stmt->fetchAll();
    }

    public static function isAssignableToStore(int $voucherId): bool
    {
        $stmt = DB::pdo()->prepare('SELECT is_global FROM voucher WHERE id_voucher=?');
        $stmt->execute([$voucherId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return (int)$row['is_global'] === 0;
    }
}