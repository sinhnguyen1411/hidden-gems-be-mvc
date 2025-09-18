<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Comment;

class CommentController extends Controller
{
    public function index(Request $req): Response
    {
        $query = $req->getQueryParams();
        $page = max(1,(int)($query['page'] ?? 1));
        $per = min(100, max(1,(int)($query['per_page'] ?? 20)));
        $filters = [
            'q' => trim((string)($query['q'] ?? '')),
            'status' => $query['trang_thai'] ?? null,
            'type' => $query['loai'] ?? ($query['type'] ?? null),
        ];
        $data = Comment::paginateAdmin($filters,$page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function update(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $payload = $req->getParsedBody();
        $status = $payload['trang_thai'] ?? '';
        $allowed = ['cho_duyet','da_duyet','tu_choi','an'];
        if (!in_array($status,$allowed,true)) {
            return JsonResponse::ok(['error'=>'Invalid status'],422);
        }
        $ok = Comment::updateStatus($id,$status);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }
}