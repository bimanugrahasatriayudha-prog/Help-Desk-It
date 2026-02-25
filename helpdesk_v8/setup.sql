-- ============================================================
-- HELPDESK IT v5 — Politeknik NSC Surabaya
-- UUID · MD5 · Chat · Auto-close · VARCHAR Kategori · Pengirim
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpdesk_v3;
USE helpdesk_v3;

-- ── ADMIN ──────────────────────────────────────────────────
CREATE TABLE admins (
    uid        CHAR(36)     PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(32)  NOT NULL COMMENT 'MD5',
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── KATEGORI (VARCHAR — admin input bebas via phpMyAdmin) ───
CREATE TABLE kategori (
    uid           CHAR(36)     PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi     TEXT
) ENGINE=InnoDB;

-- ── JENIS PENGIRIM (VARCHAR — admin input bebas) ───────────
CREATE TABLE jenis_pengirim (
    uid        CHAR(36)     PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    keterangan TEXT
) ENGINE=InnoDB;

-- ── TICKETS ────────────────────────────────────────────────
CREATE TABLE tickets (
    uid          CHAR(36) PRIMARY KEY,
    kode_ticket  VARCHAR(20)  NOT NULL UNIQUE,
    chat_token   CHAR(36)     NOT NULL UNIQUE,

    nama_pelapor       VARCHAR(100) NOT NULL,
    email              VARCHAR(100) NOT NULL,
    no_telpon          VARCHAR(20)  NOT NULL,
    uid_jenis_pengirim CHAR(36)     NULL,

    uid_kategori CHAR(36)     NOT NULL,
    judul        VARCHAR(150) NOT NULL,
    deskripsi    TEXT         NOT NULL,
    foto         VARCHAR(255) NULL,

    prioritas ENUM('Low','Medium','High')                                   DEFAULT 'Medium',
    status    ENUM('Open','On Progress','Solved','Waiting Confirm','Closed') DEFAULT 'Open',

    solved_at     TIMESTAMP NULL,
    confirmed_at  TIMESTAMP NULL,
    auto_close_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (uid_kategori)       REFERENCES kategori(uid)       ON DELETE CASCADE,
    FOREIGN KEY (uid_jenis_pengirim) REFERENCES jenis_pengirim(uid) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── CHAT ───────────────────────────────────────────────────
CREATE TABLE chats (
    uid        CHAR(36)             PRIMARY KEY,
    uid_ticket CHAR(36)             NOT NULL,
    sender     ENUM('user','admin') NOT NULL,
    pesan      TEXT                 NOT NULL,
    is_system  TINYINT(1)           DEFAULT 0,
    dibaca     TINYINT(1)           DEFAULT 0,
    created_at TIMESTAMP            DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uid_ticket) REFERENCES tickets(uid) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── NOTIFIKASI ─────────────────────────────────────────────
CREATE TABLE notifikasi (
    uid        CHAR(36)     PRIMARY KEY,
    uid_admin  CHAR(36)     NOT NULL,
    uid_ticket CHAR(36)     NOT NULL,
    judul      VARCHAR(150) NOT NULL,
    pesan      TEXT         NOT NULL,
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uid_admin)  REFERENCES admins(uid)  ON DELETE CASCADE,
    FOREIGN KEY (uid_ticket) REFERENCES tickets(uid) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SEED DATA ──────────────────────────────────────────────

INSERT INTO admins (uid, nama, email, password) VALUES
(UUID(), 'Administrator NSC', 'admin@nsc.ac.id',    MD5('admin123')),
(UUID(), 'Operator Helpdesk', 'operator@nsc.ac.id', MD5('operator123'));

INSERT INTO kategori (uid, nama_kategori, deskripsi) VALUES
(UUID(), 'Hardware',        'Masalah perangkat keras: komputer, printer, monitor, dll'),
(UUID(), 'Software',        'Masalah perangkat lunak: aplikasi, OS, lisensi, dll'),
(UUID(), 'Jaringan',        'Masalah konektivitas: internet, WiFi, LAN, VPN, dll'),
(UUID(), 'Sistem Akademik', 'Masalah SIAKAD, e-learning, portal mahasiswa, akun SSO, dll');

INSERT INTO jenis_pengirim (uid, nama, keterangan) VALUES
(UUID(), 'Mahasiswa',           'Mahasiswa aktif Politeknik NSC Surabaya'),
(UUID(), 'Dosen',               'Tenaga pengajar Politeknik NSC Surabaya'),
(UUID(), 'Tenaga Kependidikan', 'Staf dan pegawai non-pengajar'),
(UUID(), 'Tamu / Umum',         'Tamu atau pengguna umum');
