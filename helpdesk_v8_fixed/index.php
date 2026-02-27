<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/email.php';

autoCloseTickets($conn);

$success      = false;
$error        = '';
$newToken     = '';
$kodeTicket   = '';
$successEmail = '';
$successNama  = '';
$chatLinkOut  = '';
$fotoError    = '';
$emailSent    = false;

// Load dropdown data
$cats      = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");
$jenisList = $conn->query("SELECT * FROM jenis_pengirim ORDER BY nama");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = htmlspecialchars(strip_tags(trim($_POST['nama']      ?? '')), ENT_QUOTES, 'UTF-8');
    $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $no_telpon = preg_replace('/[^0-9+\-\s()]/', '', trim($_POST['no_telpon'] ?? ''));
    $uid_kat   = preg_replace('/[^a-f0-9\-]/', '', trim($_POST['uid_kategori'] ?? ''));
    $uid_jenis = preg_replace('/[^a-f0-9\-]/', '', trim($_POST['uid_jenis_pengirim'] ?? ''));
    $judul     = htmlspecialchars(strip_tags(trim($_POST['judul']      ?? '')), ENT_QUOTES, 'UTF-8');
    $deskripsi = htmlspecialchars(strip_tags(trim($_POST['deskripsi']  ?? '')), ENT_QUOTES, 'UTF-8');
    $prioritas = in_array($_POST['prioritas'] ?? '', ['Low','Medium','High']) ? $_POST['prioritas'] : 'Medium';

    if (!$nama || !$email || !$no_telpon || !$uid_kat || !$judul || !$deskripsi) {
        $error = "Harap isi semua field yang wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($nama) > 100 || strlen($judul) > 150) {
        $error = "Input melebihi batas karakter.";
    } else {
        // ── Upload foto ──────────────────────────────────────
        $foto      = null;
        $uploadDir = __DIR__ . '/assets/uploads/';

        if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];

            if (!in_array($ext, $allowed)) {
                $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
            } elseif ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
                $error = "Ukuran file terlalu besar. Maksimal 5MB.";
            } else {
                // Pastikan folder ada & writable
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                if (!is_writable($uploadDir)) {
                    chmod($uploadDir, 0775);
                }

                $fname = uniqid('foto_', true) . '.' . $ext;
                $dest  = $uploadDir . $fname;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                    $foto = $fname;
                } else {
                    // Catat error tapi jangan blokir submit
                    $fotoError = "⚠️ Foto gagal diupload (folder uploads tidak writable). Tiket tetap dibuat tanpa foto.";
                    error_log("Upload gagal: dest=$dest, uploadDir writable=" . (is_writable($uploadDir)?'yes':'no'));
                }
            }
        }

        if (!$error) {
            $uid           = generateUUID();
            $chat_token    = generateUUID();
            $kode          = generateKode();
            $uid_jenis_val = $uid_jenis ?: null;

            $stmt = $conn->prepare("INSERT INTO tickets
                (uid,kode_ticket,chat_token,nama_pelapor,email,no_telpon,uid_jenis_pengirim,uid_kategori,judul,deskripsi,foto,prioritas)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssssssssss',
                $uid, $kode, $chat_token, $nama, $email, $no_telpon,
                $uid_jenis_val, $uid_kat, $judul, $deskripsi, $foto, $prioritas
            );

            if ($stmt->execute()) {
                // Chat sistem default
                $cuid  = generateUUID();
                $pesan = "Halo {$nama}!\n\nLaporan Anda telah kami terima dengan kode tiket {$kode}.\n\nMohon tunggu, tim IT kami sedang memproses laporan Anda. Kami akan segera menghubungi Anda melalui halaman ini.\n\nTerima kasih telah menggunakan Helpdesk IT Politeknik NSC Surabaya.";
                $stmtC = $conn->prepare("INSERT INTO chats (uid,uid_ticket,sender,pesan,is_system) VALUES (?,?,'admin',?,1)");
                $stmtC->bind_param('sss', $cuid, $uid, $pesan);
                $stmtC->execute();

                // Notifikasi ke semua admin
                $admR = $conn->query("SELECT uid FROM admins");
                while ($adm = $admR->fetch_assoc()) {
                    $nuid   = generateUUID();
                    $njudul = "Tiket Baru: " . substr($judul, 0, 100);
                    $npesan = "Tiket $kode dari $nama ($email) telah masuk.";
                    $stmtN  = $conn->prepare("INSERT INTO notifikasi (uid,uid_admin,uid_ticket,judul,pesan) VALUES (?,?,?,?,?)");
                    $stmtN->bind_param('sssss', $nuid, $adm['uid'], $uid, $njudul, $npesan);
                    $stmtN->execute();
                }

                $proto        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl      = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $chatLinkOut  = $baseUrl . '/chat?token=' . $chat_token;

                // Kirim email konfirmasi via Brevo
                $emailResult  = sendTicketEmail($email, $nama, $kode, $judul, $chat_token, $baseUrl);
                $emailSent    = $emailResult['success'];

                $newToken     = $chat_token;
                $kodeTicket   = $kode;
                $successEmail = $email;
                $successNama  = $nama;
                $success      = true;
            } else {
                $error = "Gagal membuat tiket. Silakan coba lagi.";
            }
        }
    }
}

$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
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

<?php if ($success): ?>
<!-- ══════════ SUCCESS PAGE ══════════ -->
<div class="success-wrapper">
    <div class="success-card">
        <div class="success-icon">✅</div>
        <h2>Tiket Berhasil Dibuat!</h2>
        <p>Laporan diterima oleh tim Helpdesk IT NSC.<br>
           Kode tiket: <strong><?= htmlspecialchars($kodeTicket) ?></strong></p>

        <?php if ($fotoError): ?>
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;margin:10px 0;font-size:.8rem;color:#9a3412;text-align:left">
            <?= htmlspecialchars($fotoError) ?>
        </div>
        <?php endif; ?>

        <!-- Status email dari Brevo (server-side) -->
        <?php if ($emailSent): ?>
        <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:11px 16px;margin:16px 0;font-size:.82rem;color:#166534;text-align:left">
            📧 Link tiket berhasil dikirim ke <strong><?= htmlspecialchars($successEmail) ?></strong>
        </div>
        <?php else: ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:11px 16px;margin:16px 0;font-size:.82rem;color:#92400e;text-align:left">
            ⚠️ Email gagal terkirim. Salin link di bawah dan simpan baik-baik.
        </div>
        <?php endif; ?>

        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px">Link chat tiket Anda:</p>
        <div class="link-box" id="chatLink"><?= htmlspecialchars($chatLinkOut) ?></div>

        <button onclick="copyLink()" class="btn btn-primary btn-full" style="margin-bottom:10px">
            📋 Salin Link
        </button>
        <a href="chat?token=<?= urlencode($newToken) ?>" class="btn btn-accent btn-full">
            💬 Buka Chat Sekarang
        </a>

        <p style="margin-top:16px;font-size:.72rem;color:var(--text-muted)">
            ⚠️ Simpan link atau screenshot halaman ini. Tanpa link ini Anda tidak bisa mengakses tiket.
        </p>
    </div>
</div>
<script>
function copyLink() {
    navigator.clipboard.writeText(document.getElementById('chatLink').innerText.trim())
        .then(() => alert('Link berhasil disalin!'));
}
</script>

<?php else: ?>
<!-- ══════════ FORM PENGAJUAN ══════════ -->
<div class="public-wrapper">
    <div class="public-header">
        <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj" alt="NSC">
        <h1>Politeknik NSC Surabaya</h1>
        <p>Sistem Helpdesk IT Terpadu — Laporkan kendala IT Anda</p>
        <a href="admin/login" class="btn-login-admin">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><path d="M17 11l2 2 4-4" stroke-width="2"/></svg>
            Login Sebagai Admin
        </a>
    </div>
    <style>
    .btn-login-admin {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 14px;
        padding: 9px 22px;
        background: linear-gradient(135deg, #1e40af, #1d4ed8);
        color: #fff;
        font-size: .82rem;
        font-weight: 600;
        border-radius: 50px;
        text-decoration: none;
        box-shadow: 0 3px 12px rgba(29,78,216,.35);
        transition: all .2s ease;
        letter-spacing: .02em;
        border: 1.5px solid rgba(255,255,255,.15);
    }
    .btn-login-admin:hover {
        background: linear-gradient(135deg, #1e3a8a, #1e40af);
        box-shadow: 0 5px 18px rgba(29,78,216,.45);
        transform: translateY(-1px);
        color: #fff;
    }
    .btn-login-admin:active { transform: translateY(0); }
    </style>

    <div class="public-card">
        <div class="public-card-header">
            <span>📝</span>
            <h2>Form Pengajuan Laporan IT</h2>
        </div>
        <div class="public-card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="ticketForm">

                <div class="form-section">
                    <div class="form-section-title">👤 Data Pelapor</div>

                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="nama" class="form-control"
                               placeholder="Nama lengkap Anda" maxlength="100" required>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Email <span class="req">*</span></label>
                            <input type="email" name="email" id="emailInput" class="form-control"
                                   placeholder="email@gmail.com" maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Telepon <span class="req">*</span></label>
                            <input type="tel" name="no_telpon" class="form-control"
                                   placeholder="08xxxxxxxxxx" maxlength="20" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status / Jenis Pengirim <span class="req">*</span></label>
                        <select name="uid_jenis_pengirim" class="form-control" required>
                            <option value="">-- Pilih Status Anda --</option>
                            <?php if ($jenisList) { while ($j = $jenisList->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($j['uid']) ?>"><?= htmlspecialchars($j['nama']) ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">🔧 Detail Laporan</div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Kategori Masalah <span class="req">*</span></label>
                            <select name="uid_kategori" class="form-control" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php if ($cats) { while ($cat = $cats->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cat['uid']) ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prioritas</label>
                            <select name="prioritas" class="form-control">
                                <option value="Low">🟢 Low — Tidak mendesak</option>
                                <option value="Medium" selected>🟡 Medium — Cukup mendesak</option>
                                <option value="High">🔴 High — Sangat mendesak</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Judul Masalah <span class="req">*</span></label>
                        <input type="text" name="judul" class="form-control"
                               placeholder="Contoh: Printer Lab A tidak bisa mencetak"
                               maxlength="150" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi Masalah <span class="req">*</span></label>
                        <textarea name="deskripsi" class="form-control" rows="4"
                                  placeholder="Jelaskan masalah secara detail..." required></textarea>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px">
                    <label class="form-label">Screenshot / Foto Pendukung
                        <span style="color:var(--text-muted);font-weight:400">(opsional)</span>
                    </label>
                    <div class="file-upload" id="dropzone" onclick="document.getElementById('fotoInput').click()">
                        <input type="file" id="fotoInput" name="foto" accept="image/*" onchange="previewFile(this)">
                        <div class="upload-icon">📷</div>
                        <p id="uploadText">Klik untuk upload gambar<br><small>JPG, PNG, GIF, WEBP — Maks. 5MB</small></p>
                    </div>
                    <img id="fotoPreview" class="file-preview" alt="Preview">
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="padding:12px;font-size:.95rem" id="submitBtn">
                    🚀 Kirim Laporan
                </button>
            </form>
        </div>
    </div>

    <p style="color:rgba(255,255,255,.4);font-size:.7rem;margin-top:16px">
        © <?= date('Y') ?> Politeknik NSC Surabaya · Helpdesk IT
    </p>
</div>

<script>
function previewFile(input) {
    const preview = document.getElementById('fotoPreview');
    const text    = document.getElementById('uploadText');
    if (input.files && input.files[0]) {
        const f = input.files[0];
        if (f.size > 5 * 1024 * 1024) {
            alert('File terlalu besar! Maksimal 5MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            text.innerHTML = '✅ ' + f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
        };
        reader.readAsDataURL(f);
    }
}
document.getElementById('ticketForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Mengirim laporan...';
});
</script>
<?php endif; ?>
</body>
</html>
