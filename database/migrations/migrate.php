<?php
// Migration script for development resets. In production, use migrator.php (versioned).

use Dotenv\Dotenv;
use App\Core\DB;

require __DIR__ . '/../../vendor/autoload.php';

// Load env (.env) to get DB credentials
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

$driver = $_ENV['DB_DRIVER']   ?? 'mysql';
$host   = $_ENV['DB_HOST']     ?? '127.0.0.1';
$port   = (int)($_ENV['DB_PORT'] ?? 3307);
$db     = $_ENV['DB_DATABASE'] ?? 'hiddengems';
$user   = $_ENV['DB_USERNAME'] ?? 'root';
$pass   = $_ENV['DB_PASSWORD'] ?? '';

if ($driver !== 'mysql') {
    fwrite(STDERR, "This project uses MariaDB with PDO mysql driver. Set DB_DRIVER=mysql (got DB_DRIVER={$driver}).\n");
    exit(1);
}

// 1) Server-level connection (no dbname) to drop/create database
$serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
$server = new PDO($serverDsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$env    = $_ENV['APP_ENV'] ?? 'local';
$allowDrop = (($_ENV['MIGRATE_ALLOW_DROP'] ?? '') === '1') || in_array('--drop', $argv, true);
if ($env === 'production' && $allowDrop) {
    fwrite(STDERR, "Refusing to DROP in production. Remove --drop or set MIGRATE_ALLOW_DROP=0.\n");
    $allowDrop = false;
}
if ($allowDrop) {
    echo "Dropping database: {$db}\n";
    $server->exec("DROP DATABASE IF EXISTS `{$db}`;");
}
echo "Ensuring database exists: {$db}\n";
$server->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

// 2) Initialize app DB connection (now that DB exists)
DB::init([
    'driver' => $driver,
    'host' => $host,
    'port' => $port,
    'database' => $db,
    'username' => $user,
    'password' => $pass,
]);
$pdo = DB::pdo();

// 3) Apply baseline .sql files (idempotent) to ensure core schema exists
$migrationFiles = array_values(array_filter(glob(__DIR__ . '/*.sql'), function($f){
    return !str_ends_with($f, '.up.sql') && !str_ends_with($f, '.down.sql');
}));
sort($migrationFiles);
foreach ($migrationFiles as $file) {
    $sql = file_get_contents($file);
    // Strip MariaDB-only syntax like "CREATE INDEX IF NOT EXISTS ..." for MySQL compatibility
    $sql = preg_replace('/^\s*CREATE\s+INDEX\s+IF\s+NOT\s+EXISTS\b.*?;\s*$/mi', '-- stripped: incompatible CREATE INDEX IF NOT EXISTS;\n', $sql);
    $pdo->exec($sql);
    echo "Executed baseline: " . basename($file) . "\n";
}

// Ensure useful indexes exist (idempotent)
$ensureIndex = function(PDO $pdo, string $table, string $index, string $columns) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$table, $index]);
    $exists = (int)$stmt->fetchColumn();
    if ($exists === 0) {
        $sql = sprintf('CREATE INDEX %s ON %s(%s)', $index, $table, $columns);
        $pdo->exec($sql);
        echo "Created index: {$index} on {$table}\n";
    }
};

$ensureIndex($pdo, 'cua_hang', 'idx_store_name', 'ten_cua_hang');
$ensureIndex($pdo, 'cua_hang', 'idx_store_status', 'id_trang_thai');
$ensureIndex($pdo, 'vi_tri', 'idx_location_city', 'thanh_pho');
$ensureIndex($pdo, 'danh_gia', 'idx_review_store', 'id_cua_hang');
$ensureIndex($pdo, 'danh_gia', 'idx_review_user', 'id_user');
$ensureIndex($pdo, 'hinh_anh', 'idx_image_store', 'id_cua_hang');
$ensureIndex($pdo, 'voucher_cua_hang', 'idx_vs_voucher', 'id_voucher');
$ensureIndex($pdo, 'voucher_cua_hang', 'idx_vs_store', 'id_cua_hang');
$ensureIndex($pdo, 'tin_nhan', 'idx_msg_pair_time', 'id_nguoi_gui, id_nguoi_nhan, thoi_gian_tao');
$ensureIndex($pdo, 'banner', 'idx_banner_active', 'active, vi_tri');
$ensureIndex($pdo, 'giao_dich_vi', 'idx_wt_user_time', 'id_user, thoi_gian_tao');
$ensureIndex($pdo, 'yeu_cau_quang_cao', 'idx_ad_status_time', 'trang_thai, ngay_bat_dau');
$ensureIndex($pdo, 'yeu_cau_quang_cao', 'idx_ad_store_time', 'id_cua_hang, ngay_bat_dau');

echo "Baseline migrations ensured + indexes verified!\n";

// 4) Apply versioned up migrations (force 'up' even if this script was run with flags like --drop)
$argv = ['migrator.php', 'up'];
require __DIR__ . '/migrator.php';
