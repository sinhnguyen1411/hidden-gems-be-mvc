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
    fwrite(STDERR, "Only MySQL is supported by migrate.php (DB_DRIVER={$driver})\n");
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
    echo "⚠️  Dropping database: {$db}\n";
    $server->exec("DROP DATABASE IF EXISTS `{$db}`;");
}
echo "🧱 Ensuring database exists: {$db}\n";
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

// 3) If dropping, apply baseline .sql files; otherwise only apply versioned ups
if ($allowDrop) {
    $migrationFiles = array_values(array_filter(glob(__DIR__ . '/*.sql'), function($f){
        return !str_ends_with($f, '.up.sql') && !str_ends_with($f, '.down.sql');
    }));
    sort($migrationFiles);
    foreach ($migrationFiles as $file) {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "✅ Executed baseline: " . basename($file) . "\n";
    }
    echo "🎉 Baseline migrations executed!\n";
}

// 4) Apply versioned up migrations
require __DIR__ . '/migrator.php';
