<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Storage;
use App\Models\Message;

class ChatController extends Controller
{
    private function findAdminId(): ?int
    {
        $row = \App\Core\DB::pdo()->query("SELECT id_user FROM users WHERE role='admin' LIMIT 1")->fetch();
        return $row ? (int)$row['id_user'] : null;
    }

    public function send(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $from = (int)($user['uid'] ?? 0);
        $data = $req->getParsedBody();
        $to = isset($data['to_user_id']) ? (int)$data['to_user_id'] : null;
        if (!$to) {
            $to = $this->findAdminId();
        }
        $content = trim($data['noi_dung'] ?? '');
        if (!$to || $content==='') return JsonResponse::ok(['error'=>'Invalid input'],422);
        $id = Message::send($from,$to,$content,'text');
        return JsonResponse::ok(['message'=>'Sent','id_tin_nhan'=>$id],201);
    }

    public function messages(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $q = $req->getQueryParams();
        $with = isset($q['with']) ? (int)$q['with'] : $this->findAdminId();
        $limit = min(100, max(1,(int)($q['limit'] ?? 50)));
        $offset = max(0,(int)($q['offset'] ?? 0));
        if (!$with) return JsonResponse::ok(['error'=>'No counterpart'],422);
        $msgs = Message::between($uid,$with,$limit,$offset);
        return JsonResponse::ok(['data'=>$msgs]);
    }

    public function conversations(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $rows = Message::conversationsFor($uid);
        return JsonResponse::ok(['data'=>$rows]);
    }

    public function markRead(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $body = $req->getParsedBody();
        $from = isset($body['from_user_id']) ? (int)$body['from_user_id'] : null;
        $ids = [];
        if (isset($body['message_ids']) && is_array($body['message_ids'])) {
            foreach ($body['message_ids'] as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $updated = Message::markRead($uid, $from, $ids);
        return JsonResponse::ok(['message'=>'Updated','updated'=>$updated]);
    }

    public function unreadCounts(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $rows = Message::unreadCounts($uid);
        return JsonResponse::ok(['data'=>$rows]);
    }

    public function sendFile(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $from = (int)($user['uid'] ?? 0);
        $data = $req->getParsedBody();
        $to = isset($data['to_user_id']) ? (int)$data['to_user_id'] : null;
        if (!$to) {
            $to = $this->findAdminId();
        }
        $files = $req->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$to || !$file) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        try {
            $saved = Storage::saveUploadedFile($file, 'chat');
        } catch (\Throwable $e) {
            return JsonResponse::ok(['error'=>'Upload failed'],500);
        }
        $caption = trim($data['caption'] ?? '');
        $payload = [
            'url' => $saved['url'],
            'filename' => $saved['filename'],
            'original' => $saved['original'] ?? null,
            'size' => (int)($file['size'] ?? 0),
        ];
        if ($caption !== '') {
            $payload['caption'] = $caption;
        }
        $id = Message::send($from,$to,$caption,'file',$payload);
        return JsonResponse::ok([
            'message' => 'Sent',
            'id_tin_nhan' => $id,
            'url' => $payload['url'],
        ],201);
    }
}