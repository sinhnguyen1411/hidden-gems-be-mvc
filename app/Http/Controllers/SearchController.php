<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Models\Cafe;
use App\Models\Blog;
use App\Models\Voucher;
use App\Models\Promotion;

class SearchController extends Controller
{
    public function global(Request $req): Response
    {
        $q = trim($req->getQueryParams()['q'] ?? '');
        if ($q === '') {
            return JsonResponse::ok(['error'=>'Missing query'],422);
        }
        $per = min(10, max(1, (int)($req->getQueryParams()['per_cat'] ?? 5)));
        $ttl = (int)($_ENV['SEARCH_CACHE_TTL'] ?? 15);
        $stores = \App\Core\Cache::remember('search:stores:'.$q.':'.$per, $ttl, fn()=> Cafe::search($q, 1, $per));
        $blogs = \App\Core\Cache::remember('search:blogs:'.$q.':'.$per, $ttl, fn()=> Blog::search($q, 1, $per));
        $vouchers = \App\Core\Cache::remember('search:vouchers:'.$q.':'.$per, $ttl, fn()=> Voucher::search($q, 1, $per));
        $promos = \App\Core\Cache::remember('search:promos:'.$q.':'.$per, $ttl, fn()=> Promotion::search($q, 1, $per));
        return JsonResponse::ok([
            'query' => $q,
            'stores' => $stores['items'],
            'blogs' => $blogs['items'],
            'vouchers' => $vouchers['items'],
            'promotions' => $promos['items'],
        ]);
    }
}
