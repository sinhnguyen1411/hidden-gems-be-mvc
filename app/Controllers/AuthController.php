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
        $data['role'] = $data['role'] ?? 'customer';
        $errors = Validator::validate($data,[
            'name'=>'required',
            'email'=>'required|email',
            'password'=>'required|min:6',
            'role'=>'in:admin,shop,customer'
        ]);
        if ($errors) { $res->json(['error'=>'Invalid input','details'=>$errors],422); return; }
        $email = strtolower($data['email']);
        if (User::findByEmail($email)) { $res->json(['error'=>'Email already in use'],409); return; }
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $id = User::create($data['name'],$email,$hash,$data['role']);
        $res->json(['message'=>'Registered','user_id'=>$id],201);
    }

    public function login(Request $req, Response $res): void
    {
        $data = $req->getParsedBody();
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $user = $email ? User::findByEmail($email) : null;
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $res->json(['error'=>'Invalid credentials'],401); return;
        }
        $token = JWTAuth::issue(['uid'=>$user['id'],'role'=>$user['role']]);
        $refresh = bin2hex(random_bytes(32));
        User::saveRefreshToken($user['id'],$refresh);
        $res->json(['access_token'=>$token,'refresh_token'=>$refresh,'user'=>[
            'id'=>$user['id'], 'name'=>$user['name'], 'email'=>$user['email'], 'role'=>$user['role']
        ]]);
    }

    public function refresh(Request $req, Response $res): void
    {
        $data = $req->getParsedBody();
        $token = $data['refresh_token'] ?? '';
        if (!$token) { $res->json(['error'=>'Invalid token'],401); return; }
        $user = User::findByRefreshToken($token);
        if (!$user) { $res->json(['error'=>'Invalid token'],401); return; }
        $access = JWTAuth::issue(['uid'=>$user['id'],'role'=>$user['role']]);
        $res->json(['access_token'=>$access]);
    }

    public function users(Request $req, Response $res): void
    {
        $res->json(['data'=>User::all()]);
    }
}
