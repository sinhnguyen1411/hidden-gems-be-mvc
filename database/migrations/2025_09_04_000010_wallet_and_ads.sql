-- Wallet and Advertising (demo simulation)
USE hiddengems;

-- Wallet per user
CREATE TABLE IF NOT EXISTS vi_tien (
  id_user INT PRIMARY KEY,
  so_du DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cap_nhat_cuoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_user FOREIGN KEY (id_user) REFERENCES users(id_user)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Wallet transactions (audit log)
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

CREATE INDEX idx_wt_user_time ON giao_dich_vi(id_user, thoi_gian_tao);

-- Advertising requests by stores
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

CREATE INDEX idx_ad_status_time ON yeu_cau_quang_cao(trang_thai, ngay_bat_dau);
CREATE INDEX idx_ad_store_time ON yeu_cau_quang_cao(id_cua_hang, ngay_bat_dau);

