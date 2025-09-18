ALTER TABLE danh_gia
  ADD COLUMN trang_thai VARCHAR(20) NOT NULL DEFAULT 'cho_duyet' AFTER binh_luan;

UPDATE danh_gia SET trang_thai='da_duyet' WHERE trang_thai='cho_duyet';

ALTER TABLE blog
  ADD COLUMN trang_thai VARCHAR(20) NOT NULL DEFAULT 'nhap' AFTER noi_dung;

UPDATE blog SET trang_thai='cong_bo';

ALTER TABLE voucher
  ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0 AFTER so_luong_con_lai;

CREATE TABLE IF NOT EXISTS binh_luan (
  id_binh_luan INT PRIMARY KEY AUTO_INCREMENT,
  id_user INT NOT NULL,
  loai_doi_tuong VARCHAR(20) NOT NULL,
  id_tham_chieu INT NOT NULL,
  noi_dung TEXT NOT NULL,
  trang_thai VARCHAR(20) NOT NULL DEFAULT 'cho_duyet',
  thoi_gian_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE tin_nhan
  ADD INDEX idx_msg_recipient_read (id_nguoi_nhan, da_doc, id_tin_nhan);