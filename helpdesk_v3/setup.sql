-- ============================================================
-- HELPDESK IT v3 — Politeknik NSC Surabaya
-- UUID, MD5 Password, Chat System, Auto-close
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpdesk_v3;
USE helpdesk_v3;

-- ── ADMIN (login) ──────────────────────────────────────────
CREATE TABLE admins (
    uid          CHAR(36) PRIMARY KEY,
    nama         VARCHAR(100) NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(32) NOT NULL COMMENT 'MD5',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── KATEGORI ───────────────────────────────────────────────
CREATE TABLE kategori (
    uid           CHAR(36) PRIMARY KEY,
    nama_kategori ENUM('Hardware','Software','Jaringan','Sistem Akademik') NOT NULL,
    deskripsi     TEXT
) ENGINE=InnoDB;

-- ── TICKETS (user tanpa login) ─────────────────────────────
CREATE TABLE tickets (
    uid          CHAR(36) PRIMARY KEY,
    kode_ticket  VARCHAR(20) NOT NULL UNIQUE,
    chat_token   CHAR(36) NOT NULL UNIQUE COMMENT 'UUID link untuk user akses chat',

    -- Info pelapor
    nama_pelapor VARCHAR(100) NOT NULL,
    email        VARCHAR(100) NOT NULL,
    no_telpon    VARCHAR(20)  NOT NULL,

    -- Tiket
    uid_kategori CHAR(36) NOT NULL,
    judul        VARCHAR(150) NOT NULL,
    deskripsi    TEXT NOT NULL,
    foto         VARCHAR(255) NULL COMMENT 'path file upload',

    prioritas    ENUM('Low','Medium','High') DEFAULT 'Medium',
    status       ENUM('Open','On Progress','Solved','Waiting Confirm','Closed') DEFAULT 'Open',

    -- Auto close
    solved_at    TIMESTAMP NULL COMMENT 'waktu admin set solved',
    confirmed_at TIMESTAMP NULL,
    auto_close_at TIMESTAMP NULL COMMENT 'solved_at + 5 hari',

    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (uid_kategori) REFERENCES kategori(uid) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CHAT ───────────────────────────────────────────────────
CREATE TABLE chats (
    uid          CHAR(36) PRIMARY KEY,
    uid_ticket   CHAR(36) NOT NULL,
    sender       ENUM('user','admin') NOT NULL,
    pesan        TEXT NOT NULL,
    is_system    TINYINT(1) DEFAULT 0 COMMENT '1 = pesan otomatis sistem',
    dibaca       TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (uid_ticket) REFERENCES tickets(uid) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── NOTIFIKASI ADMIN ───────────────────────────────────────
CREATE TABLE notifikasi (
    uid          CHAR(36) PRIMARY KEY,
    uid_admin    CHAR(36) NOT NULL,
    uid_ticket   CHAR(36) NOT NULL,
    judul        VARCHAR(150) NOT NULL,
    pesan        TEXT NOT NULL,
    is_read      TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (uid_admin)  REFERENCES admins(uid)   ON DELETE CASCADE,
    FOREIGN KEY (uid_ticket) REFERENCES tickets(uid)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admins (password MD5)
-- password: admin123 → MD5 = 0192023a7bbd73250516f069df18b500
INSERT INTO admins (uid, nama, email, password) VALUES
(UUID(), 'Administrator NSC',  'admin@nsc.ac.id',  MD5('admin123')),
(UUID(), 'Operator Helpdesk',  'operator@nsc.ac.id', MD5('operator123'));

-- Kategori
INSERT INTO kategori (uid, nama_kategori, deskripsi) VALUES
(UUID(), 'Hardware',        'Masalah perangkat keras: komputer, printer, monitor, keyboard, dll'),
(UUID(), 'Software',        'Masalah perangkat lunak: aplikasi, sistem operasi, lisensi, dll'),
(UUID(), 'Jaringan',        'Masalah konektivitas: internet, WiFi, LAN, VPN, dll'),
(UUID(), 'Sistem Akademik', 'Masalah SIAKAD, e-learning, portal mahasiswa, akun SSO, dll');
