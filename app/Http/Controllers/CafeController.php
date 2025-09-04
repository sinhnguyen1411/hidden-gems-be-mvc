<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Cafe;

class CafeController extends Controller
{
    public function index(Request $req): Response
    {
        $query = $req->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));
        $per = min(50, max(1, (int)($query['per_page'] ?? 10)));
        $category = isset($query['category_id']) ? (int)$query['category_id'] : null;
        $data = Cafe::paginate($page,$per,$category);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function search(Request $req): Response
    {
        $query = $req->getQueryParams();
        $term = trim($query['q'] ?? '');
        if ($term === '') {
            return JsonResponse::ok(['error'=>'Missing query'],422);
        }
        $page = max(1, (int)($query['page'] ?? 1));
        $per = min(50, max(1, (int)($query['per_page'] ?? 10)));
        $data = Cafe::search($term,$page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function show(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $cafe = Cafe::find($id);
        if (!$cafe) {
            return JsonResponse::ok(['error'=>'Not found'],404);
        }
        return JsonResponse::ok(['data'=>$cafe]);
    }
}
