<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth as JWTAuth;
use App\Models\User;

class AuthController
{
    public function register(Request $req, Response $res): void
    {
        $name = trim($req->body['name'] ?? '');
        $email = strtolower(trim($req->body['email'] ?? ''));
        $password = $req->body['password'] ?? '';
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $res->json(['error'=>'Invalid input'],422);
            return;
        }
        if (User::findByEmail($email)) {
            $res->json(['error'=>'Email already in use'],409);
            return;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id = User::create($name,$email,$hash);
        $res->json(['message'=>'Registered','user_id'=>$id],201);
    }

    public function login(Request $req, Response $res): void
    {
        $email = strtolower(trim($req->body['email'] ?? ''));
        $password = $req->body['password'] ?? '';
        $user = $email ? User::findByEmail($email) : null;
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $res->json(['error'=>'Invalid credentials'],401);
            return;
        }
        $token = JWTAuth::issue(['uid'=>$user['id'],'role'=>$user['role']]);
        $res->json(['access_token'=>$token,'user'=>[
            'id'=>$user['id'], 'name'=>$user['name'], 'email'=>$user['email'], 'role'=>$user['role']
        ]]);
    }
}
