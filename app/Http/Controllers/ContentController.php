<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;
use App\Core\Cache;

class ContentController extends Controller
{
    private const ALLOWED = ['about','testimonials'];

    private function resolveSlug(Request $req): ?string
    {
        $slug = $req->getAttribute('slug');
        if (!is_string($slug)) {
            return null;
        }
        $slug = strtolower(trim($slug));
        return in_array($slug, self::ALLOWED, true) ? $slug : null;
    }

    private function storagePath(string $slug): string
    {
        $root = dirname(__DIR__, 3);
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'content';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $slug . '.md';
    }

    public function show(Request $req): Response
    {
        $slug = $this->resolveSlug($req);
        if ($slug === null) {
            return JsonResponse::ok(['error'=>'Not found'],404);
        }
        $ttl = (int)($_ENV['CONTENT_CACHE_TTL'] ?? 120);
        $data = Cache::remember('content:'.$slug, $ttl, function() use ($slug) {
            $path = $this->storagePath($slug);
            if (!is_file($path)) {
                return [
                    'content' => '',
                    'updated_at' => null,
                ];
            }
            $body = (string)file_get_contents($path);
            return [
                'content' => $body,
                'updated_at' => date(DATE_ATOM, (int)filemtime($path)),
            ];
        });
        return JsonResponse::ok([
            'data' => [
                'slug' => $slug,
                'content' => $data['content'] ?? '',
                'updated_at' => $data['updated_at'] ?? null,
            ]
        ]);
    }

    public function update(Request $req): Response
    {
        $slug = $this->resolveSlug($req);
        if ($slug === null) {
            return JsonResponse::ok(['error'=>'Not found'],404);
        }
        $body = $req->getParsedBody();
        $content = (string)($body['content'] ?? '');
        $path = $this->storagePath($slug);
        $bytes = @file_put_contents($path, $content);
        if ($bytes === false) {
            return JsonResponse::ok(['error'=>'Unable to persist content'],500);
        }
        $snapshot = [
            'content' => $content,
            'updated_at' => date(DATE_ATOM),
        ];
        $ttl = (int)($_ENV['CONTENT_CACHE_TTL'] ?? 120);
        Cache::set('content:'.$slug, $snapshot, $ttl);
        return JsonResponse::ok(['message'=>'Updated','data'=>$snapshot]);
    }
}