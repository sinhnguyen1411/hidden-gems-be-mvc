<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Voucher;

class VoucherController
{
    public function create(Request $req): Response
    {
        $data = $req->getParsedBody();
        $code = trim($data['ma_voucher'] ?? '');
        $name = trim($data['ten_voucher'] ?? '');
        $value = (float)($data['gia_tri_giam'] ?? 0);
        $type = $data['loai_giam_gia'] ?? 'percent';
        $expires = $data['ngay_het_han'] ?? null;
        $qty = (int)($data['so_luong_con_lai'] ?? 0);
        if ($code === '' || $value <= 0 || !in_array($type,['percent','amount'],true)) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        $id = Voucher::create($code,$name,$value,$type,$expires,$qty);
        return JsonResponse::ok(['message'=>'Voucher created','id_voucher'=>$id],201);
    }

    public function assign(Request $req): Response
    {
        $data = $req->getParsedBody();
        $voucherId = (int)($data['id_voucher'] ?? 0);
        $storeId = (int)($data['id_cua_hang'] ?? 0);
        if ($voucherId<=0 || $storeId<=0) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        $ok = Voucher::assignToStore($voucherId,$storeId);
        return JsonResponse::ok(['message'=>$ok?'Assigned':'No changes']);
    }

    public function byStore(Request $req): Response
    {
        $storeId = (int)$req->getAttribute('id');
        $rows = Voucher::listByStore($storeId);
        return JsonResponse::ok(['data'=>$rows]);
    }
}
