<?php
namespace App\Core;

class Storage
{
    public static function uploadsPath(): string
    {
        // app/Core => project root is two levels up
        $root = dirname(__DIR__, 2);
        $base = $_ENV['UPLOADS_PATH'] ?? ($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads');
        $path = $base;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        return $path;
    }

    public static function saveUploadedFile(array $file, ?string $subdir = null): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
                UPLOAD_ERR_PARTIAL => 'Partial upload',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            $msg = $map[$err] ?? 'Upload failed';
            throw new \RuntimeException($msg);
        }
        $original = $file['name'] ?? 'file';
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $size = (int)($file['size'] ?? 0);

        // Validate size and mime/ext
        $maxBytes = isset($_ENV['UPLOAD_MAX_BYTES']) ? (int)$_ENV['UPLOAD_MAX_BYTES'] : 5 * 1024 * 1024; // 5MB default
        if ($size > $maxBytes) {
            throw new \RuntimeException('File too large');
        }
        $allowedExt = array_filter(array_map('trim', explode(',', $_ENV['UPLOAD_ALLOWED_EXT'] ?? 'jpg,jpeg,png,gif,webp')));
        $lowerExt = strtolower($ext);
        if ($lowerExt && !in_array($lowerExt, $allowedExt, true)) {
            throw new \RuntimeException('Unsupported file type');
        }

        // MIME sniffing with finfo for additional safety
        $allowedMime = array_filter(array_map('trim', explode(',', $_ENV['UPLOAD_ALLOWED_MIME'] ?? 'image/jpeg,image/png,image/gif,image/webp')));
        if (function_exists('finfo_open') && isset($file['tmp_name']) && is_file($file['tmp_name'])) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $mime = finfo_file($f, $file['tmp_name']);
                finfo_close($f);
                if ($mime && $allowedMime && !in_array(strtolower($mime), array_map('strtolower',$allowedMime), true)) {
                    throw new \RuntimeException('Unsupported MIME type');
                }
            }
        }
        $basename = bin2hex(random_bytes(8));
        $dir = self::uploadsPath();
        if ($subdir) {
            $dir .= DIRECTORY_SEPARATOR . trim($subdir, '/\\');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        $filename = $basename . ($ext ? ('.' . strtolower($ext)) : '');
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            // Fallback for environments where move_uploaded_file might fail
            if (!rename($file['tmp_name'], $dest)) {
                throw new \RuntimeException('Failed to save upload');
            }
        }

        // Optional antivirus scan hook (placeholder)
        if (!empty($_ENV['UPLOAD_AV_SCAN_CMD'])) {
            $cmd = $_ENV['UPLOAD_AV_SCAN_CMD'] . ' ' . escapeshellarg($dest);
            @exec($cmd, $out, $code);
            if ($code !== 0) {
                @unlink($dest);
                throw new \RuntimeException('File failed antivirus scan');
            }
        }
        $uploadsBaseUrl = rtrim($_ENV['UPLOADS_URL_BASE'] ?? '', '/');
        if ($uploadsBaseUrl !== '') {
            $rel = ($subdir ? '/' . trim($subdir, '/\\') : '') . '/' . $filename;
            $url = $uploadsBaseUrl . $rel;
        } else {
            $urlBase = rtrim($_ENV['APP_URL'] ?? '', '/');
            $rel = '/uploads' . ($subdir ? '/' . trim($subdir, '/\\') : '') . '/' . $filename;
            $url = $urlBase ? ($urlBase . $rel) : $rel;
        }
        return ['path' => $dest, 'url' => $url, 'filename' => $filename, 'original' => $original];
    }

    public static function saveBase64(string $base64, string $prefix = 'img', ?string $subdir = null): array
    {
        if (preg_match('#^data:(.+);base64,(.*)$#', $base64, $m)) {
            $mime = $m[1];
            $data = base64_decode($m[2], true);
        } else {
            $mime = 'application/octet-stream';
            $data = base64_decode($base64, true);
        }
        if ($data === false) {
            throw new \InvalidArgumentException('Invalid base64 data');
        }
        // Validate size and extension similar to file uploads
        $size = strlen($data);
        $maxBytes = isset($_ENV['UPLOAD_MAX_BYTES']) ? (int)$_ENV['UPLOAD_MAX_BYTES'] : 5 * 1024 * 1024; // 5MB default
        if ($size > $maxBytes) {
            throw new \RuntimeException('File too large');
        }
        $ext = self::mimeToExt($mime);
        $allowedExt = array_filter(array_map('trim', explode(',', $_ENV['UPLOAD_ALLOWED_EXT'] ?? 'jpg,jpeg,png,gif,webp')));
        if ($ext !== '' && !in_array(strtolower($ext), $allowedExt, true)) {
            throw new \RuntimeException('Unsupported file type');
        }
        $basename = $prefix . '_' . bin2hex(random_bytes(8));
        $dir = self::uploadsPath();
        if ($subdir) {
            $dir .= DIRECTORY_SEPARATOR . trim($subdir, '/\\');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        $filename = $basename . ($ext ? ('.' . $ext) : '');
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($dest, $data);
        $uploadsBaseUrl = rtrim($_ENV['UPLOADS_URL_BASE'] ?? '', '/');
        if ($uploadsBaseUrl !== '') {
            $rel = ($subdir ? '/' . trim($subdir, '/\\') : '') . '/' . $filename;
            $url = $uploadsBaseUrl . $rel;
        } else {
            $urlBase = rtrim($_ENV['APP_URL'] ?? '', '/');
            $rel = '/uploads' . ($subdir ? '/' . trim($subdir, '/\\') : '') . '/' . $filename;
            $url = $urlBase ? ($urlBase . $rel) : $rel;
        }
        return ['path' => $dest, 'url' => $url, 'filename' => $filename];
    }

    private static function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => ''
        };
    }
}
