<?php
namespace App\Models;

use App\Core\DB;

class Message
{
    public static function send(int $fromUserId, int $toUserId, string $content, string $type='text', ?array $payload=null): int
    {
        $stored = $content;
        if ($type !== 'text' || $payload !== null) {
            $stored = json_encode([
                'type' => $type,
                'content' => $content,
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE);
        }
        $stmt = DB::pdo()->prepare('INSERT INTO tin_nhan(id_nguoi_gui,id_nguoi_nhan,noi_dung) VALUES (?,?,?)');
        $stmt->execute([$fromUserId,$toUserId,$stored]);
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
        $rows = array_reverse($rows);
        return array_map([self::class,'mapRow'],$rows);
    }

    public static function conversationsFor(int $userId): array
    {
        $sql = 'SELECT other.id_user, other.username, MAX(m.id_tin_nhan) AS last_id, MAX(m.thoi_gian_tao) AS last_time,
                SUM(CASE WHEN m.id_nguoi_nhan=:uid AND m.da_doc=0 THEN 1 ELSE 0 END) AS unread_count
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
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['id_user'] = (int)$row['id_user'];
            $row['unread_count'] = (int)($row['unread_count'] ?? 0);
        }
        unset($row);
        return $rows;
    }

    public static function markRead(int $userId, ?int $fromUserId=null, array $messageIds=[]): int
    {
        $pdo = DB::pdo();
        $ids = array_values(array_filter(array_map('intval',$messageIds), function ($v) { return (int)$v > 0; }));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE tin_nhan SET da_doc=1 WHERE id_nguoi_nhan=? AND id_tin_nhan IN ('.$placeholders.')';
            $params = array_merge([$userId], $ids);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }
        if ($fromUserId !== null && $fromUserId > 0) {
            $stmt = $pdo->prepare('UPDATE tin_nhan SET da_doc=1 WHERE id_nguoi_nhan=? AND id_nguoi_gui=? AND da_doc=0');
            $stmt->execute([$userId,$fromUserId]);
            return $stmt->rowCount();
        }
        $stmt = $pdo->prepare('UPDATE tin_nhan SET da_doc=1 WHERE id_nguoi_nhan=? AND da_doc=0');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public static function unreadCounts(int $userId): array
    {
        $sql = 'SELECT m.id_nguoi_gui AS from_user_id, u.username, COUNT(*) AS unread_count,
                       MAX(m.id_tin_nhan) AS last_message_id, MAX(m.thoi_gian_tao) AS last_message_time
                FROM tin_nhan m
                JOIN users u ON u.id_user = m.id_nguoi_gui
                WHERE m.id_nguoi_nhan = ? AND m.da_doc = 0
                GROUP BY m.id_nguoi_gui, u.username
                ORDER BY last_message_time DESC';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['from_user_id'] = (int)$row['from_user_id'];
            $row['unread_count'] = (int)$row['unread_count'];
            $row['last_message_id'] = (int)($row['last_message_id'] ?? 0);
        }
        unset($row);
        return $rows;
    }

    private static function mapRow(array $row): array
    {
        $row['da_doc'] = (int)($row['da_doc'] ?? 0);
        $row['content_type'] = 'text';
        $raw = $row['noi_dung'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['type'])) {
                $row['content_type'] = $decoded['type'];
                $row['noi_dung'] = $decoded['content'] ?? '';
                if (array_key_exists('payload',$decoded)) {
                    $row['payload'] = $decoded['payload'];
                }
            }
        }
        return $row;
    }
}