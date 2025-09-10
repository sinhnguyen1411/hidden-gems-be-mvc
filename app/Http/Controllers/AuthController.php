<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Auth as JWTAuth;
use App\Models\User;
use App\Core\Validator;
use App\Core\DB;

class AuthController extends Controller
{
    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (str_contains($ip, ',')) { $ip = trim(explode(',', $ip)[0]); }
        return substr($ip, 0, 45);
    }

    private function userAgent(): string
    {
        return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    private function recordLoginAttempt(string $identifier, bool $success): void
    {
        $stmt = DB::pdo()->prepare('INSERT INTO login_attempt(identifier, ip, success) VALUES (?,?,?)');
        $stmt->execute([$identifier, $this->clientIp(), $success ? 1 : 0]);
    }

    private function tooManyAttempts(string $identifier): bool
    {
        $max = (int)($_ENV['RATE_LIMIT_LOGIN_MAX'] ?? 5);
        $window = (int)($_ENV['RATE_LIMIT_LOGIN_WINDOW'] ?? 600); // seconds
        $since = date('Y-m-d H:i:s', time() - $window);
        $stmt = DB::pdo()->prepare('SELECT COUNT(*) FROM login_attempt WHERE identifier=? AND ip=? AND success=0 AND created_at >= ?');
        $stmt->execute([$identifier, $this->clientIp(), $since]);
        $failures = (int)$stmt->fetchColumn();
        return $failures >= $max;
    }

    private function issueRefreshToken(int $userId): string
    {
        $ttl = (int)($_ENV['REFRESH_TOKEN_TTL_SECONDS'] ?? 2592000); // 30 days
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $ua = $this->userAgent();
        $ip = $this->clientIp();
        $expAt = date('Y-m-d H:i:s', time() + $ttl);
        $stmt = DB::pdo()->prepare('INSERT INTO refresh_token(id_user, token_hash, user_agent, ip, expires_at) VALUES (?,?,?,?, ?)');
        $stmt->execute([$userId, $hash, $ua, $ip, $expAt]);
        return $token;
    }

    private function rotateRefreshToken(string $oldToken, int $userId): string
    {
        $oldHash = hash('sha256', $oldToken);
        // Revoke old
        DB::pdo()->prepare('UPDATE refresh_token SET revoked_at=NOW() WHERE token_hash=? AND id_user=?')->execute([$oldHash, $userId]);
        // Issue new
        return $this->issueRefreshToken($userId);
    }
    public function register(Request $req): Response
    {
        $data = $req->getParsedBody();
        $errors = Validator::validate($data,[
            'username'=>'required',
            'email'=>'required|email',
            'password'=>'required|min:6'
        ]);
        if ($errors) {
            return JsonResponse::ok(['error'=>'Invalid input','details'=>$errors],422);
        }
        $email = strtolower($data['email']);
        if (User::findByEmail($email)) {
            return JsonResponse::ok(['error'=>'Email already in use'],409);
        }
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $fullName = $data['full_name'] ?? null;
        $phone = $data['phone_number'] ?? null;
        $id = User::create($data['username'], $email, $hash, 'customer', $fullName, $phone);
        // Email verification token (optional)
        $ttl = (int)($_ENV['EMAIL_VERIFY_TTL_SECONDS'] ?? 86400);
        $tkn = bin2hex(random_bytes(32));
        $hashT = hash('sha256', $tkn);
        $expAt = date('Y-m-d H:i:s', time() + $ttl);
        DB::pdo()->prepare('INSERT INTO email_verification(id_user, token_hash, expires_at) VALUES(?, ?, ?)')->execute([$id, $hashT, $expAt]);
        $payload = ['message' => 'Registered', 'user_id' => $id];
        if (($_ENV['APP_ENV'] ?? 'local') === 'local' || !empty($_ENV['APP_DEBUG'])) {
            $payload['verify_email_token'] = $tkn; // dev convenience
        }
        return JsonResponse::ok($payload, 201);
    }

    public function login(Request $req): Response
    {
        $data = $req->getParsedBody();
        $password = $data['password'] ?? '';
        $user = null;
        if (!empty($data['email'])) {
            $user = User::findByEmail(strtolower(trim($data['email'])));
        } elseif (!empty($data['username'])) {
            $user = User::findByUsername($data['username']);
        }
        $identifier = strtolower(trim($data['email'] ?? $data['username'] ?? 'unknown'));
        if ($this->tooManyAttempts($identifier)) {
            return JsonResponse::ok(['error' => 'Too many login attempts. Please try again later.'], 429);
        }
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordLoginAttempt($identifier, false);
            return JsonResponse::ok(['error'=>'Invalid credentials'],401);
        }
        $this->recordLoginAttempt($identifier, true);
        $token = JWTAuth::issue(['uid'=>$user['id_user'],'role'=>$user['role']]);
        $refresh = $this->issueRefreshToken((int)$user['id_user']);
        return JsonResponse::ok([
            'access_token'=>$token,
            'refresh_token'=>$refresh,
            'user'=>[
                'id_user'=>$user['id_user'],
                'username'=>$user['username'],
                'email'=>$user['email'],
                'role'=>$user['role']
            ]
        ]);
    }

    public function refresh(Request $req): Response
    {
        $data = $req->getParsedBody();
        $token = $data['refresh_token'] ?? '';
        if (!$token) {
            return JsonResponse::ok(['error'=>'Invalid token'],401);
        }
        $hash = hash('sha256', $token);
        $stmt = DB::pdo()->prepare('SELECT rt.*, u.role FROM refresh_token rt JOIN users u ON u.id_user=rt.id_user WHERE rt.token_hash=? LIMIT 1');
        $stmt->execute([$hash]);
        $rt = $stmt->fetch();
        if (!$rt || $rt['revoked_at'] !== null) {
            return JsonResponse::ok(['error'=>'Invalid token'],401);
        }
        if (strtotime($rt['expires_at']) < time()) {
            return JsonResponse::ok(['error'=>'Token expired'],401);
        }
        // Optional binding checks
        if (!empty($_ENV['REFRESH_BIND_UA']) && $rt['user_agent'] && $rt['user_agent'] !== $this->userAgent()) {
            return JsonResponse::ok(['error'=>'Invalid token'],401);
        }
        if (!empty($_ENV['REFRESH_BIND_IP']) && $rt['ip'] && $rt['ip'] !== $this->clientIp()) {
            return JsonResponse::ok(['error'=>'Invalid token'],401);
        }
        $new = $this->rotateRefreshToken($token, (int)$rt['id_user']);
        $access = JWTAuth::issue(['uid'=>$rt['id_user'],'role'=>$rt['role']]);
        return JsonResponse::ok(['access_token'=>$access,'refresh_token'=>$new]);
    }

    public function users(Request $req): Response
    {
        return JsonResponse::ok(['data'=>User::all()]);
    }

    public function deleteMe(Request $req): Response
    {
        $claims = $req->getAttribute('user', []);
        $id = (int)($claims['uid'] ?? 0);
        if ($id <= 0) {
            return JsonResponse::ok(['error' => 'Unauthorized'], 401);
        }
        try {
            $ok = User::deleteById($id);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return JsonResponse::ok(['error' => 'Cannot delete user due to related data'], 409);
            }
            throw $e;
        }
        return JsonResponse::ok(['message' => $ok ? 'Deleted' : 'No changes']);
    }

    public function logout(Request $req): Response
    {
        $data = $req->getParsedBody();
        $token = $data['refresh_token'] ?? '';
        if ($token) {
            $hash = hash('sha256', $token);
            DB::pdo()->prepare('UPDATE refresh_token SET revoked_at=NOW() WHERE token_hash=?')->execute([$hash]);
        }
        return JsonResponse::ok(['message'=>'Logged out']);
    }

    public function forgotPassword(Request $req): Response
    {
        $email = strtolower(trim($req->getParsedBody()['email'] ?? ''));
        if ($email === '') return JsonResponse::ok(['error'=>'Email required'],422);
        $user = User::findByEmail($email);
        if ($user) {
            $ttl = (int)($_ENV['PASSWORD_RESET_TTL_SECONDS'] ?? 900);
            $t = bin2hex(random_bytes(32));
            $h = hash('sha256', $t);
            $expAt = date('Y-m-d H:i:s', time() + $ttl);
            DB::pdo()->prepare('INSERT INTO password_reset(id_user, token_hash, expires_at) VALUES(?, ?, ?)')
                ->execute([(int)$user['id_user'], $h, $expAt]);
            // In dev, return token; in prod, send email via provider (omitted)
            if (($_ENV['APP_ENV'] ?? 'local') === 'local' || !empty($_ENV['APP_DEBUG'])) {
                return JsonResponse::ok(['message'=>'Reset email sent','reset_token'=>$t]);
            }
        }
        return JsonResponse::ok(['message'=>'Reset email sent']);
    }

    public function resetPassword(Request $req): Response
    {
        $body = $req->getParsedBody();
        $token = $body['token'] ?? '';
        $new = $body['new_password'] ?? '';
        if (strlen($new) < 6 || $token === '') return JsonResponse::ok(['error'=>'Invalid input'],422);
        $h = hash('sha256', $token);
        $stmt = DB::pdo()->prepare('SELECT * FROM password_reset WHERE token_hash=? LIMIT 1');
        $stmt->execute([$h]);
        $row = $stmt->fetch();
        if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
            return JsonResponse::ok(['error'=>'Invalid or expired token'],400);
        }
        $hash = password_hash($new, PASSWORD_BCRYPT);
        DB::pdo()->prepare('UPDATE users SET password_hash=? WHERE id_user=?')->execute([$hash,(int)$row['id_user']]);
        DB::pdo()->prepare('UPDATE password_reset SET used_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        // Revoke all refresh tokens for safety
        DB::pdo()->prepare('UPDATE refresh_token SET revoked_at=NOW() WHERE id_user=? AND revoked_at IS NULL')->execute([(int)$row['id_user']]);
        return JsonResponse::ok(['message'=>'Password reset successful']);
    }

    public function changePassword(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $body = $req->getParsedBody();
        $current = (string)($body['current_password'] ?? '');
        $new = (string)($body['new_password'] ?? '');
        if (strlen($new) < 6) return JsonResponse::ok(['error'=>'New password too short'],422);
        $row = User::findById($uid);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            return JsonResponse::ok(['error'=>'Invalid current password'],400);
        }
        $hash = password_hash($new, PASSWORD_BCRYPT);
        DB::pdo()->prepare('UPDATE users SET password_hash=? WHERE id_user=?')->execute([$hash,$uid]);
        // Revoke all refresh tokens after password change
        DB::pdo()->prepare('UPDATE refresh_token SET revoked_at=NOW() WHERE id_user=? AND revoked_at IS NULL')->execute([$uid]);
        return JsonResponse::ok(['message'=>'Password changed']);
    }

    public function requestEmailVerification(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $ttl = (int)($_ENV['EMAIL_VERIFY_TTL_SECONDS'] ?? 86400);
        $t = bin2hex(random_bytes(32));
        $h = hash('sha256', $t);
        $expAt = date('Y-m-d H:i:s', time() + $ttl);
        DB::pdo()->prepare('INSERT INTO email_verification(id_user, token_hash, expires_at) VALUES(?, ?, ?)')
            ->execute([$uid,$h,$expAt]);
        $payload = ['message'=>'Verification email sent'];
        if (($_ENV['APP_ENV'] ?? 'local') === 'local' || !empty($_ENV['APP_DEBUG'])) {
            $payload['verify_email_token'] = $t;
        }
        return JsonResponse::ok($payload);
    }

    public function confirmEmailVerification(Request $req): Response
    {
        $token = (string)($req->getParsedBody()['token'] ?? '');
        if ($token === '') return JsonResponse::ok(['error'=>'Invalid token'],422);
        $h = hash('sha256', $token);
        $stmt = DB::pdo()->prepare('SELECT * FROM email_verification WHERE token_hash=? LIMIT 1');
        $stmt->execute([$h]);
        $row = $stmt->fetch();
        if (!$row || $row['verified_at'] !== null || strtotime($row['expires_at']) < time()) {
            return JsonResponse::ok(['error'=>'Invalid or expired token'],400);
        }
        DB::pdo()->prepare('UPDATE users SET email_verified_at=NOW() WHERE id_user=?')->execute([(int)$row['id_user']]);
        DB::pdo()->prepare('UPDATE email_verification SET verified_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        return JsonResponse::ok(['message'=>'Email verified']);
    }
}
