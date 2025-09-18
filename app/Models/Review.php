<?php
namespace App\Models;

use App\Core\DB;

class Review
{
    private const APPROVED_STATUSES = ['da_duyet','approved','hien_thi'];

    public static function listByCafe(int $cafeId, int $page=1, int $per=10): array
    {
        $offset = ($page-1)*$per;
        $sql = 'SELECT r.*, u.username as user_name FROM danh_gia r JOIN users u ON u.id_user=r.id_user
                WHERE r.id_cua_hang=? AND (r.trang_thai IS NULL OR r.trang_thai IN (\'da_duyet\',\'approved\',\'hien_thi\'))
                ORDER BY r.id_danh_gia DESC LIMIT ? OFFSET ?';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(1,$cafeId,\PDO::PARAM_INT);
        $stmt->bindValue(2,$per,\PDO::PARAM_INT);
        $stmt->bindValue(3,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM danh_gia WHERE id_cua_hang=? AND (trang_thai IS NULL OR trang_thai IN (\'da_duyet\',\'approved\',\'hien_thi\'))');
        $countStmt->execute([$cafeId]);
        $count = (int)$countStmt->fetchColumn();
        return ['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per];
    }

    public static function create(int $userId, int $cafeId, int $rating, string $content): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO danh_gia(id_user,id_cua_hang,diem_danh_gia,binh_luan,trang_thai) VALUES(?,?,?,?,?)');
        $stmt->execute([$userId,$cafeId,$rating,$content,'cho_duyet']);
        self::recalculateAverage($cafeId);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function updateStatus(int $reviewId, string $status, ?int $moderatorId=null): bool
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id_cua_hang FROM danh_gia WHERE id_danh_gia=? FOR UPDATE');
        $stmt->execute([$reviewId]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return false;
        }
        $update = $pdo->prepare('UPDATE danh_gia SET trang_thai=? WHERE id_danh_gia=?');
        $ok = $update->execute([$status,$reviewId]);
        if (!$ok) {
            $pdo->rollBack();
            return false;
        }
        self::recalculateAverage((int)$row['id_cua_hang']);
        if ($moderatorId) {
            $meta = json_encode([
                'review_id' => $reviewId,
                'status' => $status,
            ], JSON_UNESCAPED_UNICODE);
            $log = $pdo->prepare('INSERT INTO audit_log(actor_user_id, action, target_type, target_id, meta) VALUES (?,?,?,?,?)');
            $log->execute([$moderatorId,'review_status','review',$reviewId,$meta]);
        }
        $pdo->commit();
        return true;
    }

    public static function searchForAdmin(string $term='', ?string $status=null, int $page=1, int $per=20): array
    {
        $offset = ($page-1)*$per;
        $where = [];
        $params = [];
        if ($term !== '') {
            $where[] = '(u.username LIKE ? OR c.ten_cua_hang LIKE ? OR r.binh_luan LIKE ?)';
            $like = '%'.$term.'%';
            $params = array_merge($params, [$like,$like,$like]);
        }
        if ($status !== null && $status !== '') {
            $where[] = 'r.trang_thai = ?';
            $params[] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ',$where)) : '';
        $base = 'FROM danh_gia r JOIN users u ON u.id_user=r.id_user JOIN cua_hang c ON c.id_cua_hang=r.id_cua_hang ';
        $countStmt = DB::pdo()->prepare('SELECT COUNT(*) '.$base.$whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $sql = 'SELECT r.*, u.username, c.ten_cua_hang '.$base.$whereSql.' ORDER BY r.id_danh_gia DESC LIMIT ? OFFSET ?';
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

    private static function recalculateAverage(int $cafeId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT AVG(diem_danh_gia) FROM danh_gia WHERE id_cua_hang=? AND (trang_thai IS NULL OR trang_thai IN (\'da_duyet\',\'approved\',\'hien_thi\'))');
        $stmt->execute([$cafeId]);
        $avg = (float)$stmt->fetchColumn();
        $avg = $avg > 0 ? round($avg, 1) : 0.0;
        $update = $pdo->prepare('UPDATE cua_hang SET diem_danh_gia_trung_binh=? WHERE id_cua_hang=?');
        $update->execute([$avg,$cafeId]);
    }
}