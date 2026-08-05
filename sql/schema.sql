-- =====================================================================
--  SISTEM KEJOHANAN FUTSAL MERDEKA KEPALA BATAS 2026
--  Skema Database MySQL / MariaDB
--  Penganjur: Pusat Kecemerlangan As-Syafiee (PAKSY), Kepala Batas
--  Tarikh kejohanan: 30 Ogos 2026
-- =====================================================================
--  Import fail ini melalui phpMyAdmin (cPanel) ke dalam database kosong.
--  Selepas import, buka /api/setup.php sekali untuk cipta Super Admin.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS pendaftaran;
DROP TABLE IF EXISTS draw;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS settings;

-- ---------------------------------------------------------------------
-- Tetapan umum sistem (key-value)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  k              VARCHAR(64)  NOT NULL PRIMARY KEY,
  v              TEXT         NULL,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (k, v) VALUES
  ('nama_kejohanan',   'KEJOHANAN FUTSAL MERDEKA KEPALA BATAS 2026'),
  ('nama_penganjur',   'SAMFIRE FC dengan kerjasama PAKSY, Kepala Batas'),
  ('tarikh_kejohanan', '2026-08-30'),
  ('masa_mula',        '08:30'),
  ('lokasi',           'Gelanggang Futsal PAKSY, Kepala Batas, Pulau Pinang'),
  ('keputusan_dikunci','0'),
  ('pengumuman',       ''),
  ('pendaftaran_buka', '1'),
  ('yuran',            'RM150'),
  ('telefon_urusetia', '019-123 4567'),
  ('url_website',      'https://samfirefc.com'),
  ('url_daftar_ahli',  'https://samfirefc.com');

-- ---------------------------------------------------------------------
-- Akaun admin (tiada pendaftaran terbuka — seed melalui setup.php sahaja)
-- ---------------------------------------------------------------------
CREATE TABLE admins (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama           VARCHAR(100) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('admin','super') NOT NULL DEFAULT 'admin',
  aktif          TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Percubaan log masuk (rate limit: 5 gagal -> kunci 15 minit)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(190) NOT NULL,
  ip             VARCHAR(45)  NOT NULL,
  berjaya        TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_email_time (email, created_at),
  KEY idx_la_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 24 pasukan: 8 kumpulan (A-H) x 3 slot
-- ---------------------------------------------------------------------
CREATE TABLE teams (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama           VARCHAR(80)  NOT NULL DEFAULT '',
  singkatan      VARCHAR(12)  NOT NULL DEFAULT '',
  pengurus       VARCHAR(80)  NOT NULL DEFAULT '',
  telefon        VARCHAR(30)  NOT NULL DEFAULT '',
  logo           VARCHAR(120) NOT NULL DEFAULT '',
  kumpulan       CHAR(1)      NOT NULL,
  slot           TINYINT      NOT NULL,
  -- pemecah seri manual (undian) bila mata/beza gol/jumlah gol/head-to-head sama
  -- angka lebih KECIL = kedudukan lebih tinggi. 0 = belum ditetapkan.
  tiebreak       INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teams_slot (kumpulan, slot),
  KEY idx_teams_kumpulan (kumpulan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO teams (nama, kumpulan, slot) VALUES
 ('', 'A',1),('', 'A',2),('', 'A',3),
 ('', 'B',1),('', 'B',2),('', 'B',3),
 ('', 'C',1),('', 'C',2),('', 'C',3),
 ('', 'D',1),('', 'D',2),('', 'D',3),
 ('', 'E',1),('', 'E',2),('', 'E',3),
 ('', 'F',1),('', 'F',2),('', 'F',3),
 ('', 'G',1),('', 'G',2),('', 'G',3),
 ('', 'H',1),('', 'H',2),('', 'H',3);

-- ---------------------------------------------------------------------
-- Senarai pemain (opsyenal)
-- ---------------------------------------------------------------------
CREATE TABLE players (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id        INT UNSIGNED NOT NULL,
  nama           VARCHAR(80)  NOT NULL,
  no_jersi       VARCHAR(4)   NOT NULL DEFAULT '',
  no_kp          VARCHAR(20)  NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_players_team (team_id),
  CONSTRAINT fk_players_team FOREIGN KEY (team_id)
    REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 32 perlawanan
--   peringkat : grup | sa | ss | third | final
--   *_sumber  : rujukan pasukan sebelum ditentukan, contoh
--               'UNDI:1'  = kedudukan ke-1 hasil undian suku akhir
--               'W:SA1'   = pemenang perlawanan SA1
--               'L:SS1'   = yang kalah perlawanan SS1
--   version   : optimistic locking - elak dua admin tindih skor
-- ---------------------------------------------------------------------
CREATE TABLE matches (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kod            VARCHAR(10)  NOT NULL,
  peringkat      ENUM('grup','sa','ss','third','final') NOT NULL,
  kumpulan       CHAR(1)      NULL,
  urutan         INT          NOT NULL,
  gelanggang     TINYINT      NOT NULL DEFAULT 1,
  masa_jadual    TIME         NOT NULL,
  tempoh_minit   TINYINT      NOT NULL DEFAULT 11,
  team_home_id   INT UNSIGNED NULL,
  team_away_id   INT UNSIGNED NULL,
  home_sumber    VARCHAR(16)  NOT NULL DEFAULT '',
  away_sumber    VARCHAR(16)  NOT NULL DEFAULT '',
  skor_home      TINYINT      NULL,
  skor_away      TINYINT      NULL,
  penalti_home   TINYINT      NULL,
  penalti_away   TINYINT      NULL,
  status         ENUM('scheduled','live','done') NOT NULL DEFAULT 'scheduled',
  catatan        VARCHAR(200) NOT NULL DEFAULT '',
  updated_by     INT UNSIGNED NULL,
  updated_at     DATETIME     NULL,
  version        INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_matches_kod (kod),
  KEY idx_matches_urutan (urutan),
  KEY idx_matches_peringkat (peringkat),
  CONSTRAINT fk_m_home FOREIGN KEY (team_home_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_m_away FOREIGN KEY (team_away_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 24 perlawanan peringkat kumpulan -------------------------------
-- Susunan slot: 2 gelanggang serentak, 12 slot x 14 minit (08:30 - 11:15)
-- Setiap kumpulan berehat 3 slot (~56 minit) antara perlawanan.
INSERT INTO matches (kod, peringkat, kumpulan, urutan, gelanggang, masa_jadual, tempoh_minit, team_home_id, team_away_id) VALUES
-- Slot 1  08:30
('A1','grup','A', 1,1,'08:30',11,(SELECT id FROM teams WHERE kumpulan='A' AND slot=1),(SELECT id FROM teams WHERE kumpulan='A' AND slot=2)),
('B1','grup','B', 2,2,'08:30',11,(SELECT id FROM teams WHERE kumpulan='B' AND slot=1),(SELECT id FROM teams WHERE kumpulan='B' AND slot=2)),
-- Slot 2  08:44
('C1','grup','C', 3,1,'08:44',11,(SELECT id FROM teams WHERE kumpulan='C' AND slot=1),(SELECT id FROM teams WHERE kumpulan='C' AND slot=2)),
('D1','grup','D', 4,2,'08:44',11,(SELECT id FROM teams WHERE kumpulan='D' AND slot=1),(SELECT id FROM teams WHERE kumpulan='D' AND slot=2)),
-- Slot 3  08:58
('E1','grup','E', 5,1,'08:58',11,(SELECT id FROM teams WHERE kumpulan='E' AND slot=1),(SELECT id FROM teams WHERE kumpulan='E' AND slot=2)),
('F1','grup','F', 6,2,'08:58',11,(SELECT id FROM teams WHERE kumpulan='F' AND slot=1),(SELECT id FROM teams WHERE kumpulan='F' AND slot=2)),
-- Slot 4  09:12
('G1','grup','G', 7,1,'09:12',11,(SELECT id FROM teams WHERE kumpulan='G' AND slot=1),(SELECT id FROM teams WHERE kumpulan='G' AND slot=2)),
('H1','grup','H', 8,2,'09:12',11,(SELECT id FROM teams WHERE kumpulan='H' AND slot=1),(SELECT id FROM teams WHERE kumpulan='H' AND slot=2)),
-- Slot 5  09:26
('A2','grup','A', 9,1,'09:26',11,(SELECT id FROM teams WHERE kumpulan='A' AND slot=3),(SELECT id FROM teams WHERE kumpulan='A' AND slot=1)),
('B2','grup','B',10,2,'09:26',11,(SELECT id FROM teams WHERE kumpulan='B' AND slot=3),(SELECT id FROM teams WHERE kumpulan='B' AND slot=1)),
-- Slot 6  09:40
('C2','grup','C',11,1,'09:40',11,(SELECT id FROM teams WHERE kumpulan='C' AND slot=3),(SELECT id FROM teams WHERE kumpulan='C' AND slot=1)),
('D2','grup','D',12,2,'09:40',11,(SELECT id FROM teams WHERE kumpulan='D' AND slot=3),(SELECT id FROM teams WHERE kumpulan='D' AND slot=1)),
-- Slot 7  09:54
('E2','grup','E',13,1,'09:54',11,(SELECT id FROM teams WHERE kumpulan='E' AND slot=3),(SELECT id FROM teams WHERE kumpulan='E' AND slot=1)),
('F2','grup','F',14,2,'09:54',11,(SELECT id FROM teams WHERE kumpulan='F' AND slot=3),(SELECT id FROM teams WHERE kumpulan='F' AND slot=1)),
-- Slot 8  10:08
('G2','grup','G',15,1,'10:08',11,(SELECT id FROM teams WHERE kumpulan='G' AND slot=3),(SELECT id FROM teams WHERE kumpulan='G' AND slot=1)),
('H2','grup','H',16,2,'10:08',11,(SELECT id FROM teams WHERE kumpulan='H' AND slot=3),(SELECT id FROM teams WHERE kumpulan='H' AND slot=1)),
-- Slot 9  10:22
('A3','grup','A',17,1,'10:22',11,(SELECT id FROM teams WHERE kumpulan='A' AND slot=2),(SELECT id FROM teams WHERE kumpulan='A' AND slot=3)),
('B3','grup','B',18,2,'10:22',11,(SELECT id FROM teams WHERE kumpulan='B' AND slot=2),(SELECT id FROM teams WHERE kumpulan='B' AND slot=3)),
-- Slot 10 10:36
('C3','grup','C',19,1,'10:36',11,(SELECT id FROM teams WHERE kumpulan='C' AND slot=2),(SELECT id FROM teams WHERE kumpulan='C' AND slot=3)),
('D3','grup','D',20,2,'10:36',11,(SELECT id FROM teams WHERE kumpulan='D' AND slot=2),(SELECT id FROM teams WHERE kumpulan='D' AND slot=3)),
-- Slot 11 10:50
('E3','grup','E',21,1,'10:50',11,(SELECT id FROM teams WHERE kumpulan='E' AND slot=2),(SELECT id FROM teams WHERE kumpulan='E' AND slot=3)),
('F3','grup','F',22,2,'10:50',11,(SELECT id FROM teams WHERE kumpulan='F' AND slot=2),(SELECT id FROM teams WHERE kumpulan='F' AND slot=3)),
-- Slot 12 11:04
('G3','grup','G',23,1,'11:04',11,(SELECT id FROM teams WHERE kumpulan='G' AND slot=2),(SELECT id FROM teams WHERE kumpulan='G' AND slot=3)),
('H3','grup','H',24,2,'11:04',11,(SELECT id FROM teams WHERE kumpulan='H' AND slot=2),(SELECT id FROM teams WHERE kumpulan='H' AND slot=3));

-- --- 8 perlawanan peringkat kalah mati -------------------------------
INSERT INTO matches (kod, peringkat, kumpulan, urutan, gelanggang, masa_jadual, tempoh_minit, home_sumber, away_sumber) VALUES
('SA1','sa',   NULL,31,1,'11:35',11,'UNDI:1','UNDI:2'),
('SA2','sa',   NULL,32,2,'11:35',11,'UNDI:3','UNDI:4'),
('SA3','sa',   NULL,33,1,'11:55',11,'UNDI:5','UNDI:6'),
('SA4','sa',   NULL,34,2,'11:55',11,'UNDI:7','UNDI:8'),
('SS1','ss',   NULL,41,1,'14:30',15,'W:SA1','W:SA2'),
('SS2','ss',   NULL,42,2,'14:30',15,'W:SA3','W:SA4'),
('T3','third', NULL,51,1,'15:10',15,'L:SS1','L:SS2'),
('FINAL','final',NULL,61,1,'15:40',15,'W:SS1','W:SS2');

-- ---------------------------------------------------------------------
-- Pendaftaran pasukan (borang awam) — disemak & diluluskan oleh admin
-- ---------------------------------------------------------------------
CREATE TABLE pendaftaran (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama           VARCHAR(80)  NOT NULL,
  pengurus       VARCHAR(80)  NOT NULL,
  telefon        VARCHAR(30)  NOT NULL,
  pemain_json    TEXT         NULL,
  logo           VARCHAR(120) NOT NULL DEFAULT '',
  status         ENUM('baru','lulus','tolak') NOT NULL DEFAULT 'baru',
  team_id        INT UNSIGNED NULL,
  catatan        VARCHAR(200) NOT NULL DEFAULT '',
  ip             VARCHAR(45)  NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_daftar_status (status),
  KEY idx_daftar_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Undian suku akhir — hanya SATU baris dibenarkan wujud
-- ---------------------------------------------------------------------
CREATE TABLE draw (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dijalankan_oleh INT UNSIGNED NULL,
  nama_pelaksana VARCHAR(100) NOT NULL DEFAULT '',
  hasil_json     TEXT         NOT NULL,
  seed_bukti     VARCHAR(64)  NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Log aktiviti — setiap perubahan direkod
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id       INT UNSIGNED NULL,
  admin_nama     VARCHAR(100) NOT NULL DEFAULT '',
  tindakan       VARCHAR(60)  NOT NULL,
  butiran_json   TEXT         NULL,
  ip             VARCHAR(45)  NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
