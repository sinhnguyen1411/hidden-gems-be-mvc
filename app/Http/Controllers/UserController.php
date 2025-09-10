<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\DB;
use App\Models\User;

class UserController extends Controller
{
    public function profile(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $row = User::findById($uid);
        if (!$row) return JsonResponse::ok(['error'=>'Not found'],404);
        unset($row['password_hash'], $row['refresh_token']);
        return JsonResponse::ok(['data'=>$row]);
    }

    public function updateProfile(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $body = $req->getParsedBody();
        $fullName = isset($body['full_name']) ? trim((string)$body['full_name']) : null;
        $phone = isset($body['phone_number']) ? trim((string)$body['phone_number']) : null;
        $email = isset($body['email']) ? strtolower(trim((string)$body['email'])) : null;

        // Validate (basic)
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return JsonResponse::ok(['error'=>'Invalid email'],422);
        }
        if ($phone !== null && $phone !== '') {
            // enforce unique phone if provided
            $chk = DB::pdo()->prepare('SELECT id_user FROM users WHERE phone_number=? AND id_user<>? LIMIT 1');
            $chk->execute([$phone,$uid]);
            if ($chk->fetch()) return JsonResponse::ok(['error'=>'Phone already in use'],409);
        }
        if ($email !== null) {
            $chk = DB::pdo()->prepare('SELECT id_user FROM users WHERE email=? AND id_user<>? LIMIT 1');
            $chk->execute([$email,$uid]);
            if ($chk->fetch()) return JsonResponse::ok(['error'=>'Email already in use'],409);
        }

        $sets = [];$vals=[];
        if ($fullName !== null) { $sets[]='full_name=?'; $vals[]=$fullName; }
        if ($phone !== null)    { $sets[]='phone_number=?'; $vals[]=$phone; }
        $emailChanged = false;
        if ($email !== null) { $sets[]='email=?'; $vals[]=$email; $sets[]='email_verified_at=NULL'; $emailChanged = true; }
        if (!$sets) return JsonResponse::ok(['message'=>'No changes']);
        $vals[] = $uid;
        DB::pdo()->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id_user=?')->execute($vals);

        if ($emailChanged) {
            // Issue verification token and email (best-effort)
            $ttl = (int)($_ENV['EMAIL_VERIFY_TTL_SECONDS'] ?? 86400);
            $t = bin2hex(random_bytes(32));
            $h = hash('sha256', $t);
            $expAt = date('Y-m-d H:i:s', time() + $ttl);
            DB::pdo()->prepare('INSERT INTO email_verification(id_user, token_hash, expires_at) VALUES(?, ?, ?)')
                ->execute([$uid,$h,$expAt]);
            $url = rtrim($_ENV['FRONTEND_URL'] ?? ($_ENV['APP_URL'] ?? ''),'/') . '/verify-email?token=' . urlencode($t);
            $html = '<p>Please verify your email by clicking <a href="'.$url.'">this link</a>.</p>';
            try { \App\Core\Mailer::send($email, 'Verify your email', $html, strip_tags($html)); } catch (\Throwable $e) {}
        }

        return JsonResponse::ok(['message'=>'Profile updated']);
    }

    public function consent(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $b = $req->getParsedBody();
        $terms = (string)($b['terms_version'] ?? '');
        $privacy = (string)($b['privacy_version'] ?? '');
        if ($terms === '' && $privacy === '') return JsonResponse::ok(['error'=>'Invalid input'],422);
        DB::pdo()->prepare('INSERT INTO user_consent(id_user, terms_version, privacy_version, consent_at) VALUES(?,?,?,NOW())')
            ->execute([$uid, $terms ?: null, $privacy ?: null]);
        return JsonResponse::ok(['message'=>'Consent recorded']);
    }

    public function export(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $pdo = DB::pdo();
        $profile = User::findById($uid);
        if ($profile) { unset($profile['password_hash'], $profile['refresh_token']); }
        $stores = $pdo->prepare('SELECT * FROM cua_hang WHERE id_chu_so_huu=?'); $stores->execute([$uid]); $stores = $stores->fetchAll();
        $reviews = $pdo->prepare('SELECT * FROM danh_gia WHERE id_user=?'); $reviews->execute([$uid]); $reviews = $reviews->fetchAll();
        $favorites = $pdo->prepare('SELECT * FROM yeu_thich WHERE id_user=?'); $favorites->execute([$uid]); $favorites = $favorites->fetchAll();
        $images = $pdo->prepare('SELECT * FROM hinh_anh WHERE id_user=?'); $images->execute([$uid]); $images = $images->fetchAll();
        $wallet = $pdo->prepare('SELECT * FROM giao_dich_vi WHERE id_user=? ORDER BY id_giao_dich DESC'); $wallet->execute([$uid]); $wallet = $wallet->fetchAll();
        $messages = $pdo->prepare('SELECT * FROM tin_nhan WHERE id_nguoi_gui=? OR id_nguoi_nhan=? ORDER BY id_tin_nhan DESC'); $messages->execute([$uid,$uid]); $messages = $messages->fetchAll();
        // Ad requests for owned stores
        $ads = $pdo->prepare('SELECT a.* FROM yeu_cau_quang_cao a JOIN cua_hang c ON c.id_cua_hang=a.id_cua_hang WHERE c.id_chu_so_huu=? ORDER BY a.id_yeu_cau DESC'); $ads->execute([$uid]); $ads = $ads->fetchAll();
        return JsonResponse::ok([
            'profile' => $profile,
            'stores' => $stores,
            'reviews' => $reviews,
            'favorites' => $favorites,
            'images' => $images,
            'wallet_transactions' => $wallet,
            'messages' => $messages,
            'ad_requests' => $ads,
        ]);
    }
}

