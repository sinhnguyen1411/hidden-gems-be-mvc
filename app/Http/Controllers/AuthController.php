<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Auth as JWTAuth;
use App\Models\User;
use App\Core\Validator;

class AuthController extends Controller
{
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
        return JsonResponse::ok(['message' => 'Registered', 'user_id' => $id], 201);
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
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return JsonResponse::ok(['error'=>'Invalid credentials'],401);
        }
        $token = JWTAuth::issue(['uid'=>$user['id_user'],'role'=>$user['role']]);
        $refresh = bin2hex(random_bytes(32));
        User::saveRefreshToken($user['id_user'],$refresh);
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
        $user = User::findByRefreshToken($token);
        if (!$user) {
            return JsonResponse::ok(['error'=>'Invalid token'],401);
        }
        $access = JWTAuth::issue(['uid'=>$user['id_user'],'role'=>$user['role']]);
        return JsonResponse::ok(['access_token'=>$access]);
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
}
