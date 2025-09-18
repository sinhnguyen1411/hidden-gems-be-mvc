<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Validator;
use App\Models\Blog;

class BlogController extends Controller
{
    public function list(Request $req): Response
    {
        $q = $req->getQueryParams();
        $page = max(1,(int)($q['page'] ?? 1));
        $per = min(50, max(1,(int)($q['per_page'] ?? 10)));
        $term = trim($q['q'] ?? '');
        $data = Blog::paginatePublic($term,$page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function create(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $data = $req->getParsedBody();
        $errors = Validator::validate($data,[
            'tieu_de' => 'required',
            'noi_dung' => 'required|min:1'
        ]);
        if ($errors) return JsonResponse::ok(['error'=>'Invalid input','details'=>$errors],422);
        $title = trim($data['tieu_de']);
        $content = trim($data['noi_dung']);
        $status = $data['trang_thai'] ?? null;
        $id = Blog::create($uid,$title,$content,$status);
        return JsonResponse::ok(['message'=>'Blog created','id_blog'=>$id],201);
    }

    public function update(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $errors = Validator::validate($data,[
            'tieu_de' => 'required',
            'noi_dung' => 'required|min:1'
        ]);
        if ($errors) return JsonResponse::ok(['error'=>'Invalid input','details'=>$errors],422);
        $title = trim($data['tieu_de']);
        $content = trim($data['noi_dung']);
        $status = $data['trang_thai'] ?? null;
        $ok = Blog::update($id,$title,$content,$status);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }

    public function updateStatus(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $status = $data['trang_thai'] ?? '';
        $allowed = ['nhap','cong_bo','an'];
        if (!in_array($status,$allowed,true)) {
            return JsonResponse::ok(['error'=>'Invalid status'],422);
        }
        $ok = Blog::updateStatus($id,$status);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }
}