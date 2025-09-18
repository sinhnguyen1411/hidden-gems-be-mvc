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
        $sets = [];
        $vals = [];
        foreach ($fields as $k=>$v) {
            if (in_array($k,$allowed,true)) {
                $sets[] = "$k=?";
                $vals[] = $v;
            }
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

    public static function nearby(float $latitude, float $longitude, float $radiusKm=5.0, int $limit=20): array
    {
        $radiusKm = max(0.1, min(50.0, $radiusKm));
        $limit = max(1, min(100, $limit));
        $latRad = deg2rad($latitude);
        $cosLat = cos($latRad);
        $deltaLat = $radiusKm / 111.045;
        $deltaLng = $radiusKm / (111.045 * max(0.0001, abs($cosLat)));
        $minLat = $latitude - $deltaLat;
        $maxLat = $latitude + $deltaLat;
        $minLng = $longitude - $deltaLng;
        $maxLng = $longitude + $deltaLng;

        $sql = 'SELECT c.*, v.vi_do, v.kinh_do,
                (6371 * 2 * ASIN(SQRT(
                    POWER(SIN(RADIANS(v.vi_do - :lat)/2), 2) +
                    COS(RADIANS(:lat)) * COS(RADIANS(v.vi_do)) *
                    POWER(SIN(RADIANS(v.kinh_do - :lng)/2), 2)
                ))) AS distance_km
                FROM cua_hang c
                JOIN vi_tri v ON v.id_vi_tri = c.id_vi_tri
                WHERE v.vi_do IS NOT NULL AND v.kinh_do IS NOT NULL
                  AND v.vi_do BETWEEN :minLat AND :maxLat
                  AND v.kinh_do BETWEEN :minLng AND :maxLng
                HAVING distance_km <= :radius
                ORDER BY distance_km ASC
                LIMIT ' . $limit;
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':lat',$latitude);
        $stmt->bindValue(':lng',$longitude);
        $stmt->bindValue(':minLat',$minLat);
        $stmt->bindValue(':maxLat',$maxLat);
        $stmt->bindValue(':minLng',$minLng);
        $stmt->bindValue(':maxLng',$maxLng);
        $stmt->bindValue(':radius',$radiusKm);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            if (isset($row['distance_km'])) {
                $row['distance_km'] = round((float)$row['distance_km'], 3);
            }
        }
        unset($row);
        return $rows;
    }

    public static function dashboardMetrics(int $storeId): array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT
            (SELECT COUNT(*) FROM danh_gia WHERE id_cua_hang=:sid) AS review_count,
            (SELECT COUNT(*) FROM khuyen_mai_cua_hang WHERE id_cua_hang=:sid AND trang_thai IN (\'da_duyet\',\'dang_hoat_dong\',\'approved\')) AS promotion_count,
            (SELECT COUNT(*) FROM voucher_cua_hang WHERE id_cua_hang=:sid) AS voucher_count,
            (SELECT COUNT(*) FROM yeu_thich WHERE id_cua_hang=:sid) AS favorite_count,
            (SELECT luot_xem FROM cua_hang WHERE id_cua_hang=:sid) AS view_count,
            (SELECT diem_danh_gia_trung_binh FROM cua_hang WHERE id_cua_hang=:sid) AS average_rating,
            (SELECT COUNT(*) FROM khuyen_mai WHERE pham_vi_ap_dung=\'toan_he_thong\' AND trang_thai IN (\'dang_hoat_dong\',\'da_duyet\')) AS global_promotions_count';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sid'=>$storeId]);
        $stats = $stmt->fetch() ?: [];
        return [
            'review_count' => (int)($stats['review_count'] ?? 0),
            'promotion_count' => (int)($stats['promotion_count'] ?? 0),
            'voucher_count' => (int)($stats['voucher_count'] ?? 0),
            'favorite_count' => (int)($stats['favorite_count'] ?? 0),
            'view_count' => (int)($stats['view_count'] ?? 0),
            'average_rating' => isset($stats['average_rating']) ? (float)$stats['average_rating'] : 0.0,
            'global_promotions_count' => (int)($stats['global_promotions_count'] ?? 0),
        ];
    }
}