<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\DB;

$root = dirname(__DIR__);
Dotenv::createImmutable($root)->safeLoad();

DB::init([
    'driver'   => $_ENV['DB_DRIVER']   ?? 'mysql',
    'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
    'port'     => (int)($_ENV['DB_PORT'] ?? 3307),
    'database' => $_ENV['DB_DATABASE'] ?? 'hiddengems',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
]);

$pdo = DB::pdo();

$expected = [
    'status',
    'users',
    'vi_tri',
    'cua_hang',
    'danh_gia',
    'chuyen_muc',
    'cua_hang_chuyen_muc',
    'yeu_thich',
    'hinh_anh',
    'blog',
    'thanh_toan',
    'voucher',
    'so_thich',
    'nguoi_dung_so_thich',
    'voucher_cua_hang',
    'khuyen_mai',
    'khuyen_mai_cua_hang',
    'banner',
    'tin_nhan',
    'vi_tien',
    'giao_dich_vi',
    'yeu_cau_quang_cao',
    'login_attempt',
    'refresh_token',
    'password_reset',
    'email_verification',
    'audit_log',
    'user_consent',
    'schema_migrations',
];

$stmt = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$actual = array_map('strtolower', $rows);

$missing = array_values(array_diff($expected, $actual));
$extra   = array_values(array_diff($actual, $expected));

echo "Database: " . ($_ENV['DB_DATABASE'] ?? 'unknown') . PHP_EOL;
echo "Tables found: " . count($actual) . PHP_EOL;
echo "Missing (expected but not found): " . count($missing) . PHP_EOL;
if ($missing) {
    echo " - " . implode(PHP_EOL . ' - ', $missing) . PHP_EOL;
}
echo "Extra (found but not in expected list): " . count($extra) . PHP_EOL;
if ($extra) {
    echo " - " . implode(PHP_EOL . ' - ', $extra) . PHP_EOL;
}

// Return non-zero if anything critical missing (at least core tables)
$core = ['users','cua_hang','danh_gia'];
$coreMissing = array_intersect($core, $missing);
if (!empty($coreMissing)) {
    fwrite(STDERR, "Core tables missing: " . implode(',', $coreMissing) . PHP_EOL);
    exit(2);
}

echo "Schema check OK." . PHP_EOL;
