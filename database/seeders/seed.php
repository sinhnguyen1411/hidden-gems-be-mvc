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
$pdo->exec("INSERT INTO status(ten_trang_thai, nhom_trang_thai) VALUES
    ('dang_cho','cua_hang'),
    ('hoat_dong','cua_hang'),
    ('dong_cua','cua_hang')");

// Users
$users = [
    ['admin','admin@example.com','admin123','Admin User','admin','0123456789'],
    ['alice','alice@example.com','secret123','Alice','user','0987654321']
];
foreach ($users as $u) {
codex/anh-gia-du-an-kjabc3
    $pdo->prepare("INSERT INTO nguoi_dung(ten_dang_nhap,email,mat_khau_ma_hoa,ho_va_ten,vai_tro,so_dien_thoai) VALUES (?,?,?,?,?,?)")
        ->execute([$u[0],$u[1],password_hash($u[2],PASSWORD_BCRYPT),$u[3],$u[4],$u[5]]);

    $pdo->prepare("INSERT IGNORE INTO users(name,email,password_hash,role) VALUES(?,?,?,?)")
        ->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_BCRYPT), $u[3]]);
main
}

// Location
$pdo->prepare("INSERT INTO vi_tri(dia_chi_chi_tiet,quan_huyen,thanh_pho,vi_do,kinh_do) VALUES (?,?,?,?,?)")
    ->execute(['123 Nguyen Hue','Quan 1','Ho Chi Minh',10.776889,106.700806]);
$locationId = $pdo->lastInsertId();

// Store
$pdo->prepare("INSERT INTO cua_hang(id_chu_so_huu,ten_cua_hang,mo_ta,id_trang_thai,id_vi_tri) VALUES (?,?,?,?,?)")
    ->execute([2,'Hidden Gem','Quan ca phe thu vi',2,$locationId]);
$storeId = $pdo->lastInsertId();

// Categories
$pdo->exec("INSERT INTO chuyen_muc(ten_chuyen_muc) VALUES ('Cafe'),('Dessert')");

// Store categories
$pdo->prepare("INSERT INTO cua_hang_chuyen_muc(id_cua_hang,id_chuyen_muc) VALUES (?,?)")
    ->execute([$storeId,1]);

// Review
$pdo->prepare("INSERT INTO danh_gia(id_nguoi_dung,id_cua_hang,diem_danh_gia,binh_luan) VALUES (?,?,?,?)")
    ->execute([1,$storeId,5,'Tuyet voi']);

// Favorite
$pdo->prepare("INSERT INTO yeu_thich(id_nguoi_dung,id_cua_hang) VALUES (?,?)")
    ->execute([2,$storeId]);

// Image
$pdo->prepare("INSERT INTO hinh_anh(id_cua_hang,url_anh,is_anh_dai_dien) VALUES (?,?,?)")
    ->execute([$storeId,'https://example.com/image.jpg',1]);

// Blog
$pdo->prepare("INSERT INTO blog(id_nguoi_dung,tieu_de,noi_dung) VALUES (?,?,?)")
    ->execute([1,'Chao mung','Bai viet dau tien']);

// Payment
$pdo->prepare("INSERT INTO thanh_toan(id_nguoi_dung,so_tien,phuong_thuc_thanh_toan,trang_thai) VALUES (?,?,?,?)")
    ->execute([2,100000,'cash','completed']);

// Voucher
$pdo->prepare("INSERT INTO voucher(ma_voucher,ten_voucher,gia_tri_giam,loai_giam_gia,so_luong_con_lai) VALUES (?,?,?,?,?)")
    ->execute(['WELCOME','Welcome',10,'percent',100]);
$vId = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO voucher_cua_hang(id_voucher,id_cua_hang) VALUES (?,?)")
    ->execute([$vId,$storeId]);

// Interest
$pdo->exec("INSERT INTO so_thich(ten_so_thich) VALUES ('Cafe'),('Book')");
$pdo->prepare("INSERT INTO nguoi_dung_so_thich(id_nguoi_dung,id_so_thich) VALUES (?,?)")
    ->execute([2,1]);

// Promotion
$pdo->prepare("INSERT INTO khuyen_mai(ten_chuong_trinh,mo_ta,ngay_bat_dau,ngay_ket_thuc,loai_ap_dung,pham_vi_ap_dung) VALUES (?,?,?,?,?,?)")
    ->execute(['Khai truong','Mo ta','2025-01-01','2025-12-31','voucher','toan_he_thong']);
$promoId = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO khuyen_mai_cua_hang(id_khuyen_mai,id_cua_hang) VALUES (?,?)")
    ->execute([$promoId,$storeId]);

echo "✅ Seeding done!\n";
