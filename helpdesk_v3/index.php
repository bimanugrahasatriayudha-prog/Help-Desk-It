<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

autoCloseTickets($conn);

$success = $error = '';
$newToken = '';

$cats = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $no_telpon = trim($_POST['no_telpon'] ?? '');
    $uid_kat   = trim($_POST['uid_kategori'] ?? '');
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $prioritas = $_POST['prioritas'] ?? 'Medium';

    if ($nama && $email && $no_telpon && $uid_kat && $judul && $deskripsi) {
        // Handle file upload
        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $ext   = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $fname = generateUUID() . '.' . $ext;
                $dest  = __DIR__ . '/assets/uploads/' . $fname;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                    $foto = $fname;
                }
            } else {
                $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
            }
        }

        if (!$error) {
            $uid        = generateUUID();
            $chat_token = generateUUID();
            $kode       = generateKode();

            $stmt = $conn->prepare("INSERT INTO tickets
                (uid, kode_ticket, chat_token, nama_pelapor, email, no_telpon, uid_kategori, judul, deskripsi, foto, prioritas)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssssssss',
                $uid, $kode, $chat_token,
                $nama, $email, $no_telpon,
                $uid_kat, $judul, $deskripsi, $foto, $prioritas
            );

            if ($stmt->execute()) {
                // Chat default dari sistem
                $cuid = generateUUID();
                $pesan = "Halo {$nama}! 👋\n\nLaporan Anda telah kami terima dengan kode tiket **{$kode}**.\n\nMohon tunggu, tim IT kami sedang memproses laporan Anda. Kami akan segera menghubungi Anda melalui halaman ini.\n\nTerima kasih telah menggunakan Helpdesk IT Politeknik NSC Surabaya. 🙏";
                $stmtC = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system) VALUES (?,?,'admin',?,1)");
                $stmtC->bind_param('sss', $cuid, $uid, $pesan);
                $stmtC->execute();

                // Notifikasi ke semua admin
                $admins = $conn->query("SELECT uid FROM admins");
                while ($adm = $admins->fetch_assoc()) {
                    $nuid  = generateUUID();
                    $njudul = "Tiket Baru: $judul";
                    $npesan = "Tiket $kode dari $nama ($email) telah masuk.";
                    $stmtN = $conn->prepare("INSERT INTO notifikasi (uid, uid_admin, uid_ticket, judul, pesan) VALUES (?,?,?,?,?)");
                    $stmtN->bind_param('sssss', $nuid, $adm['uid'], $uid, $njudul, $npesan);
                    $stmtN->execute();
                }

                $newToken = $chat_token;
            } else {
                $error = "Gagal membuat tiket. Silakan coba lagi.";
            }
        }
    } else {
        $error = "Harap isi semua field yang wajib diisi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Helpdesk IT — Politeknik NSC Surabaya</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if ($newToken): ?>
<!-- ── SUCCESS PAGE ── -->
<div class="success-wrapper">
    <div class="success-card">
        <div class="success-icon">✅</div>
        <h2>Tiket Berhasil Dibuat!</h2>
        <p>Laporan Anda telah diterima oleh tim Helpdesk IT NSC.<br>Simpan link berikut untuk memantau & chat dengan admin:</p>

        <div class="link-box" id="chatLink">
            <?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/chat.php?token=' . $newToken ?>
        </div>

        <button onclick="copyLink()" class="btn btn-primary btn-full" style="margin-bottom:10px">
            📋 Salin Link Chat
        </button>
        <a href="chat.php?token=<?= $newToken ?>" class="btn btn-accent btn-full">
            💬 Buka Chat Sekarang
        </a>

        <p style="margin-top:18px;font-size:.75rem;color:var(--text-muted)">
            ⚠️ Simpan link ini baik-baik. Link ini adalah satu-satunya cara untuk mengakses chat tiket Anda.
        </p>
    </div>
</div>
<script>
function copyLink() {
    navigator.clipboard.writeText(document.getElementById('chatLink').innerText.trim());
    alert('Link berhasil disalin!');
}
</script>

<?php else: ?>
<!-- ── FORM BUAT TIKET ── -->
<div class="public-wrapper">
    <div class="public-header">
        <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj" alt="NSC">
        <h1>Politeknik NSC Surabaya</h1>
        <p>Sistem Helpdesk IT Terpadu — Laporkan kendala IT Anda</p>
    </div>

    <div class="public-card">
        <div class="public-card-header">
            <span>📝</span>
            <h2>Form Pengajuan Laporan IT</h2>
        </div>
        <div class="public-card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Data Pelapor -->
                <div style="background:#f7f9fc;border-radius:8px;padding:14px;margin-bottom:16px">
                    <div style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">👤 Data Pelapor</div>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="nama" class="form-control" placeholder="Nama lengkap Anda" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Email <span class="req">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="email@nsc.ac.id" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Telepon <span class="req">*</span></label>
                            <input type="tel" name="no_telpon" class="form-control" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($_POST['no_telpon'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Detail Laporan -->
                <div style="background:#f7f9fc;border-radius:8px;padding:14px;margin-bottom:16px">
                    <div style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">🔧 Detail Laporan</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Kategori <span class="req">*</span></label>
                            <select name="uid_kategori" class="form-control" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php while ($cat = $cats->fetch_assoc()): ?>
                                <option value="<?= $cat['uid'] ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prioritas</label>
                            <select name="prioritas" class="form-control">
                                <option value="Low">🟢 Low</option>
                                <option value="Medium" selected>🟡 Medium</option>
                                <option value="High">🔴 High</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Judul Masalah <span class="req">*</span></label>
                        <input type="text" name="judul" class="form-control" placeholder="Contoh: Printer Lab A tidak bisa mencetak" value="<?= htmlspecialchars($_POST['judul'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deskripsi Masalah <span class="req">*</span></label>
                        <textarea name="deskripsi" class="form-control" rows="4" placeholder="Jelaskan masalah secara detail..." required><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Upload -->
                <div class="form-group">
                    <label class="form-label">Screenshot / Foto Pendukung</label>
                    <div class="file-upload" onclick="document.getElementById('fotoInput').click()">
                        <input type="file" id="fotoInput" name="foto" accept="image/*" onchange="previewFile(this)">
                        <div class="upload-icon">📷</div>
                        <p>Klik untuk upload gambar<br><small>JPG, PNG, GIF — Maks. 5MB</small></p>
                    </div>
                    <img id="fotoPreview" class="file-preview" alt="Preview">
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="padding:11px;font-size:.9rem">
                    🚀 Kirim Laporan
                </button>
            </form>
        </div>
    </div>

    <p style="color:rgba(255,255,255,.5);font-size:.72rem;margin-top:16px">
        © <?= date('Y') ?> Politeknik NSC Surabaya · Helpdesk IT
    </p>
</div>

<script>
function previewFile(input) {
    const preview = document.getElementById('fotoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php endif; ?>
</body>
</html>
