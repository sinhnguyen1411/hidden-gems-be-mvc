<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Review;

class ReviewController
{
    public function list(Request $req, Response $res): void
    {
        $cafeId = (int)$req->params['id'];
        $page = max(1, (int)($req->query['page'] ?? 1));
        $per = min(50, max(1, (int)($req->query['per_page'] ?? 10)));
        $data = Review::listByCafe($cafeId,$page,$per);
        $res->json(['data'=>$data]);
    }

    public function create(Request $req, Response $res): void
    {
        $cafeId = (int)$req->params['id'];
        $rating = (int)($req->body['rating'] ?? 0);
        $content = trim($req->body['content'] ?? '');
        $userId = (int)($req->user['uid'] ?? 0);
        if ($rating < 1 || $rating > 5 || !$content) {
            $res->json(['error'=>'Invalid input'],422);
            return;
        }
        $id = Review::create($userId,$cafeId,$rating,$content);
        $res->json(['message'=>'Created','review_id'=>$id],201);
    }
}
