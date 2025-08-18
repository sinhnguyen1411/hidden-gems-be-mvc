<?php
require __DIR__ . '/../../vendor/autoload.php';   // 2 cấp lên mới đến vendor
require __DIR__ . '/../../bootstrap/app.php';    // 2 cấp lên mới đến bootstrap/app.php

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
