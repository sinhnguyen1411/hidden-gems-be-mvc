<?php
require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__,2));
$dotenv->safeLoad();

use App\Core\DB;

DB::init([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'hidden_gems',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? ''
]);

$pdo = DB::pdo();

echo "🚀 Start seeding...\n";

/**
 * USERS
 */
$users = [
    ['Admin','admin@example.com','admin123','admin'],
    ['Alice','alice@example.com','secret123','customer'],
    ['Bob','bob@example.com','password123','owner'],
    ['Carol','carol@example.com','password123','owner']
];
foreach ($users as $u) {
    $pdo->prepare("INSERT IGNORE INTO users(name,email,password_hash,role) VALUES(?,?,?,?)")
        ->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_BCRYPT), $u[3]]);
}

/**
 * CATEGORIES
 */
$categories = ['Specialty','Work-friendly','Takeaway','Dessert','Garden'];
foreach ($categories as $c) {
    $pdo->prepare("INSERT IGNORE INTO categories(name) VALUES(?)")->execute([$c]);
}

/**
 * CAFES
 */
$pdo->prepare("INSERT INTO cafes(owner_id,name,address,description) VALUES (?,?,?,?)")
    ->execute([3,'Hidden Gem Cafe','123 Nguyen Hue, HCM','Cozy place with great espresso']);
$cafe1_id = $pdo->lastInsertId();

$pdo->prepare("INSERT INTO cafes(owner_id,name,address,description) VALUES (?,?,?,?)")
    ->execute([4,'Rooftop Beans','45 Le Loi, HCM','Rooftop view, cold brew']);
$cafe2_id = $pdo->lastInsertId();

/**
 * CAFE_CATEGORIES
 */
$pdo->prepare("INSERT INTO cafe_categories(cafe_id,category_id) VALUES (?,?)")
    ->execute([$cafe1_id,1]); // Hidden Gem Cafe -> Specialty
$pdo->prepare("INSERT INTO cafe_categories(cafe_id,category_id) VALUES (?,?)")
    ->execute([$cafe1_id,2]); // Hidden Gem Cafe -> Work-friendly
$pdo->prepare("INSERT INTO cafe_categories(cafe_id,category_id) VALUES (?,?)")
    ->execute([$cafe2_id,1]); // Rooftop Beans -> Specialty
$pdo->prepare("INSERT INTO cafe_categories(cafe_id,category_id) VALUES (?,?)")
    ->execute([$cafe2_id,5]); // Rooftop Beans -> Garden

/**
 * REVIEWS
 */
$pdo->prepare("INSERT INTO reviews(cafe_id,user_id,rating,comment) VALUES (?,?,?,?)")
    ->execute([$cafe1_id,2,5,'Great coffee and atmosphere!']);
$pdo->prepare("INSERT INTO reviews(cafe_id,user_id,rating,comment) VALUES (?,?,?,?)")
    ->execute([$cafe2_id,2,4,'Loved the rooftop view, coffee was nice.']);

echo "✅ Seeding done!\n";
