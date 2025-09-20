<?php
namespace App\Models;

use App\Core\DB;

class MediaUpload
{
    /**
     * Record an uploaded media asset for auditing/reuse.
     *
     * @param int|null $uploaderId Authenticated user id, if available.
     * @param string $context Logical context identifier (e.g. banner, store_image).
     * @param array $fileMeta Expected keys: url (required), path, filename, original.
     * @param array $extra Optional keys: size (int), meta (array|string), overrides for url/path/etc.
     */
    public static function record(?int $uploaderId, string $context, array $fileMeta, array $extra = []): ?int
    {
        $url = $extra['url'] ?? ($fileMeta['url'] ?? null);
        if (!$url) {
            return null;
        }
        $path = $extra['path'] ?? ($fileMeta['path'] ?? null);
        $filename = $extra['filename'] ?? ($fileMeta['filename'] ?? null);
        $original = $extra['original'] ?? ($fileMeta['original'] ?? null);
        $size = $extra['size'] ?? ($fileMeta['size'] ?? null);
        $meta = $extra['meta'] ?? null;
        if (is_array($meta)) {
            $meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }
        $context = trim($context);
        if ($context === '') {
            $context = 'general';
        }
        if (function_exists('mb_substr')) {
            $context = mb_substr($context, 0, 50);
        } else {
            $context = substr($context, 0, 50);
        }
        $sizeBytes = $size !== null ? (int)$size : null;
        $stmt = DB::pdo()->prepare('INSERT INTO media_upload(uploader_id, context, url, path, filename, original_name, size_bytes, meta) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $uploaderId ?: null,
            $context,
            $url,
            $path,
            $filename,
            $original,
            $sizeBytes,
            $meta,
        ]);
        return (int)DB::pdo()->lastInsertId();
    }
}
