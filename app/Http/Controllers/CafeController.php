<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Cache;
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

    public function nearby(Request $req): Response
    {
        $params = $req->getQueryParams();
        $lat = isset($params['lat']) ? (float)$params['lat'] : null;
        $lng = isset($params['lng']) ? (float)$params['lng'] : null;
        if ($lat === null || $lng === null) {
            return JsonResponse::ok(['error'=>'Missing coordinates'],422);
        }
        $radius = isset($params['radius_km']) ? (float)$params['radius_km'] : (float)($_ENV['CAFES_NEAR_DEFAULT_RADIUS'] ?? 5.0);
        $radius = max(0.1, min(50.0, $radius));
        $limit = min(100, max(1, (int)($params['limit'] ?? 20)));
        $cacheKey = sprintf('cafes:near:%.4f:%.4f:%.2f:%d',$lat,$lng,$radius,$limit);
        $ttl = (int)($_ENV['CAFES_NEAR_CACHE_TTL'] ?? 60);
        $items = Cache::remember($cacheKey, $ttl, function() use ($lat,$lng,$radius,$limit){
            return Cafe::nearby($lat,$lng,$radius,$limit);
        });
        if (!is_array($items)) {
            $items = [];
        }
        return JsonResponse::ok(['data'=>[
            'items' => $items,
            'center' => ['lat'=>$lat,'lng'=>$lng],
            'radius_km' => $radius,
            'limit' => $limit,
            'count' => count($items),
        ]]);
    }
}

