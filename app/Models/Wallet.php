<?php
namespace App\Models;

use App\Core\DB;

class Wallet
{
    public static function ensure(int $userId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id_user FROM vi_tien WHERE id_user=?');
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            $pdo->prepare('INSERT INTO vi_tien(id_user, so_du) VALUES(?, 0.00)')->execute([$userId]);
        }
    }

    public static function balance(int $userId): float
    {
        self::ensure($userId);
        $stmt = DB::pdo()->prepare('SELECT so_du FROM vi_tien WHERE id_user=?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (float)$row['so_du'] : 0.0;
    }

    public static function deposit(int $userId, float $amount, string $desc='Bank transfer recognized', ?string $refType=null, ?int $refId=null): int
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            self::ensure($userId);
            $pdo->prepare('UPDATE vi_tien SET so_du = so_du + ? WHERE id_user=?')->execute([$amount, $userId]);
            $stmt = $pdo->prepare('INSERT INTO giao_dich_vi(id_user, so_tien, loai, mo_ta, tham_chieu_loai, tham_chieu_id) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$userId, $amount, 'nap', $desc, $refType, $refId]);
            $txId = (int)$pdo->lastInsertId();
            $pdo->commit();
            return $txId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function charge(int $userId, float $amount, string $desc='Charge', ?string $refType=null, ?int $refId=null): array
    {
        // Returns [success(bool), txId(int|null), message(string|null)]
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            self::ensure($userId);
            // Lock row
            $lock = $pdo->prepare('SELECT so_du FROM vi_tien WHERE id_user=? FOR UPDATE');
            $lock->execute([$userId]);
            $row = $lock->fetch();
            $balance = $row ? (float)$row['so_du'] : 0.0;
            if ($balance < $amount) {
                $pdo->rollBack();
                return [false, null, 'Insufficient balance'];
            }
            $pdo->prepare('UPDATE vi_tien SET so_du = so_du - ? WHERE id_user=?')->execute([$amount, $userId]);
            $stmt = $pdo->prepare('INSERT INTO giao_dich_vi(id_user, so_tien, loai, mo_ta, tham_chieu_loai, tham_chieu_id) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$userId, $amount, 'tru', $desc, $refType, $refId]);
            $txId = (int)$pdo->lastInsertId();
            $pdo->commit();
            return [true, $txId, null];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function refund(int $userId, float $amount, string $desc='Refund', ?string $refType=null, ?int $refId=null): int
    {
        return self::deposit($userId, $amount, $desc, $refType, $refId);
    }

    public static function history(int $userId, int $limit=50, int $offset=0): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM giao_dich_vi WHERE id_user=? ORDER BY id_giao_dich DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

