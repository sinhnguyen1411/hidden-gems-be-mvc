<?php
require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__,2));
$dotenv->safeLoad();

use App\Core\DB;

DB::init([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['DB_PORT'] ?? 3307),
    'database' => $_ENV['DB_DATABASE'] ?? 'hiddengems',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? ''
]);

$pdo = DB::pdo();

echo "Start seeding...\n";

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
    ['admin',    'admin@example.com',  'admin123',  'Admin User',    'admin',    '0123456789'],
    ['alice123', 'alice@example.com',  'secret123', 'Alice',         'customer','0987654321'],
    ['bob',      'bob@example.com',    'secret123', 'Bob',           'customer','0901111222'],
    ['carol',    'carol@example.com',  'secret123', 'Carol',         'customer','0902222333'],
    ['dave',     'dave@example.com',   'secret123', 'Dave',          'customer','0903333444'],
    ['erin',     'erin@example.com',   'secret123', 'Erin',          'customer','0904444555'],
    ['shop123',  'shop@example.com',   'shop123',   'Shop Owner',    'shop',    '0900000000'],
    ['shop2',    'shop2@example.com',  'shop123',   'Second Owner',  'shop',    '0905555666']
];
// Introspect users table columns to adapt to schema differences
$columns = [];
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = array_map(fn($r) => $r['Field'], $colStmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    $columns = [];
}

foreach ($users as $u) {
    // Prefer checking by email; fallback to username if email not available
    $exists = false;
    try {
        $check = $pdo->prepare("SELECT id_user FROM users WHERE email=? LIMIT 1");
        $check->execute([$u[1]]);
        $exists = (bool)$check->fetch();
    } catch (Throwable $e) {
        try {
            $check = $pdo->prepare("SELECT id_user FROM users WHERE username=? LIMIT 1");
            $check->execute([$u[0]]);
            $exists = (bool)$check->fetch();
        } catch (Throwable $e2) {
            $exists = false;
        }
    }

    if (!$exists) {
        $passwordHashed = password_hash($u[2], PASSWORD_BCRYPT);
        $dataMap = [
            'username' => $u[0],
            'email' => $u[1],
            'password_hash' => $passwordHashed,
            'password' => $passwordHashed, // in case schema uses `password`
            'full_name' => $u[3],
            'role' => $u[4],
            'phone_number' => $u[5],
        ];
        $insertCols = array_values(array_intersect(array_keys($dataMap), $columns));
        if (empty($insertCols)) {
            // Fallback minimal insert with common fields
            $insertCols = array_values(array_intersect(['email', 'password_hash', 'password'], array_keys($dataMap)));
        }
        $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
        $sql = 'INSERT INTO users(' . implode(',', $insertCols) . ') VALUES (' . $placeholders . ')';
        $values = array_map(fn($k) => $dataMap[$k], $insertCols);
        $pdo->prepare($sql)->execute($values);
    }
}

// Get owners/admin for use later
$getUserId = function(string $email) use ($pdo): int {
    $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    return (int)($stmt->fetchColumn() ?: 0);
};
$ownerId1 = $getUserId('shop@example.com');
$ownerId2 = $getUserId('shop2@example.com');
$adminId  = $getUserId('admin@example.com');

// Locations — ensure multiple cities
$ensureLocation = function(string $addr, string $district, string $city, float $lat, float $lng) use ($pdo): int {
    $chk = $pdo->prepare("SELECT id_vi_tri FROM vi_tri WHERE dia_chi_chi_tiet=? AND thanh_pho=? LIMIT 1");
    $chk->execute([$addr, $city]);
    if ($r = $chk->fetch(PDO::FETCH_ASSOC)) return (int)$r['id_vi_tri'];
    $pdo->prepare("INSERT INTO vi_tri(dia_chi_chi_tiet,quan_huyen,thanh_pho,vi_do,kinh_do) VALUES (?,?,?,?,?)")
        ->execute([$addr,$district,$city,$lat,$lng]);
    return (int)$pdo->lastInsertId();
};
$locHCM_Q1 = $ensureLocation('123 Nguyen Hue','Quan 1','Ho Chi Minh',10.776889,106.700806);
$locHCM_PN = $ensureLocation('45 Phan Xich Long','Phu Nhuan','Ho Chi Minh',10.800879,106.690536);
$locHN_HBT  = $ensureLocation('9 Yet Kieu','Hai Ba Trung','Ha Noi',21.018582,105.846047);
$locDN_HaiC = $ensureLocation('2 Vo Nguyen Giap','Son Tra','Da Nang',16.067829,108.224209);

// Resolve status ids by name (do not assume ordering)
$getStatusId = function(string $name) use ($pdo): int {
    $s = $pdo->prepare("SELECT id_trang_thai FROM status WHERE ten_trang_thai=? AND nhom_trang_thai='cua_hang' LIMIT 1");
    $s->execute([$name]);
    return (int)($s->fetchColumn() ?: 0);
};
$stPending = $getStatusId('dang_cho') ?: 1;
$stActive  = $getStatusId('hoat_dong') ?: 2;
$stClosed  = $getStatusId('dong_cua') ?: 3;

// Stores — create multiple with different owners, locations, statuses
$ensureStore = function(int $ownerId, string $name, string $desc, int $statusId, int $locationId, ?int $parent = null) use ($pdo): int {
    $stmt = $pdo->prepare("SELECT id_cua_hang FROM cua_hang WHERE id_chu_so_huu=? AND ten_cua_hang=? LIMIT 1");
    $stmt->execute([$ownerId,$name]);
    if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) return (int)$r['id_cua_hang'];
    $sql = "INSERT INTO cua_hang(id_chu_so_huu,ten_cua_hang,mo_ta,id_trang_thai,id_vi_tri,id_cua_hang_cha) VALUES (?,?,?,?,?,?)";
    $pdo->prepare($sql)->execute([$ownerId,$name,$desc,$statusId,$locationId,$parent]);
    return (int)$pdo->lastInsertId();
};

// status ids: assume 1:dang_cho 2:hoat_dong 3:dong_cua
$storeMain   = $ensureStore($ownerId1,'Hidden Gem','Quan ca phe thu vi',$stActive,$locHCM_Q1,null);
$storeBranch = $ensureStore($ownerId1,'Hidden Gem - Branch Q.PN','Chi nhanh Phu Nhuan',$stActive,$locHCM_PN,$storeMain);
$storeHN     = $ensureStore($ownerId2,'Gem Hanoi','Ca phe & banh ngot',$stPending,$locHN_HBT,null);
$storeDN     = $ensureStore($ownerId2,'Gem Danang','View bien, thuc don brunch',$stActive,$locDN_HaiC,null);

// Categories
$pdo->exec("INSERT IGNORE INTO chuyen_muc(ten_chuyen_muc) VALUES
  ('Cafe'),('Dessert'),('Tea'),('Bakery'),('Breakfast'),('Fastfood')");

// Store categories mapping
$mapCat = function(int $storeId, array $catNames) use ($pdo) {
    foreach ($catNames as $name) {
        $cid = (int)$pdo->query("SELECT id_chuyen_muc FROM chuyen_muc WHERE ten_chuyen_muc='" . str_replace("'","''",$name) . "' LIMIT 1")->fetchColumn();
        if ($cid) {
            $pdo->prepare("INSERT IGNORE INTO cua_hang_chuyen_muc(id_cua_hang,id_chuyen_muc) VALUES (?,?)")
                ->execute([$storeId,$cid]);
        }
    }
};
$mapCat($storeMain,   ['Cafe','Dessert']);
$mapCat($storeBranch, ['Cafe','Tea']);
$mapCat($storeHN,     ['Cafe','Bakery']);
$mapCat($storeDN,     ['Breakfast','Cafe']);

// Reviews (varied ratings & users)
$reviews = [
    [1,$storeMain,5,'Tuyet voi, khong gian dep'],
    [2,$storeMain,4,'Ca phe duoc, phuc vu nhanh'],
    [3,$storeBranch,3,'On, nhung hoi on ao'],
    [4,$storeHN,5,'Banh ngon, nhan vien than thien'],
    [5,$storeDN,2,'Mon an ra cham'],
    [6,$storeDN,4,'View dep, se quay lai']
];
foreach ($reviews as $rv) {
    $pdo->prepare("INSERT IGNORE INTO danh_gia(id_user,id_cua_hang,diem_danh_gia,binh_luan) VALUES (?,?,?,?)")
        ->execute($rv);
}

// Favorites
$favs = [[2,$storeMain],[3,$storeMain],[2,$storeDN],[4,$storeBranch]];
foreach ($favs as $f) {
    $pdo->prepare("INSERT IGNORE INTO yeu_thich(id_user,id_cua_hang) VALUES (?,?)")->execute($f);
}

// Images
$addImage = function(int $storeId, string $url, bool $cover=false) use ($pdo) {
    $chk = $pdo->prepare("SELECT id_anh FROM hinh_anh WHERE id_cua_hang=? AND url_anh=? LIMIT 1");
    $chk->execute([$storeId,$url]);
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO hinh_anh(id_cua_hang,url_anh,is_anh_dai_dien) VALUES (?,?,?)")
            ->execute([$storeId,$url,$cover?1:0]);
    }
};
$addImage($storeMain,'https://picsum.photos/seed/gem1/800/450',true);
$addImage($storeMain,'https://picsum.photos/seed/gem2/800/450',false);
$addImage($storeBranch,'https://picsum.photos/seed/gem3/800/450',true);
$addImage($storeHN,'https://picsum.photos/seed/gem4/800/450',true);
$addImage($storeDN,'https://picsum.photos/seed/gem5/800/450',true);

// Blog posts
$blogs = [
    [1,'Chao mung','Bai viet dau tien'],
    [1,'Su kien thang nay','Giam gia 20% tat ca mon banh'],
    [2,'Review Hidden Gem','Cafe dam, phong cach vintage'],
];
foreach ($blogs as $b) {
    $chk = $pdo->prepare("SELECT id_blog FROM blog WHERE id_user=? AND tieu_de=? LIMIT 1");
    $chk->execute([$b[0],$b[1]]);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO blog(id_user,tieu_de,noi_dung) VALUES (?,?,?)")->execute($b);
}

// Payments
$payments = [
    [2,100000,'cash','completed'],
    [3,75000,'momo','completed'],
    [4,50000,'card','pending']
];
foreach ($payments as $p) {
    $chk = $pdo->prepare("SELECT id_thanh_toan FROM thanh_toan WHERE id_user=? AND so_tien=? AND phuong_thuc_thanh_toan=? AND trang_thai=? LIMIT 1");
    $chk->execute($p);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO thanh_toan(id_user,so_tien,phuong_thuc_thanh_toan,trang_thai) VALUES (?,?,?,?)")->execute($p);
}

// Vouchers + mapping
$voucherSpecs = [
    ['WELCOME','Welcome',10,'percent',100],
    ['FREESHIP','Free Ship',15000,'amount',200],
    ['BREAKFAST10','Breakfast 10%',10,'percent',50]
];
foreach ($voucherSpecs as $v) {
    $pdo->prepare("INSERT IGNORE INTO voucher(ma_voucher,ten_voucher,gia_tri_giam,loai_giam_gia,so_luong_con_lai) VALUES (?,?,?,?,?)")->execute($v);
}
$getVoucherId = $pdo->prepare("SELECT id_voucher FROM voucher WHERE ma_voucher=?");
foreach ([['WELCOME',$storeMain],['WELCOME',$storeDN],['FREESHIP',$storeMain],['FREESHIP',$storeBranch],['BREAKFAST10',$storeDN]] as $map) {
    $getVoucherId->execute([$map[0]]);
    $vid = (int)$getVoucherId->fetchColumn();
    if ($vid) $pdo->prepare("INSERT IGNORE INTO voucher_cua_hang(id_voucher,id_cua_hang) VALUES (?,?)")->execute([$vid,$map[1]]);
}

// Interests
$pdo->exec("INSERT IGNORE INTO so_thich(ten_so_thich) VALUES ('Cafe'),('Book'),('Travel'),('Music')");
// Map user interests by names
$ensureInterest = function(string $name) use ($pdo): int {
    $stmt = $pdo->prepare("SELECT id_so_thich FROM so_thich WHERE ten_so_thich=? LIMIT 1");
    $stmt->execute([$name]);
    return (int)($stmt->fetchColumn() ?: 0);
};
$userInterests = [
    ['alice@example.com',['Cafe','Book']],
    ['bob@example.com',  ['Travel']],
    ['carol@example.com',['Music','Cafe']],
];
foreach ($userInterests as [$email,$names]) {
    $uid = $getUserId($email);
    foreach ($names as $n) {
        $iid = $ensureInterest($n);
        if ($uid && $iid) $pdo->prepare("INSERT IGNORE INTO nguoi_dung_so_thich(id_user,id_so_thich) VALUES (?,?)")->execute([$uid,$iid]);
    }
}

// Promotions
$promos = [
    ['Khai truong','Mo ta','2025-01-01','2025-12-31','voucher','toan_he_thong'],
    ['Combo brunch','Giam 30% mon brunch','2025-02-01','2025-03-01','voucher','gioi_han']
];
foreach ($promos as $p) {
    $chk = $pdo->prepare("SELECT id_khuyen_mai FROM khuyen_mai WHERE ten_chuong_trinh=? LIMIT 1");
    $chk->execute([$p[0]]);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO khuyen_mai(ten_chuong_trinh,mo_ta,ngay_bat_dau,ngay_ket_thuc,loai_ap_dung,pham_vi_ap_dung) VALUES (?,?,?,?,?,?)")->execute($p);
}
$getPromoId = $pdo->prepare("SELECT id_khuyen_mai FROM khuyen_mai WHERE ten_chuong_trinh=?");
foreach ([['Khai truong',$storeMain],['Khai truong',$storeHN],['Combo brunch',$storeDN]] as $pm) {
    $getPromoId->execute([$pm[0]]);
    $pid = (int)$getPromoId->fetchColumn();
    if ($pid) $pdo->prepare("INSERT IGNORE INTO khuyen_mai_cua_hang(id_khuyen_mai,id_cua_hang) VALUES (?,?)")->execute([$pid,$pm[1]]);
}

// Banners
$banners = [
    ['Top Banner 1','Sale he','https://picsum.photos/seed/banner1/1200/300','/promo','home_top',1,1],
    ['Top Banner 2','Combo brunch','https://picsum.photos/seed/banner2/1200/300','/brunch','home_top',2,1],
    ['Mid Banner','Voucher 10%','https://picsum.photos/seed/banner3/1200/300','/voucher','home_mid',1,1],
    ['Bottom Banner','Blog moi','https://picsum.photos/seed/banner4/1200/300','/blog','home_bottom',1,0]
];
foreach ($banners as $b) {
    $chk = $pdo->prepare("SELECT id_banner FROM banner WHERE tieu_de=? LIMIT 1");
    $chk->execute([$b[0]]);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO banner(tieu_de,mo_ta,url_anh,link_url,vi_tri,thu_tu,active) VALUES (?,?,?,?,?,?,?)")->execute($b);
}

// Chat messages
$messages = [
    [$getUserId('alice@example.com'), $getUserId('shop@example.com'), 'Chao ban, quan con mo cua khong?', 0],
    [$getUserId('shop@example.com'),  $getUserId('alice@example.com'), 'Chao ban, hom nay mo den 22h nhe!', 0],
    [$getUserId('bob@example.com'),   $getUserId('shop2@example.com'), 'Quan co ban banh vegan khong?', 1]
];
foreach ($messages as $m) {
    $chk = $pdo->prepare("SELECT id_tin_nhan FROM tin_nhan WHERE id_nguoi_gui=? AND id_nguoi_nhan=? AND noi_dung=? LIMIT 1");
    $chk->execute([$m[0],$m[1],$m[2]]);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO tin_nhan(id_nguoi_gui,id_nguoi_nhan,noi_dung,da_doc) VALUES (?,?,?,?)")->execute($m);
}

// Wallets and transactions
$ensureWallet = function(int $uid, float $balance) use ($pdo) {
    $exists = (int)$pdo->query("SELECT COUNT(1) FROM vi_tien WHERE id_user=".$uid)->fetchColumn();
    if ($exists===0) $pdo->prepare("INSERT INTO vi_tien(id_user,so_du) VALUES (?,?)")->execute([$uid,$balance]);
};
$ensureWallet($getUserId('alice@example.com'), 200000);
$ensureWallet($getUserId('shop@example.com'),  500000);

$txs = [
    [$getUserId('alice@example.com'), 100000,'nap','Nap qua Momo','wallet',null],
    [$getUserId('alice@example.com'),  20000,'tru','Mua voucher','voucher',null],
    [$getUserId('shop@example.com'),  50000,'hoan','Hoan phi quang cao','ads',null]
];
foreach ($txs as $t) {
    $chk = $pdo->prepare("SELECT id_giao_dich FROM giao_dich_vi WHERE id_user=? AND so_tien=? AND loai=? AND mo_ta=? LIMIT 1");
    $chk->execute([$t[0],$t[1],$t[2],$t[3]]);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO giao_dich_vi(id_user,so_tien,loai,mo_ta,tham_chieu_loai,tham_chieu_id) VALUES (?,?,?,?,?,?)")->execute($t);
}

// Advertising requests
$adRows = [
    [$storeMain,'BASIC','2025-01-10','2025-02-10',300000,'cho_duyet',null,null,null],
    [$storeDN,'PRO','2025-01-05','2025-02-05',800000,'da_duyet',$adminId,date('Y-m-d H:i:s'),null],
    [$storeHN,'VIP','2025-02-01','2025-03-01',1500000,'tu_choi',$adminId,date('Y-m-d H:i:s'),null]
];
foreach ($adRows as $ar) {
    // avoid duplicates by store+goi+start
    $chk = $pdo->prepare("SELECT id_yeu_cau FROM yeu_cau_quang_cao WHERE id_cua_hang=? AND goi=? AND ngay_bat_dau=? LIMIT 1");
    $chk->execute([$ar[0],$ar[1],$ar[2]]);
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO yeu_cau_quang_cao(id_cua_hang,goi,ngay_bat_dau,ngay_ket_thuc,gia,trang_thai,id_admin_duyet,ngay_duyet,id_giao_dich_tru) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute($ar);
    }
}

// Login attempts
$la = [
    ['alice@example.com','127.0.0.1',1],
    ['alice@example.com','127.0.0.1',0],
    ['bob@example.com','10.0.0.2',0]
];
foreach ($la as $row) {
    $pdo->prepare("INSERT INTO login_attempt(identifier,ip,success) VALUES (?,?,?)")->execute($row);
}

// Refresh/email tokens (placeholder hashes)
$rtUser = $getUserId('alice@example.com');
if ($rtUser) {
    $pdo->prepare("INSERT IGNORE INTO refresh_token(id_user,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))")
        ->execute([$rtUser, hash('sha256','demo_refresh_token')]);
    $pdo->prepare("INSERT IGNORE INTO email_verification(id_user,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 7 DAY))")
        ->execute([$rtUser, hash('sha256','demo_verify_token')]);
}

// Password reset example (used)
if ($rtUser) {
    $pdo->prepare("INSERT IGNORE INTO password_reset(id_user,token_hash,expires_at,used_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())")
        ->execute([$rtUser, hash('sha256','demo_reset_token')]);
}

// Audit logs
$audits = [
    [$adminId,'user_role_update','users',$getUserId('shop@example.com'),'role=shop'],
    [$getUserId('shop@example.com'),'create_store','cua_hang',$storeMain,null]
];
foreach ($audits as $a) {
    $pdo->prepare("INSERT INTO audit_log(actor_user_id,action,target_type,target_id,meta) VALUES (?,?,?,?,?)")->execute($a);
}

// User consent
$consents = [
    [$getUserId('alice@example.com'),'v1','v1',date('Y-m-d H:i:s')],
    [$getUserId('bob@example.com'),'v1','v1',date('Y-m-d H:i:s')]
];
foreach ($consents as $c) {
    $chk = $pdo->prepare("SELECT id FROM user_consent WHERE id_user=? LIMIT 1");
    $chk->execute([$c[0]]);
    if (!$chk->fetch()) $pdo->prepare("INSERT INTO user_consent(id_user,terms_version,privacy_version,consent_at) VALUES (?,?,?,?)")->execute($c);
}

echo "Seeding done!\n";
