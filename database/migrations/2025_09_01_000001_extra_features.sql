-- Extra features: branches, banners, chat, promo approvals
USE hiddengems;

-- 1) Branch relationship for stores
ALTER TABLE cua_hang
  ADD COLUMN id_cua_hang_cha INT NULL AFTER id_cua_hang,
  ADD CONSTRAINT fk_store_parent FOREIGN KEY (id_cua_hang_cha)
    REFERENCES cua_hang(id_cua_hang) ON DELETE SET NULL ON UPDATE CASCADE;

-- 2) Promotion participation status tracking
ALTER TABLE khuyen_mai_cua_hang
  ADD COLUMN trang_thai VARCHAR(50) NOT NULL DEFAULT 'cho_duyet' AFTER id_cua_hang,
  ADD COLUMN ngay_yeu_cau DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER trang_thai,
  ADD COLUMN ngay_duyet DATETIME NULL AFTER ngay_yeu_cau,
  ADD COLUMN id_nguoi_duyet INT NULL AFTER ngay_duyet,
  ADD CONSTRAINT fk_ps_approver FOREIGN KEY (id_nguoi_duyet) REFERENCES users(id_user)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 3) Banners for general pages
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

-- 4) Simple direct messages (chat)
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

CREATE INDEX idx_msg_pair_time ON tin_nhan(id_nguoi_gui, id_nguoi_nhan, thoi_gian_tao);
CREATE INDEX idx_banner_active ON banner(active, vi_tri);

