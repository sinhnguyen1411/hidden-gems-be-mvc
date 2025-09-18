<?php
namespace App\Models;

use App\Core\DB;

class Banner
{
    public static function create(string $title, ?string $desc, string $imageUrl, ?string $linkUrl, ?string $position, int $order=0, bool $active=true): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO banner(tieu_de,mo_ta,url_anh,link_url,vi_tri,thu_tu,active) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$title,$desc,$imageUrl,$linkUrl,$position,$order,$active?1:0]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function list(?string $position=null, bool $onlyActive=true): array
    {
        if ($position) {
            $stmt = DB::pdo()->prepare('SELECT * FROM banner WHERE vi_tri=? ' . ($onlyActive ? 'AND active=1' : '') . ' ORDER BY thu_tu ASC, id_banner DESC');
            $stmt->execute([$position]);
            return $stmt->fetchAll();
        }
        $sql = 'SELECT * FROM banner ' . ($onlyActive ? 'WHERE active=1' : '') . ' ORDER BY vi_tri, thu_tu ASC, id_banner DESC';
        $stmt = DB::pdo()->query($sql);
        return $stmt->fetchAll();
    }

    public static function update(int $id, array $fields): bool
    {
        $sets = [];
        $vals = [];
        foreach ($fields as $k=>$v) {
            $sets[] = "$k=?";
            $vals[] = $v;
        }
        if (!$sets) return false;
        $vals[] = $id;
        $stmt = DB::pdo()->prepare('UPDATE banner SET '.implode(',',$sets).' WHERE id_banner=?');
        return $stmt->execute($vals);
    }

    public static function reorder(array $orderedIds): int
    {
        if (!$orderedIds) {
            return 0;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE banner SET thu_tu=? WHERE id_banner=?');
            $order = 1;
            foreach ($orderedIds as $id) {
                $stmt->execute([$order++,$id]);
            }
            $pdo->commit();
            return $order - 1;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool
    {
        $stmt = DB::pdo()->prepare('DELETE FROM banner WHERE id_banner=?');
        return $stmt->execute([$id]);
    }

    public static function touchCacheHint(): void
    {
        $ttl = (int)($_ENV['BANNERS_CACHE_TTL'] ?? 60);
        \App\Core\Cache::set('banners:last_touch', time(), max(1,$ttl));
    }
}