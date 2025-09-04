<?php
namespace App\Models;

use App\Core\DB;

class AdRequest
{
    public static function create(int $storeId, string $package, string $startDateTime, string $endDateTime, float $price, ?int $chargeTxId=null): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO yeu_cau_quang_cao(id_cua_hang, goi, ngay_bat_dau, ngay_ket_thuc, gia, trang_thai, id_giao_dich_tru) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$storeId, $package, $startDateTime, $endDateTime, $price, 'cho_duyet', $chargeTxId]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM yeu_cau_quang_cao WHERE id_yeu_cau=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByOwner(int $ownerId, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $sql = 'SELECT a.* FROM yeu_cau_quang_cao a JOIN cua_hang c ON c.id_cua_hang=a.id_cua_hang WHERE c.id_chu_so_huu=? ORDER BY a.id_yeu_cau DESC LIMIT ? OFFSET ?';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(1,$ownerId,\PDO::PARAM_INT);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $cnt = DB::pdo()->prepare('SELECT COUNT(*) FROM yeu_cau_quang_cao a JOIN cua_hang c ON c.id_cua_hang=a.id_cua_hang WHERE c.id_chu_so_huu=?');
        $cnt->execute([$ownerId]);
        $total = (int)$cnt->fetchColumn();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$per];
    }

    public static function listPending(int $page=1, int $per=20): array
    {
        $offset = ($page-1)*$per;
        $stmt = DB::pdo()->prepare("SELECT a.*, c.ten_cua_hang FROM yeu_cau_quang_cao a JOIN cua_hang c ON c.id_cua_hang=a.id_cua_hang WHERE a.trang_thai='cho_duyet' ORDER BY a.ngay_bat_dau ASC, a.id_yeu_cau DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1,$per,\PDO::PARAM_INT);
        $stmt->bindValue(2,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $count = (int)DB::pdo()->query("SELECT COUNT(*) FROM yeu_cau_quang_cao WHERE trang_thai='cho_duyet'")->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function review(int $id, string $status, ?int $adminId): bool
    {
        $stmt = DB::pdo()->prepare('UPDATE yeu_cau_quang_cao SET trang_thai=?, id_admin_duyet=?, ngay_duyet=CURRENT_TIMESTAMP WHERE id_yeu_cau=?');
        return $stmt->execute([$status, $adminId, $id]);
    }

    public static function listActiveAt(string $targetDateTime): array
    {
        $stmt = DB::pdo()->prepare('SELECT a.*, c.ten_cua_hang FROM yeu_cau_quang_cao a JOIN cua_hang c ON c.id_cua_hang=a.id_cua_hang WHERE a.trang_thai=\'da_duyet\' AND a.ngay_bat_dau <= ? AND a.ngay_ket_thuc >= ? ORDER BY a.ngay_bat_dau ASC');
        $stmt->execute([$targetDateTime, $targetDateTime]);
        return $stmt->fetchAll();
    }
}

