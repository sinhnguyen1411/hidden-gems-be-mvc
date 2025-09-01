<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\DB;

class AdminController
{
    public function dashboard(Request $req): Response
    {
        $pdo = DB::pdo();
        $users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $shops = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='shop'")->fetchColumn();
        $stores = (int)$pdo->query("SELECT COUNT(*) FROM cua_hang")->fetchColumn();
        $reviews = (int)$pdo->query("SELECT COUNT(*) FROM danh_gia")->fetchColumn();
        $vouchers = (int)$pdo->query("SELECT COUNT(*) FROM voucher")->fetchColumn();
        $promos = (int)$pdo->query("SELECT COUNT(*) FROM khuyen_mai")->fetchColumn();
        return JsonResponse::ok(['data'=>compact('users','shops','stores','reviews','vouchers','promos')]);
    }

    public function setRole(Request $req): Response
    {
        $data = $req->getParsedBody();
        $userId = (int)($data['id_user'] ?? 0);
        $role = $data['role'] ?? '';
        if ($userId<=0 || !in_array($role,['admin','shop','customer'],true)) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        $stmt = DB::pdo()->prepare('UPDATE users SET role=? WHERE id_user=?');
        $stmt->execute([$role,$userId]);
        return JsonResponse::ok(['message'=>'Role updated']);
    }

    public function pendingStores(Request $req): Response
    {
        // Assuming status table seeded with 'dang_cho' for group 'cua_hang' and mapped id
        $stmt = DB::pdo()->prepare("SELECT s.* FROM cua_hang s JOIN status st ON st.id_trang_thai=s.id_trang_thai WHERE st.ten_trang_thai='dang_cho' AND st.nhom_trang_thai='cua_hang' ORDER BY s.id_cua_hang DESC");
        $stmt->execute();
        return JsonResponse::ok(['data'=>$stmt->fetchAll()]);
    }

    public function approveStore(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $action = $req->getParsedBody()['action'] ?? 'approve';
        $statusName = $action === 'reject' ? 'dong_cua' : 'hoat_dong';
        $stmt = DB::pdo()->prepare("SELECT id_trang_thai FROM status WHERE nhom_trang_thai='cua_hang' AND ten_trang_thai=? LIMIT 1");
        $stmt->execute([$statusName]);
        $st = $stmt->fetch();
        if (!$st) return JsonResponse::ok(['error'=>'Status not configured'],500);
        $ok = DB::pdo()->prepare('UPDATE cua_hang SET id_trang_thai=? WHERE id_cua_hang=?')->execute([(int)$st['id_trang_thai'],$id]);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }

    public function contact(Request $req): Response
    {
        // Simple contact info endpoint for deep links
        return JsonResponse::ok([
            'email' => $_ENV['CONTACT_EMAIL'] ?? null,
            'zalo' => $_ENV['CONTACT_ZALO'] ?? null,
            'phone' => $_ENV['CONTACT_PHONE'] ?? null,
        ]);
    }
}
