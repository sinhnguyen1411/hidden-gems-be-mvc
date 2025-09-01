<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Blog;

class BlogController
{
    public function list(Request $req): Response
    {
        $q = $req->getQueryParams();
        $page = max(1,(int)($q['page'] ?? 1));
        $per = min(50, max(1,(int)($q['per_page'] ?? 10)));
        $term = trim($q['q'] ?? '');
        if ($term==='') {
            // simple list without filter
            $offset = ($page-1)*$per;
            $stmt = \App\Core\DB::pdo()->prepare('SELECT b.*, u.username AS author FROM blog b JOIN users u ON u.id_user=b.id_user ORDER BY b.id_blog DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1,$per,\PDO::PARAM_INT);
            $stmt->bindValue(2,$offset,\PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();
            $count = (int)\App\Core\DB::pdo()->query('SELECT COUNT(*) FROM blog')->fetchColumn();
            return JsonResponse::ok(['data'=>['items'=>$items,'total'=>$count,'page'=>$page,'per_page'=>$per]]);
        }
        return JsonResponse::ok(['data'=>Blog::search($term,$page,$per)]);
    }

    public function create(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $data = $req->getParsedBody();
        $title = trim($data['tieu_de'] ?? '');
        $content = trim($data['noi_dung'] ?? '');
        if ($title==='' || $content==='') return JsonResponse::ok(['error'=>'Invalid input'],422);
        $id = Blog::create($uid,$title,$content);
        return JsonResponse::ok(['message'=>'Blog created','id_blog'=>$id],201);
    }

    public function update(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $title = trim($data['tieu_de'] ?? '');
        $content = trim($data['noi_dung'] ?? '');
        if ($title==='' || $content==='') return JsonResponse::ok(['error'=>'Invalid input'],422);
        $ok = Blog::update($id,$title,$content);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }
}
