<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('user', '../index.php');

$user = getCurrentUser();
$uid  = $user['id'];

$success = $error = '';

// Load categories
$cats = $conn->query("SELECT * FROM kategori ORDER BY id_kategori");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul       = trim($_POST['judul'] ?? '');
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $lokasi      = trim($_POST['lokasi'] ?? '');
    $prioritas   = $_POST['prioritas'] ?? 'Medium';

    if ($judul && $deskripsi && $id_kategori) {
        $kode = generateKodeTicket();
        // urutan: kode_ticket(s), id_user(i), id_kategori(i), judul(s), deskripsi(s), lokasi(s), prioritas(s)
        $stmt = $conn->prepare("INSERT INTO tickets (kode_ticket, id_user, id_kategori, judul, deskripsi, lokasi, prioritas) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('siissss', $kode, $uid, $id_kategori, $judul, $deskripsi, $lokasi, $prioritas);
        if ($stmt->execute()) {
            $tid = $conn->insert_id;
            // Log
            $ket = "Tiket dibuat oleh {$user['nama']}";
            $logStmt = $conn->prepare("INSERT INTO ticket_logs (id_ticket,id_user,keterangan,status) VALUES (?,?,?,'Open')");
            $logStmt->bind_param('iis', $tid, $uid, $ket);
            $logStmt->execute();
            $success = "Tiket <strong>$kode</strong> berhasil dibuat!";
        } else {
            $error = "Gagal membuat tiket. Coba lagi.";
        }
    } else {
        $error = "Harap isi semua field yang wajib.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buat Tiket — Helpdesk IT NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">Buat Tiket Baru</div>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="btn btn-outline btn-sm">← Kembali</a>
            </div>
        </header>
        <main class="page-content">
            <div style="max-width:680px;margin:0 auto">
                <div class="card">
                    <div class="card-header">
                        <h3>📝 Form Pengajuan Tiket</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Judul Masalah <span class="required">*</span></label>
                                <input type="text" name="judul" class="form-control"
                                       placeholder="Contoh: Printer di Lab A tidak bisa mencetak"
                                       value="<?= htmlspecialchars($_POST['judul'] ?? '') ?>" required>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                                <div class="form-group">
                                    <label class="form-label">Kategori <span class="required">*</span></label>
                                    <select name="id_kategori" class="form-control" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php while ($cat = $cats->fetch_assoc()): ?>
                                        <option value="<?= $cat['id_kategori'] ?>"
                                            <?= (($_POST['id_kategori'] ?? '') == $cat['id_kategori'] ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($cat['nama_kategori']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prioritas</label>
                                    <select name="prioritas" class="form-control">
                                        <?php foreach (['Low','Medium','High'] as $p): ?>
                                        <option value="<?= $p ?>" <?= (($_POST['prioritas'] ?? 'Medium') === $p ? 'selected' : '') ?>><?= $p ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Lokasi</label>
                                <input type="text" name="lokasi" class="form-control"
                                       placeholder="Contoh: Lab Komputer A, Lantai 2"
                                       value="<?= htmlspecialchars($_POST['lokasi'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Deskripsi Masalah <span class="required">*</span></label>
                                <textarea name="deskripsi" class="form-control" rows="5"
                                          placeholder="Jelaskan masalah secara detail..." required><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
                                🚀 Kirim Tiket
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
