<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Cafe;
use App\Models\Image;

class StoreController
{
    public function create(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $data = $req->getParsedBody();
        $name = trim($data['ten_cua_hang'] ?? '');
        $desc = $data['mo_ta'] ?? null;
        $statusId = isset($data['id_trang_thai']) ? (int)$data['id_trang_thai'] : null;
        if ($statusId === null) {
            $stmt = \App\Core\DB::pdo()->prepare("SELECT id_trang_thai FROM status WHERE nhom_trang_thai='cua_hang' AND ten_trang_thai='dang_cho' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            $statusId = $row ? (int)$row['id_trang_thai'] : null;
        }
        $locationId = isset($data['id_vi_tri']) ? (int)$data['id_vi_tri'] : null;
        if ($name === '') {
            return (new Response())->json(['error'=>'Invalid input'],422);
        }
        $id = Cafe::create($uid,$name,$desc,$statusId,$locationId,null);
        return (new Response())->json(['message'=>'Store created','id_cua_hang'=>$id],201);
    }

    public function update(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $id = (int)$req->getAttribute('id');
        $store = Cafe::find($id);
        if (!$store) {
            return (new Response())->json(['error'=>'Not found'],404);
        }
        if ((int)$store['id_chu_so_huu'] !== $uid && ($user['role'] ?? '') !== 'admin') {
            return (new Response())->json(['error'=>'Forbidden'],403);
        }
        $fields = $req->getParsedBody();
        $ok = Cafe::updateStore($id,$fields);
        return (new Response())->json(['message'=>$ok?'Updated':'No changes']);
    }

    public function createBranch(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $parentId = (int)$req->getAttribute('id');
        $parent = Cafe::find($parentId);
        if (!$parent) {
            return (new Response())->json(['error'=>'Parent not found'],404);
        }
        if ((int)$parent['id_chu_so_huu'] !== $uid && ($user['role'] ?? '') !== 'admin') {
            return (new Response())->json(['error'=>'Forbidden'],403);
        }
        $data = $req->getParsedBody();
        $name = trim($data['ten_cua_hang'] ?? ($parent['ten_cua_hang'].' - Chi nhanh'));
        $desc = $data['mo_ta'] ?? $parent['mo_ta'];
        $statusId = isset($data['id_trang_thai']) ? (int)$data['id_trang_thai'] : ($parent['id_trang_thai'] ?? null);
        $locationId = isset($data['id_vi_tri']) ? (int)$data['id_vi_tri'] : null;
        $id = Cafe::create((int)$parent['id_chu_so_huu'],$name,$desc,$statusId,$locationId,$parentId);
        return (new Response())->json(['message'=>'Branch created','id_cua_hang'=>$id],201);
    }

    public function myStores(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $q = $req->getQueryParams();
        $page = max(1,(int)($q['page'] ?? 1));
        $per = min(50, max(1, (int)($q['per_page'] ?? 10)));
        $data = Cafe::listOwned($uid,$page,$per);
        return (new Response())->json(['data'=>$data]);
    }

    public function uploadImage(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $storeId = (int)$req->getAttribute('id');
        $store = Cafe::find($storeId);
        if (!$store) return (new Response())->json(['error'=>'Not found'],404);
        if ((int)$store['id_chu_so_huu'] !== $uid && ($user['role'] ?? '') !== 'admin') {
            return (new Response())->json(['error'=>'Forbidden'],403);
        }
        $files = $req->getUploadedFiles();
        if (!isset($files['file'])) {
            return (new Response())->json(['error'=>'No file'],422);
        }
        try {
            $saved = \App\Core\Storage::saveUploadedFile($files['file'], 'stores');
        } catch (\Throwable $e) {
            return (new Response())->json(['error'=>'Upload failed'],500);
        }
        $imgId = Image::addForStore($storeId, $uid, $saved['url'], (bool)($req->getParsedBody()['is_avatar'] ?? false));
        return (new Response())->json(['message'=>'Uploaded','image_id'=>$imgId,'url'=>$saved['url']],201);
    }
}
