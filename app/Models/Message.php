<?php
namespace App\Models;

use App\Core\DB;

class Message
{
    public static function send(int $fromUserId, int $toUserId, string $content): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO tin_nhan(id_nguoi_gui,id_nguoi_nhan,noi_dung) VALUES (?,?,?)');
        $stmt->execute([$fromUserId,$toUserId,$content]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function between(int $userA, int $userB, int $limit=50, int $offset=0): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM tin_nhan WHERE (id_nguoi_gui=? AND id_nguoi_nhan=?) OR (id_nguoi_gui=? AND id_nguoi_nhan=?) ORDER BY id_tin_nhan DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$userA,\PDO::PARAM_INT);
        $stmt->bindValue(2,$userB,\PDO::PARAM_INT);
        $stmt->bindValue(3,$userB,\PDO::PARAM_INT);
        $stmt->bindValue(4,$userA,\PDO::PARAM_INT);
        $stmt->bindValue(5,$limit,\PDO::PARAM_INT);
        $stmt->bindValue(6,$offset,\PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_reverse($rows);
    }

    public static function conversationsFor(int $userId): array
    {
        $sql = 'SELECT other.id_user, other.username, MAX(m.id_tin_nhan) AS last_id, MAX(m.thoi_gian_tao) AS last_time
                FROM (
                    SELECT CASE WHEN id_nguoi_gui = :uid THEN id_nguoi_nhan ELSE id_nguoi_gui END AS other_id
                    FROM tin_nhan WHERE id_nguoi_gui=:uid OR id_nguoi_nhan=:uid
                ) x
                JOIN users other ON other.id_user = x.other_id
                JOIN tin_nhan m ON (m.id_nguoi_gui=:uid AND m.id_nguoi_nhan=other.id_user) OR (m.id_nguoi_nhan=:uid AND m.id_nguoi_gui=other.id_user)
                GROUP BY other.id_user, other.username
                ORDER BY last_time DESC';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll();
    }
}

