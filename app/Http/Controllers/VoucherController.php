<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Validator;
use App\Models\Voucher;

class VoucherController extends Controller
{
    public function create(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $role = $user['role'] ?? 'customer';
        $data = $req->getParsedBody();
        $errors = Validator::validate($data,[
            'ma_voucher' => 'required',
            'loai_giam_gia' => 'in:percent,amount'
        ]);
        $code = trim($data['ma_voucher'] ?? '');
        $name = trim($data['ten_voucher'] ?? '');
        $value = (float)($data['gia_tri_giam'] ?? 0);
        $type = $data['loai_giam_gia'] ?? 'percent';
        $expires = $data['ngay_het_han'] ?? null;
        $qty = (int)($data['so_luong_con_lai'] ?? 0);
        $isGlobal = !empty($data['is_global']);
        if ($isGlobal && $role !== 'admin') {
            return JsonResponse::ok(['error'=>'Forbidden'],403);
        }
        if ($errors || $code === '' || $value <= 0 || !in_array($type,['percent','amount'],true)) {
            return JsonResponse::ok(['error'=>'Invalid input','details'=>$errors ?: []],422);
        }
        $id = Voucher::create($code,$name,$value,$type,$expires,$qty,$isGlobal);
        $assigned = 0;
        if (!$isGlobal && !empty($data['store_ids']) && is_array($data['store_ids'])) {
            $storeIds = array_filter(array_map('intval',$data['store_ids']));
            foreach ($storeIds as $storeId) {
                if ($storeId > 0) {
                    if (Voucher::assignToStore($id,$storeId)) {
                        $assigned++;
                    }
                }
            }
        }
        return JsonResponse::ok([
            'message'=>'Voucher created',
            'id_voucher'=>$id,
            'is_global'=>$isGlobal,
            'assigned'=>$assigned
        ],201);
    }

    public function assign(Request $req): Response
    {
        $data = $req->getParsedBody();
        $voucherId = (int)($data['id_voucher'] ?? 0);
        $storeId = (int)($data['id_cua_hang'] ?? 0);
        if ($voucherId<=0 || $storeId<=0) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        if (!Voucher::isAssignableToStore($voucherId)) {
            return JsonResponse::ok(['error'=>'Voucher is global and cannot be assigned'],409);
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

    public function global(Request $req): Response
    {
        $rows = Voucher::listGlobal();
        return JsonResponse::ok(['data'=>$rows]);
    }
}