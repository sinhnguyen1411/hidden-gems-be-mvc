<?php
// 1. Kết nối tới mysql, drop và tạo lại database
$pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("DROP DATABASE IF EXISTS hiddengems;");
$pdo->exec("CREATE DATABASE hiddengems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

// 2. Kết nối lại vào database hiddengems
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../bootstrap/app.php';
use App\Core\DB;
$pdo = DB::pdo();

// Lấy tất cả file .sql trong thư mục migrations
$migrationFiles = glob(__DIR__ . '/*.sql');
foreach ($migrationFiles as $file) {
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "✅ Executed migration: " . basename($file) . "\n";
}
echo "🎉 All migrations executed!\n";