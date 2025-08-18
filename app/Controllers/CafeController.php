<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Cafe;

class CafeController
{
    public function index(Request $req, Response $res): void
    {
        $page = max(1, (int)($req->query['page'] ?? 1));
        $per = min(50, max(1, (int)($req->query['per_page'] ?? 10)));
        $category = isset($req->query['category_id']) ? (int)$req->query['category_id'] : null;
        $data = Cafe::paginate($page,$per,$category);
        $res->json(['data'=>$data]);
    }

    public function show(Request $req, Response $res): void
    {
        $id = (int)$req->params['id'];
        $cafe = Cafe::find($id);
        if (!$cafe) { $res->json(['error'=>'Not found'],404); return; }
        $res->json(['data'=>$cafe]);
    }
}
