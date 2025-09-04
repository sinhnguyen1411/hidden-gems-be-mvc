<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Storage;
use App\Models\Banner;

class BannerController extends Controller
{
    public function list(Request $req): Response
    {
        $q = $req->getQueryParams();
        $pos = $q['vi_tri'] ?? null;
        $active = !isset($q['active']) || (int)$q['active'] === 1;
        $rows = Banner::list($pos,$active);
        return JsonResponse::ok(['data'=>$rows]);
    }

    public function create(Request $req): Response
    {
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
        }
        if (!$imageUrl) return JsonResponse::ok(['error'=>'Image required'],422);

        $id = Banner::create($title,$desc,$imageUrl,$link,$pos,$order,$active);
        return JsonResponse::ok(['message'=>'Banner created','id_banner'=>$id,'url_anh'=>$imageUrl],201);
    }

    public function update(Request $req): Response
    {
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
        }
        $ok = $fields ? Banner::update($id,$fields) : false;
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }
}
