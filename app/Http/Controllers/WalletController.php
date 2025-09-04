<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Wallet;

class WalletController extends Controller
{
    public function me(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $balance = Wallet::balance($uid);
        return JsonResponse::ok(['data'=>['so_du'=>$balance]]);
    }

    public function history(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $q = $req->getQueryParams();
        $limit = min(100, max(1, (int)($q['limit'] ?? 50)));
        $offset = max(0, (int)($q['offset'] ?? 0));
        $rows = Wallet::history($uid, $limit, $offset);
        return JsonResponse::ok(['data'=>$rows]);
    }

    public function depositInstructions(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $syntax = 'HG NAP '.$uid;
        return JsonResponse::ok([
            'bank' => [
                'ten_chu_tai_khoan' => 'Hidden Gems Demo',
                'so_tai_khoan' => '1234567890',
                'ten_ngan_hang' => 'DemoBank',
            ],
            'quy_dinh_noi_dung' => 'Ghi rõ nội dung chuyển khoản theo cú pháp: HG NAP {id_user}',
            'cu_phap' => $syntax,
            'vi_du' => 'VD: '.$syntax,
            'ghi_chu' => 'Demo mô phỏng, hệ thống tự nhận diện theo nội dung chuyển khoản.'
        ]);
    }

    public function simulateBankTransfer(Request $req): Response
    {
        // Demo endpoint to simulate bank webhook: expects {noi_dung, so_tien}
        $data = $req->getParsedBody();
        $content = trim($data['noi_dung'] ?? '');
        $amount = (float)($data['so_tien'] ?? 0);
        if ($content === '' || $amount <= 0) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        // Optional webhook secret enforcement
        $secret = $_ENV['BANK_WEBHOOK_SECRET'] ?? null;
        if ($secret) {
            $hdr = $req->getHeaderLine('X-Webhook-Secret');
            if (!$hdr || $hdr !== $secret) {
                return JsonResponse::ok(['error'=>'Unauthorized'],401);
            }
        }
        // Parse: HG NAP {id_user}
        if (!preg_match('/HG\s*NAP\s*(\d+)/i', $content, $m)) {
            return JsonResponse::ok(['error'=>'Content not recognized'],422);
        }
        $uid = (int)$m[1];
        try {
            $txId = Wallet::deposit($uid, $amount, 'Bank transfer recognized: '.$content, 'bank_transfer', null);
            $balance = Wallet::balance($uid);
            return JsonResponse::ok(['message'=>'Credited','id_user'=>$uid,'id_giao_dich'=>$txId,'so_du'=>$balance],201);
        } catch (\Throwable $e) {
            return JsonResponse::ok(['error'=>'Failed to credit'],500);
        }
    }
}
