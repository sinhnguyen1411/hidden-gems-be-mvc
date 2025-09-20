<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Storage;
use App\Models\MediaUpload;
use App\Models\Banner;

class BannerController extends Controller
{
    public function list(Request $req): Response
    {
        $q = $req->getQueryParams();
        $pos = $q['vi_tri'] ?? null;
        $active = !isset($q['active']) || (int)$q['active'] === 1;
        $ttl = (int)($_ENV['BANNERS_CACHE_TTL'] ?? 60);
        $cacheKey = 'banners:list:' . ($pos ?: '_all') . ':' . ($active ? '1':'0');
        $rows = \App\Core\Cache::remember($cacheKey, $ttl, function() use ($pos,$active){
            return Banner::list($pos,$active);
        });
        return JsonResponse::ok(['data'=>$rows]);
    }

    public function create(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uploaderId = (int)($user['uid'] ?? 0) ?: null;

        $data = $req->getParsedBody();
        $title = $data['tieu_de'] ?? '';
        $desc = $data['mo_ta'] ?? null;
        $link = $data['link_url'] ?? null;
        $pos = $data['vi_tri'] ?? null;
        $order = (int)($data['thu_tu'] ?? 0);
        $active = !isset($data['active']) || (int)$data['active'] === 1;

        $files = $req->getUploadedFiles();
        $imageUrl = $data['url_anh'] ?? null;
        if (!$imageUrl && isset($files['file'])) {
            $saved = Storage::saveUploadedFile($files['file'], 'banners');
            $imageUrl = $saved['url'];
            try {
                $meta = ['action' => 'create'];
                if ($title !== '') {
                    $meta['title'] = $title;
                }
                if ($link) {
                    $meta['link_url'] = $link;
                }
                MediaUpload::record($uploaderId, 'banner', $saved, [
                    'size' => (int)($files['file']['size'] ?? 0),
                    'meta' => $meta,
                ]);
            } catch (\Throwable $e) {
                // ignore logging errors
            }
        }
        if (!$imageUrl) return JsonResponse::ok(['error'=>'Image required'],422);

        $id = Banner::create($title,$desc,$imageUrl,$link,$pos,$order,$active);
        Banner::touchCacheHint();
        return JsonResponse::ok(['message'=>'Banner created','id_banner'=>$id,'url_anh'=>$imageUrl],201);
    }

    public function update(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uploaderId = (int)($user['uid'] ?? 0) ?: null;

        $id = (int)$req->getAttribute('id');
        $data = $req->getParsedBody();
        $fields = [];
        foreach (['tieu_de','mo_ta','url_anh','link_url','vi_tri','thu_tu','active'] as $f) {
            if (array_key_exists($f,$data)) $fields[$f] = $data[$f];
        }
        $files = $req->getUploadedFiles();
        if (isset($files['file'])) {
            $saved = Storage::saveUploadedFile($files['file'], 'banners');
            $fields['url_anh'] = $saved['url'];
            try {
                MediaUpload::record($uploaderId, 'banner', $saved, [
                    'size' => (int)($files['file']['size'] ?? 0),
                    'meta' => ['action' => 'update', 'banner_id' => $id],
                ]);
            } catch (\Throwable $e) {
                // ignore logging failures
            }
        }
        $ok = $fields ? Banner::update($id,$fields) : false;
        if ($ok) {
            Banner::touchCacheHint();
        }
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }

    public function reorder(Request $req): Response
    {
        $body = $req->getParsedBody();
        $order = $body['order'] ?? null;
        if (!is_array($order) || !$order) {
            return JsonResponse::ok(['error'=>'Invalid order payload'],422);
        }
        $ids = array_values(array_filter(array_map('intval',$order), function ($id) { return $id > 0; }));
        if (!$ids) {
            return JsonResponse::ok(['error'=>'Invalid order payload'],422);
        }
        $updated = Banner::reorder($ids);
        Banner::touchCacheHint();
        return JsonResponse::ok(['message'=>'Updated','updated'=>$updated]);
    }

    public function delete(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $ok = Banner::delete($id);
        if ($ok) {
            Banner::touchCacheHint();
        }
        return JsonResponse::ok(['message'=>$ok?'Deleted':'No changes']);
    }
}
