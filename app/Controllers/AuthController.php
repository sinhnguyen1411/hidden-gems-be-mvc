<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth as JWTAuth;
use App\Models\User;
use App\Core\Validator;

class AuthController
{
    public function register(Request $req, Response $res): void
    {
        $data = $req->getParsedBody();
        $errors = Validator::validate($data,[
            'ten_dang_nhap'=>'required',
            'email'=>'required|email',
            'password'=>'required|min:6'
        ]);
        if ($errors) { $res->json(['error'=>'Invalid input','details'=>$errors],422); return; }
        $email = strtolower($data['email']);
        if (User::findByEmail($email)) { $res->json(['error'=>'Email already in use'],409); return; }
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $id = User::create($data['ten_dang_nhap'], $email, $hash, 'customer');
        $res->json(['message' => 'Registered', 'user_id' => $id], 201);
    }

    public function login(Request $req, Response $res): void
    {
        $data = $req->getParsedBody();
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $user = $email ? User::findByEmail($email) : null;
        if (!$user || !password_verify($password, $user['mat_khau_ma_hoa'])) {
            $res->json(['error'=>'Invalid credentials'],401); return;
        }
        $token = JWTAuth::issue(['uid'=>$user['id_user'],'role'=>$user['vai_tro']]);
        $refresh = bin2hex(random_bytes(32));
        User::saveRefreshToken($user['id_user'],$refresh);
        $res->json([
            'access_token'=>$token,
            'refresh_token'=>$refresh,
            'user'=>[
                'id_user'=>$user['id_user'],
                'ten_dang_nhap'=>$user['ten_dang_nhap'],
                'email'=>$user['email'],
                'vai_tro'=>$user['vai_tro']
            ]
        ]);
    }

    public function refresh(Request $req, Response $res): void
    {
        $data = $req->getParsedBody();
        $token = $data['refresh_token'] ?? '';
        if (!$token) { $res->json(['error'=>'Invalid token'],401); return; }
        $user = User::findByRefreshToken($token);
        if (!$user) { $res->json(['error'=>'Invalid token'],401); return; }
        $access = JWTAuth::issue(['uid'=>$user['id_user'],'role'=>$user['vai_tro']]);
        $res->json(['access_token'=>$access]);
    }

    public function users(Request $req, Response $res): void
    {
        $res->json(['data'=>User::all()]);
    }
}
