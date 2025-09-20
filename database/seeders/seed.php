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

// Media uploads audit seeds
$mediaSamples = [
    ['uploader' => $adminId, 'context' => 'banner', 'url' => 'https://picsum.photos/seed/banner1/1200/300', 'meta' => ['seed' => true, 'placement' => 'home_top']],
    ['uploader' => $adminId, 'context' => 'banner', 'url' => 'https://picsum.photos/seed/banner5/1200/300', 'meta' => ['seed' => true, 'placement' => 'home_mid']],
    ['uploader' => $ownerIdHue ?: $adminId, 'context' => 'store_image', 'url' => 'https://picsum.photos/seed/gemhue/800/450', 'meta' => ['seed' => true, 'store_id' => $storeHue ?? null]],
    ['uploader' => $ownerIdDaLat ?: $ownerId5 ?: $adminId, 'context' => 'store_image', 'url' => 'https://picsum.photos/seed/gemdl/800/450', 'meta' => ['seed' => true, 'store_id' => $storeDL ?? null]],
    ['uploader' => $ownerId1 ?: $adminId, 'context' => 'chat_attachment', 'url' => 'https://picsum.photos/seed/chatdemo/600/400', 'meta' => ['seed' => true, 'note' => 'demo attachment']],
];
$mediaCheck = $pdo->prepare('SELECT id_media_upload FROM media_upload WHERE url=? LIMIT 1');
$mediaInsert = $pdo->prepare('INSERT INTO media_upload(uploader_id, context, url, path, filename, original_name, size_bytes, meta) VALUES (?,?,?,?,?,?,?,?)');
foreach ($mediaSamples as $media) {
    if (empty($media['url'])) {
        continue;
    }
    $mediaCheck->execute([$media['url']]);
    if ($mediaCheck->fetch()) {
        continue;
    }
    $metaJson = isset($media['meta']) ? json_encode($media['meta'], JSON_UNESCAPED_UNICODE) : null;
    $mediaInsert->execute([
        $media['uploader'] ?: null,
        $media['context'],
        $media['url'],
        $media['path'] ?? null,
        $media['filename'] ?? null,
        $media['original'] ?? null,
        $media['size'] ?? null,
        $metaJson,
    ]);
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

// Extended sample data for richer demos
echo "Extending sample data...\n";

// Additional store statuses
$additionalStatuses = [
    ['ten' => 'tam_dung', 'nhom' => 'cua_hang'],
];
foreach ($additionalStatuses as $stRow) {
    $stmt = $pdo->prepare("INSERT INTO status(ten_trang_thai, nhom_trang_thai) SELECT ?,? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM status WHERE ten_trang_thai=? AND nhom_trang_thai=?)");
    $stmt->execute([$stRow['ten'], $stRow['nhom'], $stRow['ten'], $stRow['nhom']]);
}

// Additional demo users to cover more scenarios
$moreUsers = [
    ['fiona','fiona@example.com','secret123','Fiona','customer','0906666777'],
    ['george','george@example.com','secret123','George','customer','0907777888'],
    ['gloria','gloria@example.com','secret123','Gloria','customer','0908888999'],
    ['henry','henry@example.com','secret123','Henry','customer','0909999000'],
    ['ivy','ivy@example.com','secret123','Ivy','customer','0910000111'],
    ['shopcentral','shopcentral@example.com','shop123','Central Owner','shop','0911111222'],
    ['shopnorth','shopnorth@example.com','shop123','North Owner','shop','0912222333'],
    ['shopcoast','shopcoast@example.com','shop123','Coast Owner','shop','0913333444'],
    ['shopdalat','shopdalat@example.com','shop123','Da Lat Owner','shop','0914444555'],
    ['shophue','shophue@example.com','shop123','Hue Owner','shop','0915555666'],
];
$insertUserIfMissing = function(array $u) use ($pdo, $columns) {
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
    if ($exists) {
        return;
    }
    $passwordHashed = password_hash($u[2], PASSWORD_BCRYPT);
    $dataMap = [
        'username' => $u[0],
        'email' => $u[1],
        'password_hash' => $passwordHashed,
        'password' => $passwordHashed,
        'full_name' => $u[3],
        'role' => $u[4],
        'phone_number' => $u[5],
    ];
    $insertCols = array_values(array_intersect(array_keys($dataMap), $columns));
    if (empty($insertCols)) {
        $insertCols = array_values(array_intersect(['email','password_hash','password'], array_keys($dataMap)));
    }
    if (empty($insertCols)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $sql = 'INSERT INTO users(' . implode(',', $insertCols) . ') VALUES (' . $placeholders . ')';
    $values = array_map(fn($k) => $dataMap[$k], $insertCols);
    $pdo->prepare($sql)->execute($values);
};
foreach ($moreUsers as $u) {
    $insertUserIfMissing($u);
}
unset($insertUserIfMissing);

// Capture ids for new actors
$ownerId3 = $getUserId('shopcentral@example.com');
$ownerId4 = $getUserId('shopnorth@example.com');
$ownerId5 = $getUserId('shopcoast@example.com');
$ownerIdHue = $getUserId('shophue@example.com');
$ownerIdDaLat = $getUserId('shopdalat@example.com');
$customerFiona = $getUserId('fiona@example.com');
$customerGeorge = $getUserId('george@example.com');
$customerGloria = $getUserId('gloria@example.com');
$customerHenry = $getUserId('henry@example.com');
$customerIvy = $getUserId('ivy@example.com');

// Ensure more diverse locations
$locHCM_Q7 = $ensureLocation('88 Nguyen Van Linh','Quan 7','Ho Chi Minh',10.727493,106.721634);
$locHue_Center = $ensureLocation('27 Le Loi','Phu Hoi','Hue',16.463713,107.590866);
$locHP_CatBi = $ensureLocation('12 Tran Phu','Ngo Quyen','Hai Phong',20.859775,106.682122);
$locCT_NK = $ensureLocation('5 Hai Ba Trung','Ninh Kieu','Can Tho',10.034269,105.783539);
$locDL_Ho = $ensureLocation('3 Tran Quoc Toan','Da Lat','Lam Dong',11.940419,108.442482);
$locDL_Lang = $ensureLocation('Langbiang Road','Lac Duong','Lam Dong',12.020828,108.443627);
$locQN_BaiChay = $ensureLocation('145 Ha Long','Bai Chay','Quang Ninh',20.955292,107.045899);

$stPaused = $getStatusId('tam_dung') ?: $stActive;

// More stores / branches across regions
$storeQ7 = $ensureStore($ownerId1, 'Hidden Gem - Q7', 'Chi nhanh khu Phu My Hung', $stPending, $locHCM_Q7, $storeMain ?: null);
$storeHue = $ensureStore($ownerIdHue ?: $ownerId3 ?: $ownerId1, 'Gem Hue Cafe', 'Tra cung nhac acoustic', $stActive, $locHue_Center, null);
$storeHP = $ensureStore($ownerId4 ?: $ownerId2, 'Gem Portside', 'Ca phe & brunch gan bien', $stActive, $locHP_CatBi, null);
$storeCT = $ensureStore($ownerId5 ?: $ownerId2, 'Gem Mekong', 'Menu chay xanh, view song Hau', $stPending, $locCT_NK, null);
$storeDL = $ensureStore($ownerIdDaLat ?: $ownerId5, 'Gem Da Lat', 'Nha go view doi thong', $stActive, $locDL_Ho, null);
$storeDL2 = $ensureStore($ownerIdDaLat ?: $ownerId5, 'Gem Da Lat - Langbiang', 'Chi nhanh tren cao nguyen', $stPaused, $locDL_Lang, $storeDL ?: null);
$storeQN = $ensureStore($ownerId3 ?: $ownerId4, 'Gem Ha Long', 'Sky lounge nhin vinh', $stClosed, $locQN_BaiChay, null);

// Enrich categories
$pdo->exec("INSERT IGNORE INTO chuyen_muc(ten_chuyen_muc) VALUES
  ('Rooftop'),('Vegan'),('Artisan'),('Specialty Tea'),('Live Music')");

$mapCat($storeHue, ['Tea','Dessert','Specialty Tea']);
$mapCat($storeHP, ['Cafe','Breakfast','Rooftop']);
$mapCat($storeCT, ['Cafe','Vegan']);
$mapCat($storeDL, ['Cafe','Artisan','Dessert']);
$mapCat($storeDL2, ['Tea','Artisan']);
$mapCat($storeQ7, ['Cafe','Fastfood']);
$mapCat($storeQN, ['Cafe','Live Music']);

// Expanded reviews with moderation states
$reviewConfigs = [
    ['email' => 'alice@example.com', 'store' => $storeMain, 'rating' => 5, 'comment' => 'Tuyet voi, khong gian dep', 'status' => 'da_duyet', 'created' => '2025-01-12 09:15:00'],
    ['email' => 'bob@example.com', 'store' => $storeMain, 'rating' => 4, 'comment' => 'Ca phe dam, phuc vu nhanh', 'status' => 'da_duyet', 'created' => '2025-01-18 08:45:00'],
    ['email' => 'carol@example.com', 'store' => $storeBranch, 'rating' => 3, 'comment' => 'On nhung hoi on ao gio cao diem', 'status' => 'approved', 'created' => '2025-01-20 19:00:00'],
    ['email' => 'dave@example.com', 'store' => $storeDN, 'rating' => 2, 'comment' => 'Mon an ra cham vao cuoi tuan', 'status' => 'tu_choi', 'created' => '2025-01-25 14:10:00'],
    ['email' => 'erin@example.com', 'store' => $storeDN, 'rating' => 4, 'comment' => 'View dep, se quay lai', 'status' => 'da_duyet', 'created' => '2025-01-28 17:25:00'],
    ['email' => 'gloria@example.com', 'store' => $storeHue, 'rating' => 5, 'comment' => 'Tra sen ngon va nhac acoustic hay', 'status' => 'da_duyet', 'created' => '2025-02-12 20:05:00'],
    ['email' => 'george@example.com', 'store' => $storeCT, 'rating' => 5, 'comment' => 'View song Hau dep, menu chay ngon', 'status' => 'cho_duyet', 'created' => '2025-02-22 08:10:00'],
    ['email' => 'henry@example.com', 'store' => $storeHP, 'rating' => 2, 'comment' => 'Can cai thien khau phan banh man', 'status' => 'an', 'created' => '2025-02-26 10:00:00'],
    ['email' => 'ivy@example.com', 'store' => $storeDN, 'rating' => 4, 'comment' => 'Combo brunch dang thu', 'status' => 'da_duyet', 'created' => '2025-02-24 09:30:00'],
    ['email' => 'fiona@example.com', 'store' => $storeDL, 'rating' => 5, 'comment' => 'Sang som lang man voi am su', 'status' => 'da_duyet', 'created' => '2025-03-01 07:45:00'],
];
$recalcStores = [];
foreach ($reviewConfigs as $cfg) {
    $uid = $getUserId($cfg['email']);
    $storeId = (int)($cfg['store'] ?? 0);
    if (!$uid || !$storeId) {
        continue;
    }
    $check = $pdo->prepare('SELECT id_danh_gia FROM danh_gia WHERE id_user=? AND id_cua_hang=?');
    $check->execute([$uid, $storeId]);
    $rid = (int)($check->fetchColumn() ?: 0);
    if ($rid) {
        $pdo->prepare('UPDATE danh_gia SET diem_danh_gia=?, binh_luan=?, trang_thai=?, thoi_gian_tao=? WHERE id_danh_gia=?')
            ->execute([$cfg['rating'], $cfg['comment'], $cfg['status'], $cfg['created'], $rid]);
    } else {
        $pdo->prepare('INSERT INTO danh_gia(id_user,id_cua_hang,diem_danh_gia,binh_luan,trang_thai,thoi_gian_tao) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $storeId, $cfg['rating'], $cfg['comment'], $cfg['status'], $cfg['created']]);
        $rid = (int)$pdo->lastInsertId();
    }
    $recalcStores[$storeId] = true;
}

$storeViewCounts = [
    $storeMain => 1520,
    $storeBranch => 620,
    $storeHN => 180,
    $storeDN => 920,
    $storeHue => 480,
    $storeHP => 360,
    $storeCT => 240,
    $storeDL => 540,
    $storeDL2 => 190,
    $storeQ7 => 120,
    $storeQN => 75,
];
$avgStmt = $pdo->prepare("SELECT AVG(diem_danh_gia) FROM danh_gia WHERE id_cua_hang=? AND (trang_thai IS NULL OR trang_thai IN ('da_duyet','approved','hien_thi'))");
$updateStoreStats = $pdo->prepare('UPDATE cua_hang SET diem_danh_gia_trung_binh=?, luot_xem=? WHERE id_cua_hang=?');
foreach (array_keys($recalcStores) as $sid) {
    if (!$sid) {
        continue;
    }
    $avgStmt->execute([$sid]);
    $avg = (float)$avgStmt->fetchColumn();
    $avg = $avg > 0 ? round($avg, 1) : 0.0;
    $views = $storeViewCounts[$sid] ?? 0;
    $updateStoreStats->execute([$avg, $views, $sid]);
}

// Favorites for new users
$extraFavs = [
    ['email' => 'ivy@example.com', 'store' => $storeDL],
    ['email' => 'gloria@example.com', 'store' => $storeHue],
    ['email' => 'alice@example.com', 'store' => $storeDL],
    ['email' => 'george@example.com', 'store' => $storeCT],
];
foreach ($extraFavs as $fav) {
    $uid = $getUserId($fav['email']);
    $storeId = (int)($fav['store'] ?? 0);
    if ($uid && $storeId) {
        $pdo->prepare('INSERT IGNORE INTO yeu_thich(id_user,id_cua_hang) VALUES (?,?)')->execute([$uid, $storeId]);
    }
}

// Gallery for new stores
$addImage($storeHue, 'https://picsum.photos/seed/gemhue/800/450', true);
$addImage($storeHue, 'https://picsum.photos/seed/gemhue2/800/450', false);
$addImage($storeHP, 'https://picsum.photos/seed/gemhp/800/450', true);
$addImage($storeCT, 'https://picsum.photos/seed/gemct/800/450', true);
$addImage($storeDL, 'https://picsum.photos/seed/gemdl/800/450', true);
$addImage($storeDL2, 'https://picsum.photos/seed/gemdl2/800/450', false);

// Blog posts with mixed statuses
$blogEntries = [
    ['author' => 'admin@example.com', 'title' => 'Chao mung', 'content' => 'Cap nhat thong tin he thong Hidden Gems.', 'status' => 'cong_bo'],
    ['author' => 'admin@example.com', 'title' => 'Su kien thang nay', 'content' => 'Giam gia 20% tat ca mon banh va do uong signature.', 'status' => 'cong_bo'],
    ['author' => 'shop@example.com', 'title' => 'Menu moi thang 3', 'content' => 'Them mon brunch va do uong healthy moi.', 'status' => 'nhap'],
    ['author' => 'shop2@example.com', 'title' => 'Ha Noi chieu thu', 'content' => 'Khong gian san thuong moi, ly cafe dam chat thu do.', 'status' => 'cong_bo'],
    ['author' => 'shopnorth@example.com', 'title' => 'Hai Phong street food', 'content' => 'Goi y tour am thuc duong pho di kem ca phe dac biet.', 'status' => 'cong_bo'],
];
foreach ($blogEntries as $entry) {
    $uid = $getUserId($entry['author']);
    if (!$uid) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id_blog FROM blog WHERE id_user=? AND tieu_de=? LIMIT 1');
    $chk->execute([$uid, $entry['title']]);
    $bid = (int)($chk->fetchColumn() ?: 0);
    if ($bid) {
        $pdo->prepare('UPDATE blog SET noi_dung=?, trang_thai=? WHERE id_blog=?')->execute([$entry['content'], $entry['status'], $bid]);
    } else {
        $pdo->prepare('INSERT INTO blog(id_user,tieu_de,noi_dung,trang_thai) VALUES (?,?,?,?)')->execute([$uid, $entry['title'], $entry['content'], $entry['status']]);
    }
}

$getBlogIdByTitle = function(string $title) use ($pdo): ?int {
    $stmt = $pdo->prepare('SELECT id_blog FROM blog WHERE tieu_de=? LIMIT 1');
    $stmt->execute([$title]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
};
$blogChaoId = $getBlogIdByTitle('Chao mung');
$blogSuKienId = $getBlogIdByTitle('Su kien thang nay');

// Comments on stores and blogs
$commentSeeds = [
    ['email' => 'carol@example.com', 'type' => 'store', 'target' => $storeHue, 'content' => 'Khong gian thoang mat, phu hop lam viec.', 'status' => 'da_duyet', 'created' => '2025-02-15 17:40:00'],
    ['email' => 'gloria@example.com', 'type' => 'store', 'target' => $storeHue, 'content' => 'Ban cong nhin song Huong rat chill.', 'status' => 'da_duyet', 'created' => '2025-02-16 09:12:00'],
    ['email' => 'fiona@example.com', 'type' => 'store', 'target' => $storeDL, 'content' => 'Cake red velvet ngon va khong bi ngot.', 'status' => 'cho_duyet', 'created' => '2025-03-02 10:05:00'],
    ['email' => 'bob@example.com', 'type' => 'blog', 'target' => $blogChaoId, 'content' => 'Cam on team da chia se thong tin huu ich.', 'status' => 'da_duyet', 'created' => '2025-02-01 08:00:00'],
    ['email' => 'ivy@example.com', 'type' => 'blog', 'target' => $blogSuKienId, 'content' => 'Hy vong su kien sap to chuc tai Da Nang.', 'status' => 'tu_choi', 'created' => '2025-02-10 09:50:00'],
];
foreach ($commentSeeds as $seed) {
    $uid = $getUserId($seed['email']);
    $target = (int)($seed['target'] ?? 0);
    if (!$uid || !$target) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id_binh_luan FROM binh_luan WHERE id_user=? AND loai_doi_tuong=? AND id_tham_chieu=? AND noi_dung=?');
    $chk->execute([$uid, $seed['type'], $target, $seed['content']]);
    $cid = (int)($chk->fetchColumn() ?: 0);
    if ($cid) {
        $pdo->prepare('UPDATE binh_luan SET trang_thai=?, thoi_gian_tao=? WHERE id_binh_luan=?')->execute([$seed['status'], $seed['created'], $cid]);
    } else {
        $pdo->prepare('INSERT INTO binh_luan(id_user,loai_doi_tuong,id_tham_chieu,noi_dung,trang_thai,thoi_gian_tao) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $seed['type'], $target, $seed['content'], $seed['status'], $seed['created']]);
    }
}

// More payment samples
$extraPayments = [
    [$customerFiona, 85000, 'momo', 'completed'],
    [$customerGloria, 65000, 'card', 'failed'],
    [$customerIvy, 120000, 'cash', 'completed'],
    [$customerGeorge, 45000, 'bank_transfer', 'refunded'],
];
foreach ($extraPayments as $p) {
    if (empty($p[0])) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id_thanh_toan FROM thanh_toan WHERE id_user=? AND so_tien=? AND phuong_thuc_thanh_toan=? AND trang_thai=? LIMIT 1');
    $chk->execute($p);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO thanh_toan(id_user,so_tien,phuong_thuc_thanh_toan,trang_thai) VALUES (?,?,?,?)')->execute($p);
    }
}

// Vouchers with global/local coverage
$voucherConfigs = [
    ['code' => 'WELCOME', 'name' => 'Welcome', 'value' => 10.0, 'type' => 'percent', 'qty' => 100, 'expires' => '2025-12-31 23:59:59', 'global' => 1],
    ['code' => 'FREESHIP', 'name' => 'Free Ship', 'value' => 15000.0, 'type' => 'amount', 'qty' => 200, 'expires' => '2025-09-30 23:59:59', 'global' => 0],
    ['code' => 'BREAKFAST10', 'name' => 'Breakfast 10%', 'value' => 10.0, 'type' => 'percent', 'qty' => 80, 'expires' => '2025-05-31 23:59:59', 'global' => 0],
    ['code' => 'HUEAFTERNOON', 'name' => 'Tra chieu Hue', 'value' => 15.0, 'type' => 'percent', 'qty' => 40, 'expires' => '2025-08-31 23:59:59', 'global' => 0],
    ['code' => 'MEKONGGREEN', 'name' => 'Uu dai mon chay', 'value' => 20000.0, 'type' => 'amount', 'qty' => 60, 'expires' => '2025-07-31 23:59:59', 'global' => 0],
    ['code' => 'GLOBAL5', 'name' => 'Giam 5k toan he thong', 'value' => 5000.0, 'type' => 'amount', 'qty' => 500, 'expires' => '2025-12-31 23:59:59', 'global' => 1],
];
$voucherSelect = $pdo->prepare('SELECT id_voucher FROM voucher WHERE ma_voucher=? LIMIT 1');
$voucherUpdate = $pdo->prepare('UPDATE voucher SET ten_voucher=?, gia_tri_giam=?, loai_giam_gia=?, ngay_het_han=?, so_luong_con_lai=?, is_global=? WHERE id_voucher=?');
$voucherInsert = $pdo->prepare('INSERT INTO voucher(ma_voucher,ten_voucher,gia_tri_giam,loai_giam_gia,ngay_het_han,so_luong_con_lai,is_global) VALUES (?,?,?,?,?,?,?)');
$voucherIds = [];
foreach ($voucherConfigs as $cfg) {
    $voucherSelect->execute([$cfg['code']]);
    $vid = (int)($voucherSelect->fetchColumn() ?: 0);
    if ($vid) {
        $voucherUpdate->execute([$cfg['name'], $cfg['value'], $cfg['type'], $cfg['expires'], $cfg['qty'], $cfg['global'], $vid]);
    } else {
        $voucherInsert->execute([$cfg['code'], $cfg['name'], $cfg['value'], $cfg['type'], $cfg['expires'], $cfg['qty'], $cfg['global']]);
        $vid = (int)$pdo->lastInsertId();
    }
    $voucherIds[$cfg['code']] = $vid;
}

$voucherStoreMap = [
    ['code' => 'WELCOME', 'store' => $storeMain],
    ['code' => 'WELCOME', 'store' => $storeHue],
    ['code' => 'FREESHIP', 'store' => $storeBranch],
    ['code' => 'FREESHIP', 'store' => $storeHP],
    ['code' => 'BREAKFAST10', 'store' => $storeDN],
    ['code' => 'BREAKFAST10', 'store' => $storeDL],
    ['code' => 'HUEAFTERNOON', 'store' => $storeHue],
    ['code' => 'MEKONGGREEN', 'store' => $storeCT],
];
$voucherStoreStmt = $pdo->prepare('INSERT IGNORE INTO voucher_cua_hang(id_voucher,id_cua_hang) VALUES (?,?)');
foreach ($voucherStoreMap as $mapRow) {
    $vid = $voucherIds[$mapRow['code']] ?? null;
    if (!$vid) {
        $voucherSelect->execute([$mapRow['code']]);
        $vid = (int)($voucherSelect->fetchColumn() ?: 0);
    }
    $storeId = (int)($mapRow['store'] ?? 0);
    if ($vid && $storeId) {
        $voucherStoreStmt->execute([$vid, $storeId]);
    }
}

// Broaden interests
$pdo->exec("INSERT IGNORE INTO so_thich(ten_so_thich) VALUES ('Photography'),('Wellness'),('Coding'),('Minimalism')");
$extraUserInterests = [
    ['email' => 'ivy@example.com', 'names' => ['Cafe','Photography']],
    ['email' => 'gloria@example.com', 'names' => ['Music','Travel']],
    ['email' => 'george@example.com', 'names' => ['Wellness','Travel']],
];
foreach ($extraUserInterests as $row) {
    $uid = $getUserId($row['email']);
    if (!$uid) {
        continue;
    }
    foreach ($row['names'] as $name) {
        $iid = $ensureInterest($name);
        if ($iid) {
            $pdo->prepare('INSERT IGNORE INTO nguoi_dung_so_thich(id_user,id_so_thich) VALUES (?,?)')->execute([$uid, $iid]);
        }
    }
}

// Promotions and assignments
$promotionConfigs = [
    ['name' => 'Khai truong', 'desc' => 'Mo ta', 'start' => '2025-01-01 00:00:00', 'end' => '2025-12-31 23:59:59', 'apply' => 'voucher', 'scope' => 'toan_he_thong', 'status' => 'dang_hoat_dong'],
    ['name' => 'Combo brunch', 'desc' => 'Giam 30% mon brunch', 'start' => '2025-02-01 00:00:00', 'end' => '2025-03-01 23:59:59', 'apply' => 'voucher', 'scope' => 'gioi_han', 'status' => 'dang_hoat_dong'],
    ['name' => 'Tra chieu mien Trung', 'desc' => 'Giam 15% bo doi tra va banh', 'start' => '2025-02-10 00:00:00', 'end' => '2025-04-15 23:59:59', 'apply' => 'menu', 'scope' => 'gioi_han', 'status' => 'dang_hoat_dong'],
    ['name' => 'Green Monday', 'desc' => 'Giam 20% mon chay thu 2', 'start' => '2025-03-01 00:00:00', 'end' => '2025-06-01 23:59:59', 'apply' => 'menu', 'scope' => 'gioi_han', 'status' => 'dang_hoat_dong'],
    ['name' => 'Flash sale du lich', 'desc' => 'Combo cafe + tour du thuyen', 'start' => '2025-04-01 00:00:00', 'end' => '2025-04-30 23:59:59', 'apply' => 'combo', 'scope' => 'toan_he_thong', 'status' => 'tam_dung'],
];
$promoSelect = $pdo->prepare('SELECT id_khuyen_mai FROM khuyen_mai WHERE ten_chuong_trinh=? LIMIT 1');
$promoUpdate = $pdo->prepare('UPDATE khuyen_mai SET mo_ta=?, ngay_bat_dau=?, ngay_ket_thuc=?, loai_ap_dung=?, pham_vi_ap_dung=?, trang_thai=? WHERE id_khuyen_mai=?');
$promoInsert = $pdo->prepare('INSERT INTO khuyen_mai(ten_chuong_trinh,mo_ta,ngay_bat_dau,ngay_ket_thuc,loai_ap_dung,pham_vi_ap_dung,trang_thai) VALUES (?,?,?,?,?,?,?)');
$promoIds = [];
foreach ($promotionConfigs as $cfg) {
    $promoSelect->execute([$cfg['name']]);
    $pid = (int)($promoSelect->fetchColumn() ?: 0);
    if ($pid) {
        $promoUpdate->execute([$cfg['desc'], $cfg['start'], $cfg['end'], $cfg['apply'], $cfg['scope'], $cfg['status'], $pid]);
    } else {
        $promoInsert->execute([$cfg['name'], $cfg['desc'], $cfg['start'], $cfg['end'], $cfg['apply'], $cfg['scope'], $cfg['status']]);
        $pid = (int)$pdo->lastInsertId();
    }
    $promoIds[$cfg['name']] = $pid;
}

$promoStoreAssignments = [
    ['name' => 'Khai truong', 'store' => $storeMain, 'status' => 'da_duyet', 'request_at' => '2024-12-25 09:00:00', 'approved_at' => '2025-01-02 09:30:00', 'approver' => $adminId],
    ['name' => 'Khai truong', 'store' => $storeHue, 'status' => 'da_duyet', 'request_at' => '2025-02-05 10:00:00', 'approved_at' => '2025-02-06 11:15:00', 'approver' => $adminId],
    ['name' => 'Combo brunch', 'store' => $storeDN, 'status' => 'dang_hoat_dong', 'request_at' => '2025-02-02 08:00:00', 'approved_at' => '2025-02-03 08:30:00', 'approver' => $adminId],
    ['name' => 'Green Monday', 'store' => $storeCT, 'status' => 'cho_duyet', 'request_at' => '2025-03-05 07:30:00', 'approved_at' => null, 'approver' => null],
    ['name' => 'Tra chieu mien Trung', 'store' => $storeHue, 'status' => 'da_duyet', 'request_at' => '2025-02-10 12:00:00', 'approved_at' => '2025-02-11 09:45:00', 'approver' => $adminId],
    ['name' => 'Tra chieu mien Trung', 'store' => $storeDL, 'status' => 'tu_choi', 'request_at' => '2025-02-12 10:00:00', 'approved_at' => '2025-02-13 16:00:00', 'approver' => $adminId],
];
$promoStoreCheck = $pdo->prepare('SELECT id_khuyen_mai FROM khuyen_mai_cua_hang WHERE id_khuyen_mai=? AND id_cua_hang=? LIMIT 1');
$promoStoreInsert = $pdo->prepare('INSERT INTO khuyen_mai_cua_hang(id_khuyen_mai,id_cua_hang,trang_thai,ngay_yeu_cau,ngay_duyet,id_nguoi_duyet) VALUES (?,?,?,?,?,?)');
$promoStoreUpdate = $pdo->prepare('UPDATE khuyen_mai_cua_hang SET trang_thai=?, ngay_duyet=?, id_nguoi_duyet=? WHERE id_khuyen_mai=? AND id_cua_hang=?');
foreach ($promoStoreAssignments as $assign) {
    $pid = $promoIds[$assign['name']] ?? null;
    $storeId = (int)($assign['store'] ?? 0);
    if (!$pid || !$storeId) {
        continue;
    }
    $promoStoreCheck->execute([$pid, $storeId]);
    $exists = (bool)$promoStoreCheck->fetch();
    $requestAt = $assign['request_at'] ?? date('Y-m-d H:i:s');
    $approvedAt = $assign['approved_at'] ?? null;
    $approver = $assign['approver'];
    if ($exists) {
        $promoStoreUpdate->execute([$assign['status'], $approvedAt, $approver, $pid, $storeId]);
    } else {
        $promoStoreInsert->execute([$pid, $storeId, $assign['status'], $requestAt, $approvedAt, $approver]);
    }
}

// Banners in more placements
$moreBanners = [
    ['Home Spotlight', 'Khuyen mai toan he thong', 'https://picsum.photos/seed/banner5/1200/300', '/global', 'home_mid', 2, 1],
    ['Sidebar Promo', 'Voucher ngau nhien', 'https://picsum.photos/seed/banner6/600/800', '/vouchers', 'app_sidebar', 1, 1],
    ['Footer Info', 'Lien he tu van', 'https://picsum.photos/seed/banner7/1200/200', '/contact', 'home_bottom', 2, 1],
];
foreach ($moreBanners as $b) {
    $chk = $pdo->prepare('SELECT id_banner FROM banner WHERE tieu_de=? LIMIT 1');
    $chk->execute([$b[0]]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO banner(tieu_de,mo_ta,url_anh,link_url,vi_tri,thu_tu,active) VALUES (?,?,?,?,?,?,?)')->execute($b);
    }
}

// Chat conversations so chat APIs have more data
$chatSeeds = [
    [$getUserId('ivy@example.com'), $getUserId('shop@example.com'), 'Minh muon dat ban truoc 2 gio duoc khong?', 0],
    [$getUserId('shop@example.com'), $getUserId('ivy@example.com'), 'Duoc nhe ban, ban muon khung gio nao?', 0],
    [$getUserId('gloria@example.com'), $getUserId('shophue@example.com'), 'Quan co nhac acoustic toi thu 6 khong?', 0],
];
foreach ($chatSeeds as $m) {
    if (empty($m[0]) || empty($m[1])) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id_tin_nhan FROM tin_nhan WHERE id_nguoi_gui=? AND id_nguoi_nhan=? AND noi_dung=? LIMIT 1');
    $chk->execute([$m[0], $m[1], $m[2]]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO tin_nhan(id_nguoi_gui,id_nguoi_nhan,noi_dung,da_doc) VALUES (?,?,?,?)')->execute($m);
    }
}

// Wallet balances and histories
$ensureWallet($customerIvy, 150000);
$ensureWallet($ownerIdHue ?: $ownerId3, 450000);
$ensureWallet($ownerIdDaLat ?: $ownerId5, 350000);

$walletTxMore = [
    [$customerIvy, 150000, 'nap', 'Nap qua banking', 'wallet', null],
    [$customerIvy, 45000, 'tru', 'Thanh toan tai Hidden Gem Da Lat', 'order', null],
    [$ownerIdHue ?: $ownerId3, 120000, 'tru', 'Phi quang cao goi 1w', 'ad_request', null],
    [$ownerIdHue ?: $ownerId3, 120000, 'hoan', 'Hoan phi quang cao do tu choi', 'ad_refund', null],
];
foreach ($walletTxMore as $tx) {
    if (empty($tx[0])) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id_giao_dich FROM giao_dich_vi WHERE id_user=? AND so_tien=? AND loai=? AND mo_ta=? LIMIT 1');
    $chk->execute([$tx[0], $tx[1], $tx[2], $tx[3]]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO giao_dich_vi(id_user,so_tien,loai,mo_ta,tham_chieu_loai,tham_chieu_id) VALUES (?,?,?,?,?,?)')->execute($tx);
    }
}

// Advertising requests for new stores
$adMore = [
    [$storeHue, '1w', '2025-03-10', '2025-03-16', 500000, 'cho_duyet', null, null, null],
    [$storeHue, '1d', '2025-02-20', '2025-02-20', 100000, 'tu_choi', $adminId, '2025-02-18 10:30:00', null],
    [$storeHP, '1m', '2025-03-01', '2025-03-30', 1200000, 'da_duyet', $adminId, '2025-02-20 09:00:00', null],
];
foreach ($adMore as $ar) {
    if (empty($ar[0])) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id_yeu_cau FROM yeu_cau_quang_cao WHERE id_cua_hang=? AND goi=? AND ngay_bat_dau=? LIMIT 1');
    $chk->execute([$ar[0], $ar[1], $ar[2]]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO yeu_cau_quang_cao(id_cua_hang,goi,ngay_bat_dau,ngay_ket_thuc,gia,trang_thai,id_admin_duyet,ngay_duyet,id_giao_dich_tru) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute($ar);
    }
}

// Login attempts for security analytics
$loginExtra = [
    ['ivy@example.com', '192.168.1.10', 1],
    ['ivy@example.com', '192.168.1.10', 0],
    ['shophue@example.com', '203.113.10.5', 0],
];
foreach ($loginExtra as $row) {
    $pdo->prepare('INSERT INTO login_attempt(identifier,ip,success) VALUES (?,?,?)')->execute($row);
}

// Refresh & verification tokens for more flows
$shopId = $getUserId('shop@example.com');
if ($shopId) {
    $pdo->prepare("INSERT IGNORE INTO refresh_token(id_user,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 60 DAY))")
        ->execute([$shopId, hash('sha256', 'shop_refresh_token_demo')]);
}
if ($customerIvy) {
    $pdo->prepare("INSERT IGNORE INTO email_verification(id_user,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 3 DAY))")
        ->execute([$customerIvy, hash('sha256', 'ivy_verify_token')]);
    $pdo->prepare("INSERT IGNORE INTO password_reset(id_user,token_hash,expires_at,used_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 2 DAY), NULL)")
        ->execute([$customerIvy, hash('sha256', 'ivy_reset_token')]);
}

// Normalize joined dates for reporting
$joinedAtUpdates = [
    'admin@example.com' => '2024-10-01',
    'shop@example.com' => '2024-11-05',
    'shop2@example.com' => '2024-12-01',
    'shopcentral@example.com' => '2025-02-10',
    'ivy@example.com' => '2025-03-15',
];
foreach ($joinedAtUpdates as $email => $date) {
    $pdo->prepare('UPDATE users SET joined_at=? WHERE email=?')->execute([$date, $email]);
}

// Audit log examples
$auditExtras = [
    [$adminId, 'set_featured_store', 'store', $storeHue, json_encode(['featured' => true], JSON_UNESCAPED_UNICODE)],
];
foreach ($auditExtras as $log) {
    if (empty($log[3])) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id FROM audit_log WHERE actor_user_id=? AND action=? AND target_type=? AND target_id=? LIMIT 1');
    $chk->execute([$log[0], $log[1], $log[2], $log[3]]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO audit_log(actor_user_id,action,target_type,target_id,meta) VALUES (?,?,?,?,?)')->execute($log);
    }
}

// Additional user consents
$consentSeeds = [
    [$customerIvy, 'v1', 'v1', date('Y-m-d H:i:s', strtotime('-1 day'))],
    [$customerGloria, 'v1', 'v1', date('Y-m-d H:i:s', strtotime('-2 day'))],
];
foreach ($consentSeeds as $c) {
    if (empty($c[0])) {
        continue;
    }
    $chk = $pdo->prepare('SELECT id FROM user_consent WHERE id_user=? LIMIT 1');
    $chk->execute([$c[0]]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO user_consent(id_user,terms_version,privacy_version,consent_at) VALUES (?,?,?,?)')->execute($c);
    }
}

echo "Seeding done!\n";
