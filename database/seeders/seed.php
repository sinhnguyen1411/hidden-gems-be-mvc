<?php
require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__,2));
$dotenv->safeLoad();

use App\Core\DB;

DB::init([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'hiddengems',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? ''
]);

$pdo = DB::pdo();

echo "🚀 Start seeding...\n";

// Status
// Status (idempotent)
$pdo->exec("INSERT INTO status(ten_trang_thai, nhom_trang_thai)
    SELECT 'dang_cho','cua_hang' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM status WHERE ten_trang_thai='dang_cho' AND nhom_trang_thai='cua_hang')");
$pdo->exec("INSERT INTO status(ten_trang_thai, nhom_trang_thai)
    SELECT 'hoat_dong','cua_hang' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM status WHERE ten_trang_thai='hoat_dong' AND nhom_trang_thai='cua_hang')");
$pdo->exec("INSERT INTO status(ten_trang_thai, nhom_trang_thai)
    SELECT 'dong_cua','cua_hang' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM status WHERE ten_trang_thai='dong_cua' AND nhom_trang_thai='cua_hang')");

// Users
$users = [
    ['admin','admin@example.com','admin123','Admin User','admin','0123456789'],
    ['alice123','alice@example.com','secret123','Alice','customer','0987654321']
];
foreach ($users as $u) {
    $check = $pdo->prepare("SELECT id_user FROM users WHERE email=? LIMIT 1");
    $check->execute([$u[1]]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,role,phone_number) VALUES (?,?,?,?,?,?)")
            ->execute([$u[0],$u[1],password_hash($u[2],PASSWORD_BCRYPT),$u[3],$u[4],$u[5]]);
    }
}

// Location
// Location (insert if not exists by full address)
$chkLoc = $pdo->prepare("SELECT id_vi_tri FROM vi_tri WHERE dia_chi_chi_tiet=? AND thanh_pho=? LIMIT 1");
$chkLoc->execute(['123 Nguyen Hue','Ho Chi Minh']);
if ($row = $chkLoc->fetch()) {
    $locationId = (int)$row['id_vi_tri'];
} else {
    $pdo->prepare("INSERT INTO vi_tri(dia_chi_chi_tiet,quan_huyen,thanh_pho,vi_do,kinh_do) VALUES (?,?,?,?,?)")
        ->execute(['123 Nguyen Hue','Quan 1','Ho Chi Minh',10.776889,106.700806]);
    $locationId = (int)$pdo->lastInsertId();
}

// Store
// Store (by unique name + owner)
$chkStore = $pdo->prepare("SELECT id_cua_hang FROM cua_hang WHERE id_chu_so_huu=? AND ten_cua_hang=? LIMIT 1");
$chkStore->execute([2,'Hidden Gem']);
if ($row = $chkStore->fetch()) {
    $storeId = (int)$row['id_cua_hang'];
} else {
    $pdo->prepare("INSERT INTO cua_hang(id_chu_so_huu,ten_cua_hang,mo_ta,id_trang_thai,id_vi_tri) VALUES (?,?,?,?,?)")
        ->execute([2,'Hidden Gem','Quan ca phe thu vi',2,$locationId]);
    $storeId = (int)$pdo->lastInsertId();
}

// Categories
$pdo->exec("INSERT IGNORE INTO chuyen_muc(ten_chuyen_muc) VALUES ('Cafe'),('Dessert')");

// Store categories
$pdo->prepare("INSERT IGNORE INTO cua_hang_chuyen_muc(id_cua_hang,id_chuyen_muc) VALUES (?,?)")
    ->execute([$storeId,1]);

// Review
$pdo->prepare("INSERT IGNORE INTO danh_gia(id_user,id_cua_hang,diem_danh_gia,binh_luan) VALUES (?,?,?,?)")
    ->execute([1,$storeId,5,'Tuyet voi']);

// Favorite
$pdo->prepare("INSERT IGNORE INTO yeu_thich(id_user,id_cua_hang) VALUES (?,?)")
    ->execute([2,$storeId]);

// Image
// Image (insert if not exists)
$chkImg = $pdo->prepare("SELECT id_anh FROM hinh_anh WHERE id_cua_hang=? AND url_anh=? LIMIT 1");
$chkImg->execute([$storeId,'https://example.com/image.jpg']);
if (!$chkImg->fetch()) {
    $pdo->prepare("INSERT INTO hinh_anh(id_cua_hang,url_anh,is_anh_dai_dien) VALUES (?,?,?)")
        ->execute([$storeId,'https://example.com/image.jpg',1]);
}

// Blog
// Blog (by user+title)
$chkBlog = $pdo->prepare("SELECT id_blog FROM blog WHERE id_user=? AND tieu_de=? LIMIT 1");
$chkBlog->execute([1,'Chao mung']);
if (!$chkBlog->fetch()) {
    $pdo->prepare("INSERT INTO blog(id_user,tieu_de,noi_dung) VALUES (?,?,?)")
        ->execute([1,'Chao mung','Bai viet dau tien']);
}

// Payment
// Payment (avoid duplicates by simple match)
$chkPay = $pdo->prepare("SELECT id_thanh_toan FROM thanh_toan WHERE id_user=? AND so_tien=? AND phuong_thuc_thanh_toan=? AND trang_thai=? LIMIT 1");
$chkPay->execute([2,100000,'cash','completed']);
if (!$chkPay->fetch()) {
    $pdo->prepare("INSERT INTO thanh_toan(id_user,so_tien,phuong_thuc_thanh_toan,trang_thai) VALUES (?,?,?,?)")
        ->execute([2,100000,'cash','completed']);
}

// Voucher
// Voucher + mapping (unique code)
$pdo->prepare("INSERT IGNORE INTO voucher(ma_voucher,ten_voucher,gia_tri_giam,loai_giam_gia,so_luong_con_lai) VALUES (?,?,?,?,?)")
    ->execute(['WELCOME','Welcome',10,'percent',100]);
$getV = $pdo->prepare("SELECT id_voucher FROM voucher WHERE ma_voucher=?");
$getV->execute(['WELCOME']);
$vId = (int)$getV->fetchColumn();
$pdo->prepare("INSERT IGNORE INTO voucher_cua_hang(id_voucher,id_cua_hang) VALUES (?,?)")
    ->execute([$vId,$storeId]);

// Interest
$pdo->exec("INSERT IGNORE INTO so_thich(ten_so_thich) VALUES ('Cafe'),('Book')");
$pdo->prepare("INSERT IGNORE INTO nguoi_dung_so_thich(id_user,id_so_thich) VALUES (?,?)")
    ->execute([2,1]);

// Promotion
// Promotion (by title)
$chkPr = $pdo->prepare("SELECT id_khuyen_mai FROM khuyen_mai WHERE ten_chuong_trinh=? LIMIT 1");
$chkPr->execute(['Khai truong']);
if ($row = $chkPr->fetch()) {
    $promoId = (int)$row['id_khuyen_mai'];
} else {
    $pdo->prepare("INSERT INTO khuyen_mai(ten_chuong_trinh,mo_ta,ngay_bat_dau,ngay_ket_thuc,loai_ap_dung,pham_vi_ap_dung) VALUES (?,?,?,?,?,?)")
        ->execute(['Khai truong','Mo ta','2025-01-01','2025-12-31','voucher','toan_he_thong']);
    $promoId = (int)$pdo->lastInsertId();
}
$pdo->prepare("INSERT IGNORE INTO khuyen_mai_cua_hang(id_khuyen_mai,id_cua_hang) VALUES (?,?)")
    ->execute([$promoId,$storeId]);

echo "✅ Seeding done!\n";

