-- ============================================================
-- HELPDESK IT — Politeknik NSC Surabaya
-- Database v2 — Kategori ENUM
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpdesk_it;
USE helpdesk_it;

-- USERS
CREATE TABLE users (
    id_user    INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('user','admin','teknisi') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- KATEGORI
CREATE TABLE kategori (
    id_kategori   INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori ENUM('Hardware','Software','Jaringan','Sistem Akademik'),
    deskripsi     TEXT
) ENGINE=InnoDB;

-- TICKETS
CREATE TABLE tickets (
    id_ticket   INT AUTO_INCREMENT PRIMARY KEY,
    kode_ticket VARCHAR(20) NOT NULL UNIQUE,
    id_user     INT NOT NULL,
    id_kategori INT NOT NULL,
    id_teknisi  INT NULL,
    judul       VARCHAR(150) NOT NULL,
    deskripsi   TEXT NOT NULL,
    lokasi      VARCHAR(100),
    prioritas   ENUM('Low','Medium','High') DEFAULT 'Medium',
    status      ENUM('Open','On Progress','Solved','Closed','Re-Open') DEFAULT 'Open',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user)     REFERENCES users(id_user)     ON DELETE CASCADE,
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE CASCADE,
    FOREIGN KEY (id_teknisi)  REFERENCES users(id_user)     ON DELETE SET NULL
) ENGINE=InnoDB;

-- LOG
CREATE TABLE ticket_logs (
    id_log     INT AUTO_INCREMENT PRIMARY KEY,
    id_ticket  INT NOT NULL,
    id_user    INT NOT NULL,
    keterangan TEXT NOT NULL,
    status     VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket) ON DELETE CASCADE,
    FOREIGN KEY (id_user)   REFERENCES users(id_user)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- password disimpan plain text, sesuai database kamu
-- ============================================================

-- Users
INSERT INTO users (nama, email, password, role) VALUES
('Administrator',  'admin@gmail.com',        'admin123',  'admin'),
('Budi Santoso',   'budi@gmail.com',          'teknisi123','teknisi'),
('Siti Rahayu',    'siti@gmail.com',          'teknisi123','teknisi'),
('User Mahasiswa', 'user@gmail.com',           'user123',   'user'),
('Dewi Kusuma',    'dewi@student.nsc.id',     'user123',   'user');

-- Kategori (isi semua nilai ENUM)
INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Hardware',         'Masalah perangkat keras: komputer, printer, monitor, keyboard, dll'),
('Software',         'Masalah perangkat lunak: aplikasi, sistem operasi, lisensi, dll'),
('Jaringan',         'Masalah konektivitas: internet, WiFi, LAN, VPN, dll'),
('Sistem Akademik',  'Masalah SIAKAD, e-learning, portal mahasiswa, akun SSO, dll');

-- Tickets
INSERT INTO tickets (kode_ticket, id_user, id_kategori, id_teknisi, judul, deskripsi, lokasi, prioritas, status) VALUES
('TKT-A1B2C3D4', 4, 1, 2, 'Printer Lab A tidak bisa mencetak',
 'Printer HP di Lab Komputer A lantai 2 tidak merespon saat dikirim perintah cetak dari semua komputer.',
 'Lab Komputer A, Lantai 2', 'High', 'On Progress'),

('TKT-E5F6G7H8', 5, 2, NULL, 'Microsoft Office error saat dibuka',
 'Aplikasi MS Office 2019 menampilkan pesan error setiap kali dibuka di komputer ruang dosen.',
 'Ruang Dosen, Gedung B', 'Medium', 'Open'),

('TKT-I9J0K1L2', 4, 3, 3, 'WiFi tidak bisa konek di lantai 3',
 'WiFi kampus tidak terdeteksi di area lantai 3 Gedung C sejak 2 hari lalu.',
 'Gedung C, Lantai 3', 'High', 'Open'),

('TKT-M3N4O5P6', 5, 4, 2, 'Tidak bisa login SIAKAD',
 'Akun SIAKAD mahasiswa tidak bisa login sejak kemarin, muncul pesan "akun tidak ditemukan".',
 'Online', 'Medium', 'Solved'),

('TKT-Q7R8S9T0', 4, 1, NULL, 'Proyektor Ruang 301 mati',
 'Proyektor di ruang kuliah 301 tidak menyala sama sekali, tombol power tidak merespon.',
 'Ruang Kuliah 301', 'Medium', 'Open'),

('TKT-U1V2W3X4', 5, 2, 3, 'Komputer blue screen saat booting',
 'Komputer nomor 7 di Lab Multimedia selalu blue screen setiap kali dinyalakan.',
 'Lab Multimedia, Lantai 1', 'High', 'Closed');

-- Logs
INSERT INTO ticket_logs (id_ticket, id_user, keterangan, status) VALUES
(1, 4, 'Tiket dibuat — printer tidak merespon sama sekali', 'Open'),
(1, 1, 'Tiket diterima, ditugaskan ke Budi Santoso', 'On Progress'),
(1, 2, 'Sudah datang ke lokasi, sedang memeriksa koneksi USB printer', 'On Progress'),

(2, 5, 'Tiket dibuat — MS Office error saat dibuka', 'Open'),

(3, 4, 'Tiket dibuat — WiFi lantai 3 tidak terdeteksi', 'Open'),
(3, 1, 'Ditugaskan ke Siti Rahayu untuk pengecekan access point', 'On Progress'),

(4, 5, 'Tiket dibuat — tidak bisa login SIAKAD', 'Open'),
(4, 2, 'Akun sudah direset oleh admin SIAKAD, user diminta coba kembali', 'Solved'),

(5, 4, 'Tiket dibuat — proyektor mati total', 'Open'),

(6, 5, 'Tiket dibuat — komputer blue screen saat booting', 'Open'),
(6, 3, 'Ditemukan driver VGA corrupt, sudah reinstall driver', 'Solved'),
(6, 1, 'Dikonfirmasi selesai oleh user, tiket ditutup', 'Closed');
