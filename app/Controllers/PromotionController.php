<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Promotion;

class PromotionController
{
    public function create(Request $req): Response
    {
        $data = $req->getParsedBody();
        foreach (['ten_chuong_trinh','ngay_bat_dau','ngay_ket_thuc'] as $f) {
            if (empty($data[$f])) return (new Response())->json(['error'=>'Missing '.$f],422);
        }
        $id = Promotion::create($data);
        return (new Response())->json(['message'=>'Promotion created','id_khuyen_mai'=>$id],201);
    }

    public function applyStore(Request $req): Response
    {
        $promoId = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $storeId = (int)($data['id_cua_hang'] ?? 0);
        if ($promoId<=0 || $storeId<=0) return (new Response())->json(['error'=>'Invalid input'],422);
        $ok = Promotion::applyStore($promoId,$storeId);
        return (new Response())->json(['message'=>$ok?'Applied':'No changes']);
    }

    public function reviewApplication(Request $req): Response
    {
        $promoId = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $storeId = (int)($data['id_cua_hang'] ?? 0);
        $status = $data['trang_thai'] ?? '';
        $user = $req->getAttribute('user', []);
        $approver = (int)($user['uid'] ?? 0);
        if (!in_array($status,['da_duyet','tu_choi'],true)) return (new Response())->json(['error'=>'Invalid status'],422);
        $ok = Promotion::reviewApplication($promoId,$storeId,$status,$approver);
        return (new Response())->json(['message'=>$ok?'Updated':'No changes']);
    }

    public function byStore(Request $req): Response
    {
        $storeId = (int)$req->getAttribute('id');
        $rows = Promotion::listByStore($storeId);
        return (new Response())->json(['data'=>$rows]);
    }
}

