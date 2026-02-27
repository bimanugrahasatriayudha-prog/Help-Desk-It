<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

autoCloseTickets($conn);

$token = preg_replace('/[^a-f0-9\-]/', '', trim($_GET['token'] ?? ''));
if (!$token) { header('Location: index'); exit(); }

$tQ = $conn->query("
    SELECT t.*, k.nama_kategori, jp.nama AS jenis_pengirim
    FROM tickets t
    LEFT JOIN kategori k ON t.uid_kategori = k.uid
    LEFT JOIN jenis_pengirim jp ON t.uid_jenis_pengirim = jp.uid
    WHERE t.chat_token = '$token'
    LIMIT 1
");
$ticket = $tQ ? $tQ->fetch_assoc() : null;
if (!$ticket) {
    http_response_code(404);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px">
        <h2>Link tidak valid</h2><p>Token tiket tidak ditemukan.</p>
        <a href="index">Buat tiket baru</a></body></html>');
}

$tid       = $ticket['uid'];
$status    = $ticket['status'];
$isClosed  = ($status === 'Closed');
$isWaiting = ($status === 'Waiting Confirm' || $status === 'Solved');

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isClosed) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $pesan = htmlspecialchars(strip_tags(trim($_POST['pesan'] ?? '')), ENT_QUOTES, 'UTF-8');
        $foto  = null;
        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $_FILES['foto']['size'] <= 5*1024*1024) {
                $fname = generateUUID() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/assets/uploads/' . $fname)) {
                    $foto = $fname;
                }
            }
        }
        if ($pesan || $foto) {
            $cuid  = generateUUID();
            $final = $pesan . ($foto ? "\n[img:{$foto}]" : '');
            $stmt  = $conn->prepare("INSERT INTO chats (uid,uid_ticket,sender,pesan) VALUES (?,?,'user',?)");
            $stmt->bind_param('sss', $cuid, $tid, $final);
            $stmt->execute();
            // Notif admin
            $admR = $conn->query("SELECT uid FROM admins");
            while ($adm = $admR->fetch_assoc()) {
                $nuid   = generateUUID();
                $njudul = "Pesan dari " . $ticket['nama_pelapor'];
                $npesan = "Tiket " . $ticket['kode_ticket'] . ": " . substr($final, 0, 80);
                $stmtN  = $conn->prepare("INSERT INTO notifikasi (uid,uid_admin,uid_ticket,judul,pesan) VALUES (?,?,?,?,?)");
                $stmtN->bind_param('sssss', $nuid, $adm['uid'], $tid, $njudul, $npesan);
                $stmtN->execute();
            }
        }
    }

    if ($action === 'confirm') {
        $conn->query("UPDATE tickets SET status='Closed', confirmed_at=NOW(), updated_at=NOW() WHERE uid='$tid'");
        $cuid  = generateUUID();
        $pesan = "Pelapor telah mengkonfirmasi bahwa masalah telah terselesaikan. Tiket ini sekarang ditutup. Terima kasih telah menggunakan Helpdesk IT NSC!";
        $stmt  = $conn->prepare("INSERT INTO chats (uid,uid_ticket,sender,pesan,is_system) VALUES (?,?,'admin',?,1)");
        $stmt->bind_param('sss', $cuid, $tid, $pesan);
        $stmt->execute();
    }

    if ($action === 'reopen') {
        $conn->query("UPDATE tickets SET status='Open', solved_at=NULL, auto_close_at=NULL, updated_at=NOW() WHERE uid='$tid'");
        $cuid  = generateUUID();
        $pesan = "Pelapor melaporkan masalah belum terselesaikan. Tiket dibuka kembali dan akan segera ditangani.";
        $stmt  = $conn->prepare("INSERT INTO chats (uid,uid_ticket,sender,pesan,is_system) VALUES (?,?,'admin',?,1)");
        $stmt->bind_param('sss', $cuid, $tid, $pesan);
        $stmt->execute();
    }

    header("Location: chat?token=" . urlencode($token));
    exit();
}

// Reload data
$tQ2   = $conn->query("SELECT t.*, k.nama_kategori, jp.nama AS jenis_pengirim FROM tickets t LEFT JOIN kategori k ON t.uid_kategori=k.uid LEFT JOIN jenis_pengirim jp ON t.uid_jenis_pengirim=jp.uid WHERE t.uid='$tid' LIMIT 1");
$ticket    = $tQ2->fetch_assoc();
$status    = $ticket['status'];
$isClosed  = ($status === 'Closed');
$isWaiting = ($status === 'Waiting Confirm' || $status === 'Solved');

// Load chats & mark read
$chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$tid' ORDER BY created_at ASC");
$conn->query("UPDATE chats SET dibaca=1 WHERE uid_ticket='$tid' AND sender='admin' AND dibaca=0");
$chatCount = (int)$conn->query("SELECT COUNT(*) c FROM chats WHERE uid_ticket='$tid'")->fetch_assoc()['c'];

// UID pesan terakhir — digunakan sebagai anchor untuk polling realtime
$lastChatRow = $conn->query("SELECT uid FROM chats WHERE uid_ticket='$tid' ORDER BY created_at DESC LIMIT 1");
$lastChatUid = ($lastChatRow && $row = $lastChatRow->fetch_assoc()) ? $row['uid'] : '';

$tokenJs = addslashes($token);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Chat — <?= htmlspecialchars($ticket['kode_ticket']) ?> — Helpdesk NSC</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* ═══ CHAT PAGE — VIEWPORT FIX ═══
   Strategi: semua elemen di-lock ke 100vh.
   Hanya .chat-body yang boleh scroll (overflow-y: auto).
   Tidak ada elemen lain yang boleh menyebabkan page-level scroll.
*/

/* 1. Root: paksa tinggi tepat 100vh, blok semua overflow di level page */
html {
    height: 100%;
    overflow: hidden;
}
body {
    height: 100%;
    overflow: hidden;
    margin: 0;
    padding: 0;
}

/* 2. Wrapper: flex column, tepat 100vh, tidak boleh melebihi layar */
.chat-wrapper {
    height: 100vh !important;
    min-height: 0 !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    box-sizing: border-box;
}

/* 3. Topbar & info bar: fixed height, tidak menyusut */
.chat-topbar {
    flex-shrink: 0 !important;
}
.chat-infobar {
    flex-shrink: 0 !important;
}

/* 4. Chat body: satu-satunya area yang scroll
      flex: 1 1 0 → ambil semua sisa ruang
      min-height: 0 → KRITIS agar flex child bisa menyusut di bawah kontennya
      overflow-y: auto → scroll internal
      width: 100% → isi penuh wrapper
*/
.chat-body {
    flex: 1 1 0 !important;
    min-height: 0 !important;
    max-height: none !important;
    width: 100% !important;
    box-sizing: border-box;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* 5. Footer: tidak tumbuh, tidak menyusut, selalu di bawah */
.chat-footer {
    flex-shrink: 0 !important;
    flex-grow: 0 !important;
}

/* 6. Scrollbar tipis supaya tidak mengganggu */
.chat-body::-webkit-scrollbar { width: 5px; }
.chat-body::-webkit-scrollbar-track { background: transparent; }
.chat-body::-webkit-scrollbar-thumb { background: #c5d0e0; border-radius: 4px; }
.chat-body::-webkit-scrollbar-thumb:hover { background: var(--primary); }

.new-msg-banner{position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;padding:9px 22px;border-radius:24px;font-size:.8rem;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 4px 16px rgba(0,0,0,.25);display:none;align-items:center;gap:8px;white-space:nowrap}

/* WhatsApp-style spacing between messages */
#chatBody { padding: 12px 4px; }
#chatBody .chat-bubble-wrap { margin-bottom: 6px; }
#chatBody > div[style*="justify-content:center"] { margin: 10px 0; }
</style>
</head>
<body>
<div class="chat-wrapper">

    <!-- Topbar -->
    <div class="chat-topbar">
        <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj" alt="NSC">
        <div class="chat-topbar-info">
            <h3>Helpdesk IT — <?= htmlspecialchars($ticket['kode_ticket']) ?></h3>
            <p><span class="chat-status-dot"></span><?= htmlspecialchars($ticket['judul']) ?> · <?= badgeStatus($status) ?></p>
        </div>
        <div style="margin-left:auto;font-size:.72rem;opacity:.7;text-align:right;line-height:1.6">
            <?= htmlspecialchars($ticket['nama_pelapor']) ?><br>
            <?= htmlspecialchars($ticket['email']) ?>
        </div>
    </div>

    <!-- Info Bar -->
    <div class="chat-infobar" style="background:#f7f9fc;border-bottom:1px solid var(--border);padding:7px 20px;font-size:.71rem;color:var(--text-muted);display:flex;gap:14px;flex-wrap:wrap;justify-content:center">
        <span>📋 <?= htmlspecialchars($ticket['kode_ticket']) ?></span>
        <span>🏷️ <?= htmlspecialchars($ticket['nama_kategori'] ?? '—') ?></span>
        <?php if ($ticket['jenis_pengirim']): ?>
        <span>👤 <?= htmlspecialchars($ticket['jenis_pengirim']) ?></span>
        <?php endif; ?>
        <span><?= badgePrioritas($ticket['prioritas']) ?></span>
        <span>📅 <?= date('d M Y H:i', strtotime($ticket['created_at'])) ?></span>
        <?php if ($ticket['auto_close_at']): ?>
        <span style="color:var(--warning)">⏰ Auto-close: <?= date('d M Y', strtotime($ticket['auto_close_at'])) ?></span>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <div class="chat-body" id="chatBody">
    <?php while ($chat = $chats->fetch_assoc()):
        $isSystem = $chat['is_system'];
        $sender   = $chat['sender'];
        $raw      = $chat['pesan'];
        $imgFile  = null;
        if (preg_match('/\[img:([^\]]+)\]/', $raw, $m)) {
            $imgFile = $m[1];
            $raw     = trim(str_replace($m[0], '', $raw));
        }
        $raw = nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));
    ?>

    <?php if ($isSystem): ?>
    <div style="display:flex;justify-content:center">
        <div class="chat-bubble system">
            <?= $raw ?>
            <div class="chat-time"><?= date('d M Y H:i', strtotime($chat['created_at'])) ?></div>
        </div>
    </div>
    <?php elseif ($sender === 'admin'): ?>
    <div class="chat-bubble-wrap admin">
        <div class="chat-avatar admin-av">IT</div>
        <div>
            <div style="font-size:.67rem;color:var(--text-muted);margin-bottom:3px">Admin Helpdesk IT</div>
            <div class="chat-bubble admin">
                <?php if ($imgFile): ?><img src="assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src)"><?php endif; ?>
                <?= $raw ?>
                <div class="chat-time"><?= date('d M Y H:i', strtotime($chat['created_at'])) ?></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="chat-bubble-wrap user">
        <div>
            <div style="font-size:.67rem;color:var(--text-muted);margin-bottom:3px;text-align:right"><?= htmlspecialchars($ticket['nama_pelapor']) ?></div>
            <div class="chat-bubble user">
                <?php if ($imgFile): ?><img src="assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src)"><?php endif; ?>
                <?= $raw ?>
                <div class="chat-time"><?= date('d M Y H:i', strtotime($chat['created_at'])) ?></div>
            </div>
        </div>
        <div class="chat-avatar user-av"><?= strtoupper(substr($ticket['nama_pelapor'],0,1)) ?></div>
    </div>
    <?php endif; ?>
    <?php endwhile; ?>
    </div>

    <!-- New message banner (shown instead of full reload when typing) -->
    <div class="new-msg-banner" id="newMsgBanner" onclick="reloadNow()">
        🔔 Ada pesan baru — Ketuk untuk lihat
    </div>

    <!-- Footer -->
    <div class="chat-footer">
    <?php if ($isWaiting): ?>
        <div class="chat-confirm-bar">
            ✅ Tim IT telah menyelesaikan masalah Anda.<br>
            Apakah masalah sudah benar-benar terselesaikan?
        </div>
        <div style="max-width:800px;margin:0 auto;display:flex;gap:10px">
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="confirm">
                <button class="btn btn-success btn-full">✅ Ya, Sudah Selesai</button>
            </form>
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="reopen">
                <button class="btn btn-danger btn-full">❌ Belum, Masih Bermasalah</button>
            </form>
        </div>
    <?php elseif ($isClosed): ?>
        <div class="chat-closed-bar">
            🔒 Tiket ini telah ditutup. Jika ada masalah baru, silakan
            <a href="index" style="color:var(--primary);font-weight:600">buat tiket baru</a>.
        </div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" id="chatForm" class="chat-input-area">
            <input type="hidden" name="action" value="send">
            <label for="chatFoto" style="cursor:pointer;font-size:1.2rem;padding:8px;color:var(--text-muted)" title="Upload gambar">📷</label>
            <input type="file" id="chatFoto" name="foto" accept="image/*" style="display:none" onchange="previewChatImg(this)">
            <textarea name="pesan" id="chatPesan"
                      placeholder="Tulis pesan... (Enter kirim, Shift+Enter baris baru)"
                      rows="1"
                      oninput="autoResize(this); saveDraft(this.value)"
                      onkeydown="handleEnter(event)"></textarea>
            <button type="submit" class="btn btn-primary">Kirim →</button>
        </form>
        <div id="chatImgPreview" style="max-width:800px;margin:6px auto 0;display:none;align-items:center;gap:8px">
            <img id="chatImgThumb" style="max-height:50px;border-radius:6px" alt="">
            <span onclick="clearImg()" style="cursor:pointer;color:var(--danger);font-size:.75rem">✕ Hapus</span>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
const DRAFT_KEY  = 'hd_draft_<?= $tokenJs ?>';
const CHAT_TOKEN = '<?= $tokenJs ?>';

// ── Scroll ke bawah ──────────────────────────────────────────
const chatBody = document.getElementById('chatBody');
function scrollBottom(smooth) {
    if (!chatBody) return;
    chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
}
scrollBottom(false);

// ── Restore draft ─────────────────────────────────────────────
const pesanTA = document.getElementById('chatPesan');
if (pesanTA) {
    const saved = sessionStorage.getItem(DRAFT_KEY);
    if (saved) { pesanTA.value = saved; autoResize(pesanTA); }
}

function saveDraft(val) { sessionStorage.setItem(DRAFT_KEY, val); }

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 130) + 'px';
}

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        doSend();
    }
}

function doSend() {
    sessionStorage.removeItem(DRAFT_KEY);
    document.getElementById('chatForm').submit();
}

const chatForm = document.getElementById('chatForm');
if (chatForm) {
    chatForm.addEventListener('submit', () => sessionStorage.removeItem(DRAFT_KEY));
}

// ════════════════════════════════════════════════════════════════
//  REALTIME POLLING — inject pesan baru ke DOM tanpa reload halaman
// ════════════════════════════════════════════════════════════════
let lastMsgUid  = '<?= addslashes($lastChatUid ?? '') ?>';  // uid pesan terakhir saat halaman dimuat
let currentStatus = '<?= addslashes($status) ?>';
const USER_NAME = '<?= addslashes(htmlspecialchars($ticket['nama_pelapor'], ENT_QUOTES)) ?>';
const NAMA_AWAL = USER_NAME.charAt(0).toUpperCase();

// Render bubble pesan dari data JSON
function renderBubble(msg) {
    // Parse pesan: cek apakah ada gambar [img:xxx]
    let rawPesan = msg.pesan;
    let imgFile  = null;
    const imgMatch = rawPesan.match(/\[img:([^\]]+)\]/);
    if (imgMatch) {
        imgFile  = imgMatch[1];
        rawPesan = rawPesan.replace(imgMatch[0], '').trim();
    }

    // Escape HTML
    const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    // Bold markdown **text**
    let pesanHtml = esc(rawPesan).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');

    // Format waktu
    const dt   = new Date(msg.created_at.replace(' ', 'T'));
    const time = dt.toLocaleDateString('id-ID', {day:'2-digit',month:'short'}) + ' ' +
                 dt.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});

    const imgHtml = imgFile
        ? `<img src="assets/uploads/${esc(imgFile)}" class="chat-img" onclick="window.open(this.src)">`
        : '';

    let html = '';

    if (msg.is_system) {
        html = `<div style="display:flex;justify-content:center">
            <div class="chat-bubble system">
                ${pesanHtml}
                <div class="chat-time">${time}</div>
            </div>
        </div>`;
    } else if (msg.sender === 'admin') {
        html = `<div class="chat-bubble-wrap admin">
            <div class="chat-avatar admin-av">IT</div>
            <div>
                <div style="font-size:.67rem;color:var(--text-muted);margin-bottom:3px">Admin Helpdesk IT</div>
                <div class="chat-bubble admin">
                    ${imgHtml}${pesanHtml}
                    <div class="chat-time">${time}</div>
                </div>
            </div>
        </div>`;
    } else {
        html = `<div class="chat-bubble-wrap user">
            <div>
                <div style="font-size:.67rem;color:var(--text-muted);margin-bottom:3px;text-align:right">${esc(USER_NAME)}</div>
                <div class="chat-bubble user">
                    ${imgHtml}${pesanHtml}
                    <div class="chat-time">${time}</div>
                </div>
            </div>
            <div class="chat-avatar user-av">${NAMA_AWAL}</div>
        </div>`;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    return wrapper.firstElementChild;
}

// Cek apakah scroll sedang di bawah (threshold 60px)
function isNearBottom() {
    if (!chatBody) return true;
    return chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 60;
}

function pollChat() {
    const url = 'api/chat_messages?token=' + encodeURIComponent(CHAT_TOKEN)
              + (lastMsgUid ? '&after=' + encodeURIComponent(lastMsgUid) : '');

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;

            // ── Injeksi pesan baru ke DOM ──
            if (data.messages && data.messages.length > 0) {
                const wasAtBottom = isNearBottom();
                data.messages.forEach(msg => {
                    const el = renderBubble(msg);
                    if (el) chatBody.appendChild(el);
                    lastMsgUid = msg.uid;
                });
                if (wasAtBottom) scrollBottom(true);
                else {
                    // Tampilkan banner notif jika user scroll ke atas
                    document.getElementById('newMsgBanner').style.display = 'flex';
                }
            }

            // ── Jika status berubah (mis. Solved → Closed), reload untuk update footer ──
            if (data.status && data.status !== currentStatus) {
                location.reload();
            }
        })
        .catch(() => {}); // silent fail
}

function reloadNow() {
    sessionStorage.removeItem(DRAFT_KEY);
    location.reload();
}

// Poll setiap 3 detik
setInterval(pollChat, 3000);

// Sembunyikan banner saat user scroll ke bawah manual
if (chatBody) {
    chatBody.addEventListener('scroll', () => {
        if (isNearBottom()) {
            document.getElementById('newMsgBanner').style.display = 'none';
        }
    });
}

// ── Preview foto ─────────────────────────────────────────────
function previewChatImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('chatImgThumb').src = e.target.result;
            document.getElementById('chatImgPreview').style.display = 'flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function clearImg() {
    document.getElementById('chatFoto').value = '';
    document.getElementById('chatImgPreview').style.display = 'none';
}
</script>
</body>
</html>
