<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/email.php';
requireAdmin();
autoCloseTickets($conn);

$admin = getAdmin();
$uid   = trim($_GET['uid'] ?? '');
if (!$uid) { header('Location: tickets'); exit(); }

$uid_esc = $conn->real_escape_string($uid);
$tQ = $conn->query("SELECT t.*, k.nama_kategori, jp.nama AS jenis_pengirim FROM tickets t LEFT JOIN kategori k ON t.uid_kategori=k.uid LEFT JOIN jenis_pengirim jp ON t.uid_jenis_pengirim=jp.uid WHERE t.uid='$uid_esc' LIMIT 1");
$ticket = $tQ->fetch_assoc();
if (!$ticket) { header('Location: tickets'); exit(); }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $auid   = $conn->real_escape_string($admin['uid']);

    if ($action === 'send_chat') {
        $pesan = trim($_POST['pesan'] ?? '');
        $foto  = null;
        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $fname = generateUUID() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../assets/uploads/' . $fname)) {
                    $foto = $fname;
                }
            }
        }
        if ($pesan || $foto) {
            $cuid = generateUUID();
            $finalPesan = $pesan ?: '';
            if ($foto) $finalPesan .= ($finalPesan ? "\n" : '') . "[img:{$foto}]";
            $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan) VALUES (?,?,'admin',?)");
            $stmt->bind_param('sss', $cuid, $uid_esc, $finalPesan);
            $stmt->execute();

            // ── Kirim notifikasi email ke user (hanya untuk pesan non-system) ──
            // Cegah duplikasi: cek apakah pesan admin terakhir sudah dikirim notif
            // Cek flag di session untuk rate-limit (max 1 email per tiket per 5 menit)
            $throttleKey = 'email_notif_' . $uid_esc;
            if (!isset($_SESSION[$throttleKey]) || (time() - $_SESSION[$throttleKey]) > 300) {
                $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/');
                sendReplyNotifEmail(
                    $ticket['email'],
                    $ticket['nama_pelapor'],
                    $ticket['kode_ticket'],
                    $ticket['judul'],
                    $ticket['chat_token'],
                    $baseUrl,
                    $pesan
                );
                $_SESSION[$throttleKey] = time();
            }
        }
    }

    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? $ticket['status'];
        $extra = '';

        // Saat admin set Solved → kirim konfirmasi ke user (dulu fitur Waiting Confirm)
        if ($newStatus === 'Solved') {
            $extra = ", solved_at=NOW(), auto_close_at=DATE_ADD(NOW(), INTERVAL 5 DAY)";
            $cuid  = generateUUID();
            $pesan = "✅ Tim IT telah menyelesaikan masalah Anda.\n\nMohon konfirmasi apakah masalah sudah benar-benar terselesaikan dengan menekan tombol **\"Ya, Masalah Sudah Selesai\"** di bawah.\n\nJika tidak ada konfirmasi dalam 5 hari, tiket akan otomatis ditutup. Terima kasih! 🙏";
            $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system) VALUES (?,?,'admin',?,1)");
            $stmt->bind_param('sss', $cuid, $uid_esc, $pesan);
            $stmt->execute();
        } elseif ($newStatus !== 'Solved') {
            // Notif sistem untuk status lain (bukan Solved)
            $cuid  = generateUUID();
            $pesan = "Status tiket diperbarui menjadi: $newStatus";
            $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system) VALUES (?,?,'admin',?,1)");
            $stmt->bind_param('sss', $cuid, $uid_esc, $pesan);
            $stmt->execute();
        }

        $conn->query("UPDATE tickets SET status='$newStatus'$extra, updated_at=NOW() WHERE uid='$uid_esc'");
    }

    if ($action === 'update_prioritas') {
        $p = $_POST['prioritas'] ?? $ticket['prioritas'];
        $conn->query("UPDATE tickets SET prioritas='$p', updated_at=NOW() WHERE uid='$uid_esc'");
    }

    header("Location: ticket_detail?uid=" . urlencode($uid) . "&updated=1");
    exit();
}

// Reload ticket
$tQ2 = $conn->query("SELECT t.*, k.nama_kategori, jp.nama AS jenis_pengirim FROM tickets t LEFT JOIN kategori k ON t.uid_kategori=k.uid LEFT JOIN jenis_pengirim jp ON t.uid_jenis_pengirim=jp.uid WHERE t.uid='$uid_esc' LIMIT 1");
$ticket = $tQ2->fetch_assoc();

// Mark admin notifs as read
$auid_esc = $conn->real_escape_string($admin['uid']);
$conn->query("UPDATE notifikasi SET is_read=1 WHERE uid_ticket='$uid_esc' AND uid_admin='$auid_esc'");

// Chats
$chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$uid_esc' ORDER BY created_at ASC");
$conn->query("UPDATE chats SET dibaca=1 WHERE uid_ticket='$uid_esc' AND sender='user' AND dibaca=0");

// UID pesan terakhir — anchor untuk realtime polling
$lastAdminRow     = $conn->query("SELECT uid FROM chats WHERE uid_ticket='$uid_esc' ORDER BY created_at DESC LIMIT 1");
$adminLastChatUid = ($lastAdminRow && $r = $lastAdminRow->fetch_assoc()) ? $r['uid'] : '';

$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$chatLink = $proto . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/chat.php?token=' . $ticket['chat_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tiket <?= htmlspecialchars($ticket['kode_ticket']) ?> — Admin NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ── Lock viewport: tidak ada scroll di level halaman ── */
html, body {
    height: 100%;
    overflow: hidden;
    margin: 0; padding: 0;
}

/* ── Layout utama ── */
.layout {
    height: 100%;
    overflow: hidden;
    display: flex;
}
.main-content {
    flex: 1 1 0;
    min-width: 0;
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── Page content: mengisi sisa tinggi di bawah topbar ── */
.page-content {
    flex: 1 1 0;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 16px;
    box-sizing: border-box;
}

/* ── Grid 2 kolom: kiri (info+update) | kanan (chat) ── */
.detail-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 18px;
    flex: 1 1 0;
    min-height: 0;
    align-items: stretch;
}

/* ══ KOLOM KIRI ══
   Panel kiri scroll sendiri jika konten melebihi tinggi layar */
.info-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
    /* Kunci: panel kiri bisa scroll secara keseluruhan */
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

/* Scrollbar tipis panel kiri */
.info-panel::-webkit-scrollbar { width: 5px; }
.info-panel::-webkit-scrollbar-track { background: transparent; }
.info-panel::-webkit-scrollbar-thumb { background: #c5d0e0; border-radius: 4px; }
.info-panel::-webkit-scrollbar-thumb:hover { background: var(--primary); }

/* Card Info Tiket — tinggi fleksibel, tidak scroll sendiri karena panel kiri sudah scroll */
.info-tiket-card {
    flex-shrink: 0; /* biarkan tinggi natural */
}
.info-tiket-card .card-body-scroll {
    padding: 14px;
}

/* Card Update — tinggi natural, tidak tumbuh */
.update-card {
    flex-shrink: 0;
}
.update-card .card-body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.update-card .form-group { margin-bottom: 8px; }

/* ══ KOLOM KANAN: Chat Panel ══ */
.chat-panel {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    background: #fff;
    min-height: 0; /* wajib agar flex child bisa menyusut */
}
.chat-panel-header {
    background: var(--primary-dark);
    color: #fff;
    padding: 12px 16px;
    font-size: .85rem;
    font-weight: 600;
    flex-shrink: 0;
}
.chat-panel-body {
    flex: 1 1 0;
    min-height: 0; /* kunci scroll internal */
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f7f9fc;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}
/* Scrollbar tipis chat body */
.chat-panel-body::-webkit-scrollbar { width: 5px; }
.chat-panel-body::-webkit-scrollbar-track { background: #f0f4f8; border-radius: 4px; }
.chat-panel-body::-webkit-scrollbar-thumb { background: #c5d0e0; border-radius: 4px; }
.chat-panel-body::-webkit-scrollbar-thumb:hover { background: var(--primary); }

.chat-panel-footer {
    padding: 12px;
    border-top: 1px solid var(--border);
    background: #fff;
    flex-shrink: 0;
}

/* Foto pendukung */
.foto-pendukung {
    display: block;
    max-width: 100%;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid var(--border);
    margin-top: 6px;
    transition: opacity .2s;
}
.foto-pendukung:hover { opacity: .85; }

/* ── Responsive 1366x768 ── */
@media (max-width: 1400px) {
    .detail-grid { grid-template-columns: 300px 1fr; }
}
@media (max-width: 1100px) {
    .detail-grid { grid-template-columns: 280px 1fr; }
}
/* ── Tablet / mobile ── */
@media (max-width: 860px) {
    html, body { overflow: auto; }
    .layout, .main-content, .page-content { height: auto; overflow: visible; }
    .detail-grid {
        grid-template-columns: 1fr;
        flex: unset;
    }
    .info-panel { overflow: visible; }
    .chat-panel { height: 70vh; min-height: 350px; }
}
</style>
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:4px">
                <button class="hamburger" onclick="openSidebar()">☰</button>
                <div>
                    <div class="topbar-title">Detail Tiket — <?= htmlspecialchars($ticket['kode_ticket']) ?></div>
                    <div class="topbar-subtitle"><?= htmlspecialchars($ticket['judul']) ?></div>
                </div>
            </div>
            <div class="topbar-right">
                <a href="javascript:navigator.clipboard.writeText('<?= htmlspecialchars($chatLink, ENT_QUOTES) ?>').then(()=>alert('Link disalin!'))" class="btn btn-sm btn-outline">📋 Salin Link User</a>
                <a href="tickets" class="btn btn-sm btn-outline">← Kembali</a>
            </div>
        </header>

        <main class="page-content">
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success" style="margin-bottom:12px;flex-shrink:0">✅ Berhasil diperbarui.</div>
            <?php endif; ?>

            <div class="detail-grid">

                <!-- ══════════ KOLOM KIRI: Info + Update ══════════ -->
                <div class="info-panel">

                    <!-- Info Tiket -->
                    <div class="card info-tiket-card">
                        <div class="card-header">
                            <h3>📋 Info Tiket</h3>
                            <?= badgeStatus($ticket['status']) ?>
                        </div>
                        <div class="card-body-scroll">
                            <table style="font-size:.8rem;width:100%">
                                <tr><td style="color:var(--text-muted);padding:4px 0;width:110px">Kode</td>
                                    <td><span class="ticket-code"><?= htmlspecialchars($ticket['kode_ticket']) ?></span></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Kategori</td>
                                    <td><?= htmlspecialchars($ticket['nama_kategori'] ?? '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Prioritas</td>
                                    <td><?= badgePrioritas($ticket['prioritas']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Pelapor</td>
                                    <td><strong><?= htmlspecialchars($ticket['nama_pelapor']) ?></strong></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Email</td>
                                    <td><?= htmlspecialchars($ticket['email']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">No. Telp</td>
                                    <td><?= htmlspecialchars($ticket['no_telpon']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Jenis</td>
                                    <td><?= htmlspecialchars($ticket['jenis_pengirim'] ?? '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Masuk</td>
                                    <td><?= date('d M Y H:i', strtotime($ticket['created_at'])) ?></td></tr>
                                <?php if ($ticket['auto_close_at']): ?>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Auto-close</td>
                                    <td style="color:var(--warning)"><?= date('d M Y H:i', strtotime($ticket['auto_close_at'])) ?></td></tr>
                                <?php endif; ?>
                            </table>

                            <hr class="divider">
                            <p style="font-size:.8rem;line-height:1.7;margin:0"><?= nl2br(htmlspecialchars($ticket['deskripsi'])) ?></p>

                            <?php if (!empty($ticket['foto'])): ?>
                            <div style="margin-top:12px">
                                <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px;font-weight:600">📷 Foto Pendukung dari Pelapor:</p>
                                <img
                                    src="../assets/uploads/<?= htmlspecialchars($ticket['foto']) ?>"
                                    class="foto-pendukung"
                                    alt="Foto Pendukung"
                                    onclick="window.open(this.src,'_blank')"
                                    onerror="this.parentElement.innerHTML='<p style=\'font-size:.75rem;color:#e53e3e\'>⚠️ Foto tidak dapat ditampilkan. Pastikan folder uploads dapat diakses.</p>'"
                                >
                            </div>
                            <?php else: ?>
                            <div style="margin-top:10px;padding:7px 10px;background:#f7f9fc;border-radius:6px;border:1px dashed var(--border)">
                                <p style="font-size:.75rem;color:var(--text-muted);margin:0">📷 Tidak ada foto pendukung dari pelapor.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Update Status & Prioritas — tampil selama belum Closed -->
                    <?php if ($ticket['status'] !== 'Closed'): ?>
                    <div class="card update-card">
                        <div class="card-header"><h3>✏️ Update</h3></div>
                        <div class="card-body">

                            <!-- Update Status -->
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="form-group">
                                    <label class="form-label" style="font-size:.76rem">Status Tiket</label>
                                    <select name="status" class="form-control" style="font-size:.8rem">
                                        <?php
                                        // Hapus 'Waiting Confirm' dari pilihan admin — sekarang otomatis saat pilih Solved
                                        foreach (['Open','On Progress','Solved','Closed'] as $s):
                                        ?>
                                        <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm btn-full">💾 Update Status</button>
                            </form>


                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <!-- ════════════════════════════════════════════════ -->

                <!-- ══════════ KOLOM KANAN: Chat Panel ══════════ -->
                <div class="chat-panel">
                    <div class="chat-panel-header">
                        💬 Chat dengan Pelapor — <?= htmlspecialchars($ticket['nama_pelapor']) ?>
                        <?php if ($ticket['status'] === 'Closed'): ?>
                        <span style="float:right;font-size:.7rem;opacity:.7">🔒 Tiket Ditutup</span>
                        <?php endif; ?>
                    </div>

                    <div class="chat-panel-body" id="chatBody">
                    <?php while ($chat = $chats->fetch_assoc()):
                        $isSystem = $chat['is_system'];
                        $sender   = $chat['sender'];
                        $rawPesan = $chat['pesan'];
                        $imgFile  = null;
                        if (preg_match('/\[img:([^\]]+)\]/', $rawPesan, $m)) {
                            $imgFile  = $m[1];
                            $rawPesan = trim(str_replace($m[0], '', $rawPesan));
                        }
                        $rawPesan = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($rawPesan));
                        $rawPesan = nl2br($rawPesan);
                    ?>
                        <?php if ($isSystem): ?>
                        <div style="display:flex;justify-content:center">
                            <div class="chat-bubble system" style="max-width:90%;font-size:.76rem">
                                <?= $rawPesan ?>
                                <div class="chat-time"><?= date('d M H:i', strtotime($chat['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php elseif ($sender === 'admin'): ?>
                        <div class="chat-bubble-wrap admin">
                            <div class="chat-avatar admin-av">IT</div>
                            <div>
                                <div class="chat-bubble admin" style="font-size:.8rem">
                                    <?php if ($imgFile): ?>
                                    <img src="../assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src,'_blank')">
                                    <?php endif; ?>
                                    <?= $rawPesan ?>
                                    <div class="chat-time"><?= date('d M H:i', strtotime($chat['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="chat-bubble-wrap user">
                            <div>
                                <div class="chat-bubble user" style="font-size:.8rem">
                                    <?php if ($imgFile): ?>
                                    <img src="../assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src,'_blank')">
                                    <?php endif; ?>
                                    <?= $rawPesan ?>
                                    <div class="chat-time"><?= date('d M H:i', strtotime($chat['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="chat-avatar user-av"><?= strtoupper(substr($ticket['nama_pelapor'],0,1)) ?></div>
                        </div>
                        <?php endif; ?>
                    <?php endwhile; ?>
                    </div>

                    <?php if ($ticket['status'] !== 'Closed'): ?>
                    <div class="chat-panel-footer">
                        <form method="POST" enctype="multipart/form-data" id="adminChatForm">
                            <input type="hidden" name="action" value="send_chat">
                            <div id="adminImgPreviewWrap" style="display:none;margin-bottom:6px">
                                <img id="adminImgPreview" style="max-height:60px;border-radius:6px;border:1px solid var(--border)" alt="">
                                <button type="button" onclick="clearAdminImg()" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:.8rem;margin-left:6px">✕ Hapus</button>
                            </div>
                            <div style="display:flex;gap:8px;align-items:flex-end">
                                <label for="adminFoto" style="cursor:pointer;font-size:1.2rem;color:var(--text-muted)" title="Upload gambar">📷</label>
                                <input type="file" id="adminFoto" name="foto" accept="image/*" style="display:none" onchange="previewAdminImg(this)">
                                <textarea name="pesan" placeholder="Ketik pesan..." rows="2" class="form-control" style="flex:1;font-size:.8rem;resize:none"></textarea>
                                <button type="submit" class="btn btn-primary btn-sm">Kirim</button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <div style="padding:12px;text-align:center;font-size:.78rem;color:var(--text-muted);background:#f7f9fc">
                        🔒 Tiket ini sudah ditutup
                    </div>
                    <?php endif; ?>
                </div>
                <!-- ════════════════════════════════════════════════ -->

            </div>
        </main>
    </div>
</div>

<script>
// Scroll chat ke bawah
const cb = document.getElementById('chatBody');
if (cb) cb.scrollTop = cb.scrollHeight;

const ADMIN_DRAFT_KEY = 'hd_admin_draft_<?= addslashes($uid) ?>';

// Draft textarea
const adminTA = document.querySelector('#adminChatForm textarea[name="pesan"]');
if (adminTA) {
    const saved = sessionStorage.getItem(ADMIN_DRAFT_KEY);
    if (saved) adminTA.value = saved;

    adminTA.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        sessionStorage.setItem(ADMIN_DRAFT_KEY, this.value);
    });
    adminTA.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sessionStorage.removeItem(ADMIN_DRAFT_KEY);
            document.getElementById('adminChatForm').submit();
        }
    });
}
const adminForm = document.getElementById('adminChatForm');
if (adminForm) adminForm.addEventListener('submit', () => sessionStorage.removeItem(ADMIN_DRAFT_KEY));

// Preview foto yang akan dikirim admin di chat
function previewAdminImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('adminImgPreview').src = e.target.result;
            document.getElementById('adminImgPreviewWrap').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function clearAdminImg() {
    document.getElementById('adminFoto').value = '';
    document.getElementById('adminImgPreviewWrap').style.display = 'none';
    document.getElementById('adminImgPreview').src = '';
}

// ════════════════════════════════════════════════════════════════
//  REALTIME POLLING ADMIN — inject pesan baru ke DOM tanpa reload
// ════════════════════════════════════════════════════════════════
let adminLastMsgUid = '<?= addslashes($adminLastChatUid ?? '') ?>';
const TICKET_UID    = '<?= addslashes($uid) ?>';

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderAdminBubble(msg) {
    let rawPesan = msg.pesan;
    let imgFile  = null;
    const imgMatch = rawPesan.match(/\[img:([^\]]+)\]/);
    if (imgMatch) {
        imgFile  = imgMatch[1];
        rawPesan = rawPesan.replace(imgMatch[0], '').trim();
    }

    let pesanHtml = escHtml(rawPesan)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');

    const dt   = new Date(msg.created_at.replace(' ', 'T'));
    const time = dt.toLocaleDateString('id-ID', {day:'2-digit',month:'short'}) + ' ' +
                 dt.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});

    const imgHtml = imgFile
        ? `<img src="../assets/uploads/${escHtml(imgFile)}" class="chat-img" onclick="window.open(this.src,'_blank')">`
        : '';

    let html = '';
    if (msg.is_system) {
        html = `<div style="display:flex;justify-content:center">
            <div class="chat-bubble system" style="max-width:90%;font-size:.76rem">
                ${pesanHtml}
                <div class="chat-time">${time}</div>
            </div>
        </div>`;
    } else if (msg.sender === 'admin') {
        html = `<div class="chat-bubble-wrap admin">
            <div class="chat-avatar admin-av">IT</div>
            <div>
                <div class="chat-bubble admin" style="font-size:.8rem">
                    ${imgHtml}${pesanHtml}
                    <div class="chat-time">${time}</div>
                </div>
            </div>
        </div>`;
    } else {
        const namaAwal = '<?= addslashes(strtoupper(substr($ticket['nama_pelapor'],0,1))) ?>';
        html = `<div class="chat-bubble-wrap user">
            <div>
                <div class="chat-bubble user" style="font-size:.8rem">
                    ${imgHtml}${pesanHtml}
                    <div class="chat-time">${time}</div>
                </div>
            </div>
            <div class="chat-avatar user-av">${namaAwal}</div>
        </div>`;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    return wrapper.firstElementChild;
}

function isAdminNearBottom() {
    const cb = document.getElementById('chatBody');
    if (!cb) return true;
    return cb.scrollHeight - cb.scrollTop - cb.clientHeight < 60;
}

function pollAdminChat() {
    const cb  = document.getElementById('chatBody');
    const url = '../api/chat_messages?uid=' + encodeURIComponent(TICKET_UID)
              + (adminLastMsgUid ? '&after=' + encodeURIComponent(adminLastMsgUid) : '');

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !cb) return;

            if (data.messages && data.messages.length > 0) {
                const wasAtBottom = isAdminNearBottom();
                data.messages.forEach(msg => {
                    const el = renderAdminBubble(msg);
                    if (el) cb.appendChild(el);
                    adminLastMsgUid = msg.uid;
                });
                if (wasAtBottom) cb.scrollTo({ top: cb.scrollHeight, behavior: 'smooth' });
                else {
                    // Badge notif
                    let badge = document.getElementById('adminNewMsg');
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.id = 'adminNewMsg';
                        badge.style.cssText = 'position:fixed;top:68px;right:20px;background:var(--danger);color:#fff;padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 3px 12px rgba(0,0,0,.2)';
                        badge.innerHTML = '🔔 Pesan baru — klik untuk lihat';
                        badge.onclick = () => {
                            cb.scrollTo({ top: cb.scrollHeight, behavior: 'smooth' });
                            badge.remove();
                        };
                        document.body.appendChild(badge);
                    }
                }
            }
        }).catch(() => {});
}

setInterval(pollAdminChat, 3000);

// Sembunyikan badge saat scroll ke bawah
const adminCb = document.getElementById('chatBody');
if (adminCb) {
    adminCb.addEventListener('scroll', () => {
        if (isAdminNearBottom()) {
            const badge = document.getElementById('adminNewMsg');
            if (badge) badge.remove();
        }
    });
}
</script>
</body>
</html>
