
CREATE DATABASE IF NOT EXISTS hiddengems
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE hiddengems;

-- 0) TRẠNG THÁI
CREATE TABLE status (
  id_trang_thai INT PRIMARY KEY AUTO_INCREMENT,
  ten_trang_thai VARCHAR(50) NOT NULL,
  nhom_trang_thai VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- 1) NGƯỜI DÙNG
CREATE TABLE users (
  id_user INT PRIMARY KEY AUTO_INCREMENT,
  ten_dang_nhap VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  mat_khau_ma_hoa VARCHAR(255) NOT NULL,
  ho_va_ten VARCHAR(255),
  vai_tro VARCHAR(50) NOT NULL DEFAULT 'user',
  refresh_token VARCHAR(255),
  so_dien_thoai VARCHAR(20),
  ngay_tham_gia DATE NOT NULL DEFAULT (CURRENT_DATE),
  UNIQUE KEY uq_user_email (email),
  UNIQUE KEY uq_user_username (ten_dang_nhap),
  UNIQUE KEY uq_user_phone (so_dien_thoai)
) ENGINE=InnoDB;

-- 3) VỊ TRÍ
CREATE TABLE vi_tri (
  id_vi_tri INT PRIMARY KEY AUTO_INCREMENT,
  dia_chi_chi_tiet VARCHAR(255) NOT NULL,
  quan_huyen VARCHAR(100),
  thanh_pho VARCHAR(100),
  vi_do DECIMAL(10,8),
  kinh_do DECIMAL(11,8)
) ENGINE=InnoDB;

-- 2) CỬA HÀNG
CREATE TABLE cua_hang (
  id_cua_hang INT PRIMARY KEY AUTO_INCREMENT,
  id_chu_so_huu INT NOT NULL,
  ten_cua_hang VARCHAR(255) NOT NULL,
  mo_ta TEXT,
  diem_danh_gia_trung_binh DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  luot_xem INT NOT NULL DEFAULT 0,
  id_trang_thai INT,
  id_vi_tri INT,
  ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_store_owner   FOREIGN KEY (id_chu_so_huu) REFERENCES users(id_user) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_store_status  FOREIGN KEY (id_trang_thai) REFERENCES status(id_trang_thai)       ON DELETE SET NULL  ON UPDATE CASCADE,
  CONSTRAINT fk_store_location FOREIGN KEY (id_vi_tri)    REFERENCES vi_tri(id_vi_tri)           ON DELETE SET NULL  ON UPDATE CASCADE,
  CONSTRAINT chk_store_avg CHECK (diem_danh_gia_trung_binh >= 0.0 AND diem_danh_gia_trung_binh <= 5.0)
) ENGINE=InnoDB;

-- 4) ĐÁNH GIÁ
CREATE TABLE danh_gia (
  id_danh_gia INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  id_cua_hang INT NOT NULL,
  diem_danh_gia INT NOT NULL,
  binh_luan TEXT,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_user  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_review_store FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_rating_range CHECK (diem_danh_gia BETWEEN 1 AND 5),
  UNIQUE KEY uq_review_user_store (id_user, id_cua_hang)
) ENGINE=InnoDB;

-- 5) CHUYÊN MỤC
CREATE TABLE chuyen_muc (
  id_chuyen_muc INT PRIMARY KEY AUTO_INCREMENT,
  ten_chuyen_muc VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_category_name (ten_chuyen_muc)
) ENGINE=InnoDB;

-- 6) QUAN HỆ CỬA HÀNG - CHUYÊN MỤC
CREATE TABLE cua_hang_chuyen_muc (
  id_cua_hang INT NOT NULL,
  id_chuyen_muc INT NOT NULL,
  PRIMARY KEY (id_cua_hang, id_chuyen_muc),
  CONSTRAINT fk_sc_store    FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sc_category FOREIGN KEY (id_chuyen_muc) REFERENCES chuyen_muc(id_chuyen_muc) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 7) YÊU THÍCH
CREATE TABLE yeu_thich (
  id_user INT NOT NULL,
  id_cua_hang INT NOT NULL,
  thoi_gian_thich DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user, id_cua_hang),
  CONSTRAINT fk_fav_user  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fav_store FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 8) HÌNH ẢNH
CREATE TABLE hinh_anh (
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
CREATE TABLE blog (
  id_blog INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  tieu_de VARCHAR(255) NOT NULL,
  noi_dung TEXT,
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_blog_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 10) THANH TOÁN
CREATE TABLE thanh_toan (
  id_thanh_toan INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  so_tien DECIMAL(10,2) NOT NULL CHECK (so_tien >= 0),
  phuong_thuc_thanh_toan VARCHAR(50) NOT NULL,
  trang_thai VARCHAR(50) NOT NULL,
  thoi_gian_thanh_toan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 11) VOUCHER
CREATE TABLE voucher (
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

-- 12) SỞ THÍCH
CREATE TABLE so_thich (
  id_so_thich INT PRIMARY KEY AUTO_INCREMENT,
  ten_so_thich VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_interest_name (ten_so_thich)
) ENGINE=InnoDB;

-- 13) QUAN HỆ SỞ THÍCH NGƯỜI DÙNG
CREATE TABLE nguoi_dung_so_thich (
  id_user INT NOT NULL,
  id_so_thich INT NOT NULL,
  PRIMARY KEY (id_user, id_so_thich),
  CONSTRAINT fk_ui_user     FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ui_interest FOREIGN KEY (id_so_thich)   REFERENCES so_thich(id_so_thich)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 14) QUAN HỆ VOUCHER - CỬA HÀNG
CREATE TABLE voucher_cua_hang (
  id_voucher INT NOT NULL,
  id_cua_hang INT NOT NULL,
  PRIMARY KEY (id_voucher, id_cua_hang),
  CONSTRAINT fk_vs_voucher FOREIGN KEY (id_voucher) REFERENCES voucher(id_voucher)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_vs_store   FOREIGN KEY (id_cua_hang) REFERENCES cua_hang(id_cua_hang) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 15) KHUYẾN MÃI
CREATE TABLE khuyen_mai (
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

-- 16) QUAN HỆ KHUYẾN MÃI - CỬA HÀNG
CREATE TABLE khuyen_mai_cua_hang (
  id_khuyen_mai INT NOT NULL,
  id_cua_hang INT NOT NULL,
  PRIMARY KEY (id_khuyen_mai, id_cua_hang),
  CONSTRAINT fk_ps_promo FOREIGN KEY (id_khuyen_mai) REFERENCES khuyen_mai(id_khuyen_mai) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ps_store FOREIGN KEY (id_cua_hang)   REFERENCES cua_hang(id_cua_hang)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- CHỈ MỤC HỮU ÍCH
CREATE INDEX idx_store_name   ON cua_hang(ten_cua_hang);
CREATE INDEX idx_store_status ON cua_hang(id_trang_thai);
CREATE INDEX idx_location_city ON vi_tri(thanh_pho);
CREATE INDEX idx_review_store ON danh_gia(id_cua_hang);
CREATE INDEX idx_image_store  ON hinh_anh(id_cua_hang);

