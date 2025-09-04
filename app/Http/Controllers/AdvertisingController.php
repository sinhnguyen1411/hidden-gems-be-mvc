<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\AdRequest;
use App\Models\Wallet;
use App\Models\Cafe;

class AdvertisingController extends Controller
{
    private function packages(): array
    {
        // code => [days, price_usd]
        return [
            '1d' => ['days'=>1,  'price'=>1.00,  'ten'=>'1 ngày'],
            '1w' => ['days'=>7,  'price'=>5.00,  'ten'=>'1 tuần'],
            '1m' => ['days'=>30, 'price'=>18.00, 'ten'=>'1 tháng'],
        ];
    }

    public function packagesList(Request $req): Response
    {
        $items = [];
        foreach ($this->packages() as $code=>$cfg) {
            $items[] = ['ma_goi'=>$code,'so_ngay'=>$cfg['days'],'gia_usd'=>$cfg['price'],'ten'=>$cfg['ten']];
        }
        return JsonResponse::ok(['data'=>$items]);
    }

    public function create(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $data = $req->getParsedBody();
        $storeId = (int)($data['id_cua_hang'] ?? 0);
        $pkg = $data['goi'] ?? '';
        $start = trim($data['ngay_bat_dau'] ?? ''); // YYYY-MM-DD
        if ($storeId<=0 || $pkg==='') return JsonResponse::ok(['error'=>'Invalid input'],422);
        $packages = $this->packages();
        if (!isset($packages[$pkg])) return JsonResponse::ok(['error'=>'Unknown package'],422);

        $store = Cafe::find($storeId);
        if (!$store) return JsonResponse::ok(['error'=>'Store not found'],404);
        if ((int)$store['id_chu_so_huu'] !== $uid && ($user['role'] ?? '') !== 'admin') {
            return JsonResponse::ok(['error'=>'Forbidden'],403);
        }

        // Validate start date at least 1 day in advance
        try {
            $startDate = new \DateTime($start ?: 'tomorrow');
        } catch (\Throwable $e) {
            return JsonResponse::ok(['error'=>'Invalid start date'],422);
        }
        $todayPlus1 = new \DateTime('tomorrow');
        $todayPlus1->setTime(0,0,0);
        $startDateMid = clone $startDate; $startDateMid->setTime(0,0,0);
        if ($startDateMid < $todayPlus1) {
            return JsonResponse::ok(['error'=>'Start date must be at least 1 day in advance'],422);
        }
        $days = (int)$packages[$pkg]['days'];
        $price = (float)$packages[$pkg]['price'];
        $endDate = (clone $startDateMid)->modify('+'.($days).' days')->modify('-1 second'); // inclusive range

        // Create request first
        $adId = AdRequest::create($storeId, $pkg, $startDateMid->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d H:i:s'), $price, null);

        // Charge wallet
        [$ok, $txId, $msg] = Wallet::charge((int)$store['id_chu_so_huu'], $price, 'Thanh toán gói quảng cáo '.$pkg.' cho cửa hàng #'.$storeId, 'ad_request', $adId);
        if (!$ok) {
            // Best-effort cleanup if insufficient balance
            \App\Core\DB::pdo()->prepare('DELETE FROM yeu_cau_quang_cao WHERE id_yeu_cau=?')->execute([$adId]);
            return JsonResponse::ok(['error'=>$msg ?? 'Charge failed','so_du'=>Wallet::balance((int)$store['id_chu_so_huu'])],402);
        }
        // Update ad with tx id
        \App\Core\DB::pdo()->prepare('UPDATE yeu_cau_quang_cao SET id_giao_dich_tru=? WHERE id_yeu_cau=?')->execute([$txId,$adId]);

        return JsonResponse::ok(['message'=>'Request submitted, pending admin approval','id_yeu_cau'=>$adId],201);
    }

    public function myRequests(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $q = $req->getQueryParams();
        $page = max(1,(int)($q['page'] ?? 1));
        $per = min(50, max(1,(int)($q['per_page'] ?? 10)));
        $data = AdRequest::listByOwner($uid,$page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function adminPending(Request $req): Response
    {
        $q = $req->getQueryParams();
        $page = max(1,(int)($q['page'] ?? 1));
        $per = min(50, max(1,(int)($q['per_page'] ?? 20)));
        $data = AdRequest::listPending($page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function adminReview(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $status = $data['trang_thai'] ?? '';
        if (!in_array($status,['da_duyet','tu_choi'],true)) return JsonResponse::ok(['error'=>'Invalid status'],422);
        $ad = AdRequest::find($id);
        if (!$ad) return JsonResponse::ok(['error'=>'Not found'],404);
        $user = $req->getAttribute('user', []);
        $adminId = (int)($user['uid'] ?? 0);

        if ($status === 'tu_choi') {
            // Refund to owner
            $row = \App\Core\DB::pdo()->prepare('SELECT id_chu_so_huu FROM cua_hang WHERE id_cua_hang=?');
            $row->execute([(int)$ad['id_cua_hang']]);
            $owner = $row->fetch();
            if ($owner) {
                Wallet::refund((int)$owner['id_chu_so_huu'], (float)$ad['gia'], 'Hoàn tiền do từ chối yêu cầu quảng cáo #'.$id, 'ad_request', $id);
            }
        }

        $ok = AdRequest::review($id,$status,$adminId);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }

    public function active(Request $req): Response
    {
        $q = $req->getQueryParams();
        $at = $q['tai_ngay'] ?? null; // YYYY-MM-DD or Y-m-d H:i:s
        try {
            $dt = new \DateTime($at ?: 'now');
        } catch (\Throwable $e) {
            $dt = new \DateTime();
        }
        $rows = AdRequest::listActiveAt($dt->format('Y-m-d H:i:s'));
        return JsonResponse::ok(['data'=>$rows]);
    }
}
