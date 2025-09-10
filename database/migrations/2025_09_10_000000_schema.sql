-- Consolidated full schema for Hidden Gems
CREATE DATABASE IF NOT EXISTS hiddengems
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE hiddengems;

-- 0) STATUS
CREATE TABLE IF NOT EXISTS status (
  id_trang_thai INT PRIMARY KEY AUTO_INCREMENT,
  ten_trang_thai VARCHAR(50) NOT NULL,
  nhom_trang_thai VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- 1) USERS
CREATE TABLE IF NOT EXISTS users (
  id_user INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(512) NOT NULL,
  full_name VARCHAR(255),
  role VARCHAR(50) NOT NULL DEFAULT 'customer',
  CHECK (role IN ('admin','shop','customer')),
  refresh_token VARCHAR(512),
  phone_number VARCHAR(20),
  joined_at DATE NOT NULL DEFAULT (CURRENT_DATE),
  email_verified_at DATETIME NULL,
  UNIQUE KEY uq_user_email (email),
  UNIQUE KEY uq_user_username (username),
  UNIQUE KEY uq_user_phone (phone_number)
) ENGINE=InnoDB;

-- 2) LOCATIONS
CREATE TABLE IF NOT EXISTS vi_tri (
  id_vi_tri INT PRIMARY KEY AUTO_INCREMENT,
  dia_chi_chi_tiet VARCHAR(255) NOT NULL,
  quan_huyen VARCHAR(100),
  thanh_pho VARCHAR(100),
  vi_do DECIMAL(10,8),
  kinh_do DECIMAL(11,8)
) ENGINE=InnoDB;

-- 3) STORES
CREATE TABLE IF NOT EXISTS cua_hang (
  id_cua_hang INT PRIMARY KEY AUTO_INCREMENT,
  id_cua_hang_cha INT NULL,
  id_chu_so_huu INT NOT NULL,
  ten_cua_hang VARCHAR(255) NOT NULL,
  mo_ta TEXT,
  diem_danh_gia_trung_binh DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  luot_xem INT NOT NULL DEFAULT 0,
  id_trang_thai INT,
  id_vi_tri INT,
  ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_store_parent  FOREIGN KEY (id_cua_hang_cha) REFERENCES cua_hang(id_cua_hang) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_store_owner   FOREIGN KEY (id_chu_so_huu) REFERENCES users(id_user)       ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_store_status  FOREIGN KEY (id_trang_thai) REFERENCES status(id_trang_thai) ON DELETE SET NULL  ON UPDATE CASCADE,
  CONSTRAINT fk_store_location FOREIGN KEY (id_vi_tri)   REFERENCES vi_tri(id_vi_tri)      ON DELETE SET NULL  ON UPDATE CASCADE,
  CONSTRAINT chk_store_avg CHECK (diem_danh_gia_trung_binh >= 0.0 AND diem_danh_gia_trung_binh <= 5.0)
) ENGINE=InnoDB;

-- 4) REVIEWS
CREATE TABLE IF NOT EXISTS danh_gia (
  id_danh_gia INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  id_cua_hang INT NOT NULL,
  diem_danh_gia INT NOT NULL,
  binh_luan TEXT,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_user  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_review_store FOREIGN KEY (id_cua_hang) REFERENCES cua_hang(id_cua_hang) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_rating_range CHECK (diem_danh_gia BETWEEN 1 AND 5),
  UNIQUE KEY uq_review_user_store (id_user, id_cua_hang)
) ENGINE=InnoDB;

-- 5) CATEGORIES
CREATE TABLE IF NOT EXISTS chuyen_muc (
  id_chuyen_muc INT PRIMARY KEY AUTO_INCREMENT,
  ten_chuyen_muc VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_category_name (ten_chuyen_muc)
) ENGINE=InnoDB;

-- 6) STORE-CATEGORY
CREATE TABLE IF NOT EXISTS cua_hang_chuyen_muc (
  id_cua_hang INT NOT NULL,
  id_chuyen_muc INT NOT NULL,
  PRIMARY KEY (id_cua_hang, id_chuyen_muc),
  CONSTRAINT fk_sc_store    FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sc_category FOREIGN KEY (id_chuyen_muc) REFERENCES chuyen_muc(id_chuyen_muc) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 7) FAVORITES
CREATE TABLE IF NOT EXISTS yeu_thich (
  id_user INT NOT NULL,
  id_cua_hang INT NOT NULL,
  thoi_gian_thich DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user, id_cua_hang),
  CONSTRAINT fk_fav_user  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fav_store FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 8) IMAGES
CREATE TABLE IF NOT EXISTS hinh_anh (
  id_anh INT PRIMARY KEY AUTO_INCREMENT,
  id_cua_hang INT NOT NULL,
  id_user INT,
  url_anh TEXT NOT NULL,
  is_anh_dai_dien BOOLEAN NOT NULL DEFAULT FALSE,
  thoi_gian_tai_len DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_img_store FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_img_user  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 9) BLOG
CREATE TABLE IF NOT EXISTS blog (
  id_blog INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  tieu_de VARCHAR(255) NOT NULL,
  noi_dung TEXT,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_blog_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 10) PAYMENTS
CREATE TABLE IF NOT EXISTS thanh_toan (
  id_thanh_toan INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  so_tien DECIMAL(10,2) NOT NULL CHECK (so_tien >= 0),
  phuong_thuc_thanh_toan VARCHAR(50) NOT NULL,
  trang_thai VARCHAR(50) NOT NULL,
  thoi_gian_thanh_toan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 11) VOUCHERS
CREATE TABLE IF NOT EXISTS voucher (
  id_voucher INT PRIMARY KEY AUTO_INCREMENT,
  ma_voucher VARCHAR(50) NOT NULL,
  ten_voucher VARCHAR(255),
  gia_tri_giam DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  loai_giam_gia VARCHAR(20) NOT NULL,
  ngay_het_han DATETIME,
  so_luong_con_lai INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_voucher_code (ma_voucher),
  CHECK (gia_tri_giam >= 0),
  CHECK (loai_giam_gia IN ('percent','amount'))
) ENGINE=InnoDB;

-- 12) INTERESTS
CREATE TABLE IF NOT EXISTS so_thich (
  id_so_thich INT PRIMARY KEY AUTO_INCREMENT,
  ten_so_thich VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_interest_name (ten_so_thich)
) ENGINE=InnoDB;

-- 13) USER-INTEREST
CREATE TABLE IF NOT EXISTS nguoi_dung_so_thich (
  id_user INT NOT NULL,
  id_so_thich INT NOT NULL,
  PRIMARY KEY (id_user, id_so_thich),
  CONSTRAINT fk_ui_user     FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ui_interest FOREIGN KEY (id_so_thich)   REFERENCES so_thich(id_so_thich)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 14) VOUCHER-STORE
CREATE TABLE IF NOT EXISTS voucher_cua_hang (
  id_voucher INT NOT NULL,
  id_cua_hang INT NOT NULL,
  PRIMARY KEY (id_voucher, id_cua_hang),
  CONSTRAINT fk_vs_voucher FOREIGN KEY (id_voucher) REFERENCES voucher(id_voucher)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_vs_store   FOREIGN KEY (id_cua_hang) REFERENCES cua_hang(id_cua_hang) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 15) PROMOTIONS
CREATE TABLE IF NOT EXISTS khuyen_mai (
  id_khuyen_mai INT PRIMARY KEY AUTO_INCREMENT,
  ten_chuong_trinh VARCHAR(255) NOT NULL,
  mo_ta TEXT,
  ngay_bat_dau DATETIME NOT NULL,
  ngay_ket_thuc DATETIME NOT NULL,
  loai_ap_dung VARCHAR(50),
  pham_vi_ap_dung VARCHAR(50) DEFAULT 'gioi_han',
  trang_thai VARCHAR(50) NOT NULL DEFAULT 'dang_hoat_dong',
  CHECK (ngay_ket_thuc >= ngay_bat_dau)
) ENGINE=InnoDB;

-- 16) PROMOTION-STORE (with status + reviewer)
CREATE TABLE IF NOT EXISTS khuyen_mai_cua_hang (
  id_khuyen_mai INT NOT NULL,
  id_cua_hang INT NOT NULL,
  trang_thai VARCHAR(50) NOT NULL DEFAULT 'cho_duyet',
  ngay_yeu_cau DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_duyet DATETIME NULL,
  id_nguoi_duyet INT NULL,
  PRIMARY KEY (id_khuyen_mai, id_cua_hang),
  CONSTRAINT fk_ps_promo FOREIGN KEY (id_khuyen_mai) REFERENCES khuyen_mai(id_khuyen_mai) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ps_store FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ps_approver FOREIGN KEY (id_nguoi_duyet) REFERENCES users(id_user) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 17) BANNERS
CREATE TABLE IF NOT EXISTS banner (
  id_banner INT PRIMARY KEY AUTO_INCREMENT,
  tieu_de VARCHAR(255),
  mo_ta TEXT,
  url_anh TEXT NOT NULL,
  link_url TEXT,
  vi_tri VARCHAR(50),
  thu_tu INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 18) MESSAGES (CHAT)
CREATE TABLE IF NOT EXISTS tin_nhan (
  id_tin_nhan INT PRIMARY KEY AUTO_INCREMENT,
  id_nguoi_gui INT NOT NULL,
  id_nguoi_nhan INT NOT NULL,
  noi_dung TEXT NOT NULL,
  da_doc BOOLEAN NOT NULL DEFAULT FALSE,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_from FOREIGN KEY (id_nguoi_gui) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_msg_to   FOREIGN KEY (id_nguoi_nhan) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 19) WALLET
CREATE TABLE IF NOT EXISTS vi_tien (
  id_user INT PRIMARY KEY,
  so_du DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cap_nhat_cuoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS giao_dich_vi (
  id_giao_dich INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  so_tien DECIMAL(12,2) NOT NULL,
  loai VARCHAR(20) NOT NULL,
  mo_ta TEXT,
  tham_chieu_loai VARCHAR(50),
  tham_chieu_id INT,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wt_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CHECK (so_tien >= 0),
  CHECK (loai IN ('nap','tru','hoan'))
) ENGINE=InnoDB;

-- 20) AD REQUESTS
CREATE TABLE IF NOT EXISTS yeu_cau_quang_cao (
  id_yeu_cau INT PRIMARY KEY AUTO_INCREMENT,
  id_cua_hang INT NOT NULL,
  goi VARCHAR(10) NOT NULL,
  ngay_bat_dau DATETIME NOT NULL,
  ngay_ket_thuc DATETIME NOT NULL,
  gia DECIMAL(12,2) NOT NULL,
  trang_thai VARCHAR(20) NOT NULL DEFAULT 'cho_duyet',
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_admin_duyet INT NULL,
  ngay_duyet DATETIME NULL,
  id_giao_dich_tru INT NULL,
  CONSTRAINT fk_ad_store FOREIGN KEY (id_cua_hang) REFERENCES cua_hang(id_cua_hang)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ad_tx FOREIGN KEY (id_giao_dich_tru) REFERENCES giao_dich_vi(id_giao_dich)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ad_admin FOREIGN KEY (id_admin_duyet) REFERENCES users(id_user)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CHECK (trang_thai IN ('cho_duyet','da_duyet','tu_choi')),
  CHECK (ngay_ket_thuc >= ngay_bat_dau)
) ENGINE=InnoDB;

-- 21) SECURITY / AUTH AUX TABLES
CREATE TABLE IF NOT EXISTS login_attempt (
  id INT PRIMARY KEY AUTO_INCREMENT,
  identifier VARCHAR(255) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la_identifier_time (identifier, created_at),
  INDEX idx_la_ip_time (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refresh_token (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  token_hash VARCHAR(128) NOT NULL,
  user_agent VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rt_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_rt_token (token_hash),
  INDEX idx_rt_user_time (id_user, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  token_hash VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_pr_token (token_hash),
  INDEX idx_pr_user_time (id_user, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_verification (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  token_hash VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ev_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_ev_token (token_hash),
  INDEX idx_ev_user_time (id_user, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  actor_user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) NULL,
  target_id INT NULL,
  meta TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_al_actor FOREIGN KEY (actor_user_id) REFERENCES users(id_user)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_al_action_time (action, created_at)
) ENGINE=InnoDB;

-- 22) USER CONSENT
CREATE TABLE IF NOT EXISTS user_consent (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  terms_version VARCHAR(50) NULL,
  privacy_version VARCHAR(50) NULL,
  consent_at DATETIME NOT NULL,
  CONSTRAINT fk_uc_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_uc_user_time (id_user, consent_at)
) ENGINE=InnoDB;

-- USEFUL INDEXES
CREATE INDEX IF NOT EXISTS idx_store_name   ON cua_hang(ten_cua_hang);
CREATE INDEX IF NOT EXISTS idx_store_status ON cua_hang(id_trang_thai);
CREATE INDEX IF NOT EXISTS idx_location_city ON vi_tri(thanh_pho);
CREATE INDEX IF NOT EXISTS idx_review_store ON danh_gia(id_cua_hang);
CREATE INDEX IF NOT EXISTS idx_review_user ON danh_gia(id_user);
CREATE INDEX IF NOT EXISTS idx_image_store  ON hinh_anh(id_cua_hang);
CREATE INDEX IF NOT EXISTS idx_vs_voucher ON voucher_cua_hang(id_voucher);
CREATE INDEX IF NOT EXISTS idx_vs_store   ON voucher_cua_hang(id_cua_hang);
CREATE INDEX IF NOT EXISTS idx_msg_pair_time ON tin_nhan(id_nguoi_gui, id_nguoi_nhan, thoi_gian_tao);
CREATE INDEX IF NOT EXISTS idx_banner_active ON banner(active, vi_tri);
CREATE INDEX IF NOT EXISTS idx_wt_user_time ON giao_dich_vi(id_user, thoi_gian_tao);
CREATE INDEX IF NOT EXISTS idx_ad_status_time ON yeu_cau_quang_cao(trang_thai, ngay_bat_dau);
CREATE INDEX IF NOT EXISTS idx_ad_store_time ON yeu_cau_quang_cao(id_cua_hang, ngay_bat_dau);

