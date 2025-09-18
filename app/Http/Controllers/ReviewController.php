<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Review;

class ReviewController extends Controller
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

    public function updateStatus(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $body = $req->getParsedBody();
        $status = $body['trang_thai'] ?? '';
        $allowed = ['cho_duyet','da_duyet','tu_choi','an'];
        if (!in_array($status,$allowed,true)) {
            return JsonResponse::ok(['error'=>'Invalid status'],422);
        }
        $user = $req->getAttribute('user', []);
        $moderatorId = (int)($user['uid'] ?? 0);
        $ok = Review::updateStatus($id,$status,$moderatorId);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }
}