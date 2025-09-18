<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\DB;

class AdminController extends Controller
{
    public function dashboard(Request $req): Response
    {
        $ttl = (int)($_ENV['DASHBOARD_CACHE_TTL'] ?? 30);
        $data = \App\Core\Cache::remember('admin:dashboard', $ttl, function(){
            $pdo = DB::pdo();
            $users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $shops = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='shop'")->fetchColumn();
            $stores = (int)$pdo->query("SELECT COUNT(*) FROM cua_hang")->fetchColumn();
            $reviews = (int)$pdo->query("SELECT COUNT(*) FROM danh_gia")->fetchColumn();
            $vouchers = (int)$pdo->query("SELECT COUNT(*) FROM voucher")->fetchColumn();
            $promos = (int)$pdo->query("SELECT COUNT(*) FROM khuyen_mai")->fetchColumn();
            return compact('users','shops','stores','reviews','vouchers','promos');
        });
        return JsonResponse::ok(['data'=>$data]);
    }

    public function listUsers(Request $req): Response
    {
        $query = $req->getQueryParams();
        $page = max(1,(int)($query['page'] ?? 1));
        $per = min(100,max(1,(int)($query['per_page'] ?? 20)));
        $filters = [
            'q' => trim((string)($query['q'] ?? '')),
            'role' => $query['role'] ?? null,
        ];
        $data = \App\Models\User::search($filters,$page,$per);
        return JsonResponse::ok(['data'=>$data]);
    }

    public function search(Request $req): Response
    {
        $query = $req->getQueryParams();
        $domain = $query['domain'] ?? '';
        $term = trim((string)($query['q'] ?? ''));
        $status = $query['trang_thai'] ?? null;
        $page = max(1,(int)($query['page'] ?? 1));
        $per = min(100,max(1,(int)($query['per_page'] ?? 20)));
        switch ($domain) {
            case 'reviews':
                $data = \App\Models\Review::searchForAdmin($term,$status,$page,$per);
                break;
            case 'promotions':
                $data = \App\Models\Promotion::searchAdmin($term,$status,$page,$per);
                break;
            case 'users':
                $filters = ['q'=>$term,'role'=>$query['role'] ?? null];
                $data = \App\Models\User::search($filters,$page,$per);
                break;
            default:
                return JsonResponse::ok(['error'=>'Unsupported domain'],422);
        }
        return JsonResponse::ok(['data'=>$data]);
    }

    public function reportsSummary(Request $req): Response
    {
        $query = $req->getQueryParams();
        $from = $query['from'] ?? null;
        $to = $query['to'] ?? null;
        $format = strtolower($query['format'] ?? 'json');
        $pdo = DB::pdo();

        $summary = [
            'period' => ['from'=>$from,'to'=>$to],
            'users' => [
                'total' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'new' => $this->countInRange('users','joined_at',$from,$to),
            ],
            'stores' => [
                'total' => (int)$pdo->query('SELECT COUNT(*) FROM cua_hang')->fetchColumn(),
                'new' => $this->countInRange('cua_hang','ngay_tao',$from,$to),
            ],
            'reviews' => [
                'total' => (int)$pdo->query('SELECT COUNT(*) FROM danh_gia')->fetchColumn(),
                'new' => $this->countInRange('danh_gia','thoi_gian_tao',$from,$to),
            ],
            'promotions' => [
                'total' => (int)$pdo->query('SELECT COUNT(*) FROM khuyen_mai')->fetchColumn(),
                'new' => $this->countInRange('khuyen_mai','ngay_bat_dau',$from,$to),
                'global_active' => (int)$pdo->query("SELECT COUNT(*) FROM khuyen_mai WHERE pham_vi_ap_dung='toan_he_thong' AND trang_thai='dang_hoat_dong'")->fetchColumn(),
            ],
            'vouchers' => [
                'total' => (int)$pdo->query('SELECT COUNT(*) FROM voucher')->fetchColumn(),
                'global_total' => (int)$pdo->query('SELECT COUNT(*) FROM voucher WHERE is_global=1')->fetchColumn(),
            ],
        ];

        if ($format === 'csv') {
            $lines = ['metric,value'];
            $lines[] = 'period.from,'.($from ?? '');
            $lines[] = 'period.to,'.($to ?? '');
            $lines[] = 'users.total,'.$summary['users']['total'];
            $lines[] = 'users.new,'.$summary['users']['new'];
            $lines[] = 'stores.total,'.$summary['stores']['total'];
            $lines[] = 'stores.new,'.$summary['stores']['new'];
            $lines[] = 'reviews.total,'.$summary['reviews']['total'];
            $lines[] = 'reviews.new,'.$summary['reviews']['new'];
            $lines[] = 'promotions.total,'.$summary['promotions']['total'];
            $lines[] = 'promotions.new,'.$summary['promotions']['new'];
            $lines[] = 'promotions.global_active,'.$summary['promotions']['global_active'];
            $lines[] = 'vouchers.total,'.$summary['vouchers']['total'];
            $lines[] = 'vouchers.global_total,'.$summary['vouchers']['global_total'];
            $csv = implode("\n", $lines);
            $response = (new Response())->raw($csv,200,'text/csv; charset=utf-8');
            $filename = 'reports-summary-' . date('Ymd_His') . '.csv';
            $response->withHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }
        return JsonResponse::ok(['data'=>$summary]);
    }

    public function setRole(Request $req): Response
    {
        $data = $req->getParsedBody();
        $userId = (int)($data['id_user'] ?? 0);
        $role = $data['role'] ?? '';
        if ($userId<=0 || !in_array($role,['admin','shop','customer'],true)) {
            return JsonResponse::ok(['error'=>'Invalid input'],422);
        }
        // Audit old role
        $old = DB::pdo()->prepare('SELECT role FROM users WHERE id_user=?');
        $old->execute([$userId]);
        $prev = $old->fetch();
        $stmt = DB::pdo()->prepare('UPDATE users SET role=? WHERE id_user=?');
        $stmt->execute([$role,$userId]);
        // Write audit log
        $actor = $req->getAttribute('user', []);
        $actorId = (int)($actor['uid'] ?? 0);
        $meta = json_encode(['from'=>$prev['role'] ?? null, 'to'=>$role], JSON_UNESCAPED_UNICODE);
        DB::pdo()->prepare('INSERT INTO audit_log(actor_user_id, action, target_type, target_id, meta) VALUES (?,?,?,?,?)')
            ->execute([$actorId,'set_role','user',$userId,$meta]);
        return JsonResponse::ok(['message'=>'Role updated']);
    }

    public function pendingStores(Request $req): Response
    {
        $stmt = DB::pdo()->prepare("SELECT s.* FROM cua_hang s JOIN status st ON st.id_trang_thai=s.id_trang_thai WHERE st.ten_trang_thai='dang_cho' AND st.nhom_trang_thai='cua_hang' ORDER BY s.id_cua_hang DESC");
        $stmt->execute();
        return JsonResponse::ok(['data'=>$stmt->fetchAll()]);
    }

    public function approveStore(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        $action = $req->getParsedBody()['action'] ?? 'approve';
        $statusName = $action === 'reject' ? 'dong_cua' : 'hoat_dong';
        $stmt = DB::pdo()->prepare("SELECT id_trang_thai FROM status WHERE nhom_trang_thai='cua_hang' AND ten_trang_thai=? LIMIT 1");
        $stmt->execute([$statusName]);
        $st = $stmt->fetch();
        if (!$st) return JsonResponse::ok(['error'=>'Status not configured'],500);
        $ok = DB::pdo()->prepare('UPDATE cua_hang SET id_trang_thai=? WHERE id_cua_hang=?')->execute([(int)$st['id_trang_thai'],$id]);
        $actor = $req->getAttribute('user', []);
        $actorId = (int)($actor['uid'] ?? 0);
        $meta = json_encode(['action'=>$action, 'status'=>$statusName], JSON_UNESCAPED_UNICODE);
        DB::pdo()->prepare('INSERT INTO audit_log(actor_user_id, action, target_type, target_id, meta) VALUES (?,?,?,?,?)')
            ->execute([$actorId,'approve_store','store',$id,$meta]);
        return JsonResponse::ok(['message'=>$ok?'Updated':'No changes']);
    }

    public function contact(Request $req): Response
    {
        return JsonResponse::ok([
            'email' => $_ENV['CONTACT_EMAIL'] ?? null,
            'zalo' => $_ENV['CONTACT_ZALO'] ?? null,
            'phone' => $_ENV['CONTACT_PHONE'] ?? null,
        ]);
    }

    public function deleteUser(Request $req): Response
    {
        $id = (int)$req->getAttribute('id');
        if ($id <= 0) {
            return JsonResponse::ok(['error' => 'Invalid user id'], 422);
        }
        try {
            $ok = \App\Models\User::deleteById($id);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return JsonResponse::ok(['error' => 'Cannot delete user due to related data'], 409);
            }
            throw $e;
        }
        return JsonResponse::ok(['message' => $ok ? 'Deleted' : 'No changes']);
    }

    private function countInRange(string $table, string $column, ?string $from, ?string $to): int
    {
        $clauses = [];
        $params = [];
        $normFrom = $this->normalizeDate($from, false);
        $normTo = $this->normalizeDate($to, true);
        if ($normFrom) {
            $clauses[] = "$column >= ?";
            $params[] = $normFrom;
        }
        if ($normTo) {
            $clauses[] = "$column <= ?";
            $params[] = $normTo;
        }
        $sql = 'SELECT COUNT(*) FROM ' . $table;
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function normalizeDate(?string $value, bool $end=false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $dt = new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
        $trimmed = trim($value);
        if ($end && strlen($trimmed) <= 10) {
            $dt->setTime(23,59,59);
        } elseif (!$end && strlen($trimmed) <= 10) {
            $dt->setTime(0,0,0);
        }
        return $dt->format('Y-m-d H:i:s');
    }
}