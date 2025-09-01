<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Review;

class ReviewController
{
    public function list(Request $req): Response
    {
        $cafeId = (int)$req->getAttribute('id');
        $query = $req->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));
        $per = min(50, max(1, (int)($query['per_page'] ?? 10)));
        $data = Review::listByCafe($cafeId,$page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function create(Request $req): Response
    {
        $cafeId = (int)$req->getAttribute('id');
        $body = $req->getParsedBody();
        $rating = (int)($body['rating'] ?? 0);
        $content = trim($body['content'] ?? '');
        $user = $req->getAttribute('user', []);
        $userId = (int)($user['uid'] ?? 0);
        if ($rating < 1 || $rating > 5 || !$content) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        $id = Review::create($userId,$cafeId,$rating,$content);
        return JsonResponse::ok(['message'=>'Created','review_id'=>$id],201);
    }
}
