<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Message;
use App\Models\User;

class ChatController
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
        if (!$to || $content==='') return (new Response())->json(['error'=>'Invalid input'],422);
        $id = Message::send($from,$to,$content);
        return (new Response())->json(['message'=>'Sent','id_tin_nhan'=>$id],201);
    }

    public function messages(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $q = $req->getQueryParams();
        $with = isset($q['with']) ? (int)$q['with'] : $this->findAdminId();
        $limit = min(100, max(1,(int)($q['limit'] ?? 50)));
        $offset = max(0,(int)($q['offset'] ?? 0));
        if (!$with) return (new Response())->json(['error'=>'No counterpart'],422);
        $msgs = Message::between($uid,$with,$limit,$offset);
        return (new Response())->json(['data'=>$msgs]);
    }

    public function conversations(Request $req): Response
    {
        $user = $req->getAttribute('user', []);
        $uid = (int)($user['uid'] ?? 0);
        $rows = Message::conversationsFor($uid);
        return (new Response())->json(['data'=>$rows]);
    }
}

