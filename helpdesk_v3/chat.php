<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

autoCloseTickets($conn);

$token = trim($_GET['token'] ?? '');
if (!$token) { header('Location: index.php'); exit(); }

$token = $conn->real_escape_string($token);
$tQ = $conn->query("
    SELECT t.*, k.nama_kategori
    FROM tickets t
    LEFT JOIN kategori k ON t.uid_kategori = k.uid
    WHERE t.chat_token = '$token'
    LIMIT 1
");
$ticket = $tQ->fetch_assoc();
if (!$ticket) {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif">
        <h2>❌ Link tidak valid</h2>
        <p>Token tiket tidak ditemukan.</p>
        <a href="index.php">← Kembali</a>
    </div>');
}

$tid    = $conn->real_escape_string($ticket['uid']);
$status = $ticket['status'];
$isClosed = $status === 'Closed';

// Handle user kirim pesan
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isClosed) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $pesan = trim($_POST['pesan'] ?? '');
        $foto  = null;

        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $fname = generateUUID() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/assets/uploads/' . $fname)) {
                    $foto = $fname;
                }
            }
        }

        if ($pesan || $foto) {
            $cuid = generateUUID();
            $finalPesan = $pesan ?: '[Gambar]';
            if ($foto) $finalPesan .= "\n[img:{$foto}]";
            $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan) VALUES (?,?,'user',?)");
            $stmt->bind_param('sss', $cuid, $tid, $finalPesan);
            $stmt->execute();

            // Notif ke admin
            $admins = $conn->query("SELECT uid FROM admins");
            while ($adm = $admins->fetch_assoc()) {
                $nuid  = generateUUID();
                $njudul = "Pesan baru dari " . $ticket['nama_pelapor'];
                $npesan = "Tiket {$ticket['kode_ticket']}: " . substr($finalPesan, 0, 80);
                $stmtN = $conn->prepare("INSERT INTO notifikasi (uid, uid_admin, uid_ticket, judul, pesan) VALUES (?,?,?,?,?)");
                $stmtN->bind_param('sssss', $nuid, $adm['uid'], $tid, $njudul, $npesan);
                $stmtN->execute();
            }
        }
    }

    if ($action === 'confirm') {
        // User konfirmasi selesai
        $conn->query("UPDATE tickets SET status='Closed', confirmed_at=NOW() WHERE uid='$tid'");
        $cuid = generateUUID();
        $pesan = "✅ Pelapor telah mengkonfirmasi bahwa masalah telah terselesaikan. Tiket ini sekarang ditutup. Terima kasih!";
        $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system) VALUES (?,?,'admin',?,1)");
        $stmt->bind_param('sss', $cuid, $tid, $pesan);
        $stmt->execute();
        header("Location: chat.php?token=$token");
        exit();
    }

    header("Location: chat.php?token=$token");
    exit();
}

// Refresh ticket status
$tQ2 = $conn->query("SELECT * FROM tickets WHERE chat_token='$token' LIMIT 1");
$ticket = $tQ2->fetch_assoc();
$status = $ticket['status'];
$isClosed = $status === 'Closed';
$isWaiting = $status === 'Waiting Confirm';

// Load chats
$chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$tid' ORDER BY created_at ASC");

// Mark admin chats as read
$conn->query("UPDATE chats SET dibaca=1 WHERE uid_ticket='$tid' AND sender='admin' AND dibaca=0");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Chat Tiket <?= htmlspecialchars($ticket['kode_ticket']) ?> — Helpdesk NSC</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="chat-wrapper">
    <!-- Topbar -->
    <div class="chat-topbar">
        <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj" alt="NSC">
        <div class="chat-topbar-info">
            <h3>Helpdesk IT — <?= htmlspecialchars($ticket['kode_ticket']) ?></h3>
            <p>
                <span class="chat-status-dot"></span>
                <?= htmlspecialchars($ticket['judul']) ?> · <?= badgeStatus($status) ?>
            </p>
        </div>
        <div style="margin-left:auto;font-size:.75rem;opacity:.7;text-align:right">
            <?= htmlspecialchars($ticket['nama_pelapor']) ?><br>
            <?= htmlspecialchars($ticket['email']) ?>
        </div>
    </div>

    <!-- Info tiket -->
    <div style="background:#f7f9fc;border-bottom:1px solid var(--border);padding:10px 20px;font-size:.75rem;color:var(--text-muted);display:flex;gap:20px;flex-wrap:wrap;justify-content:center">
        <span>📋 <?= htmlspecialchars($ticket['kode_ticket']) ?></span>
        <span>🏷️ <?= htmlspecialchars($ticket['nama_kategori'] ?? '—') ?></span>
        <span><?= badgePrioritas($ticket['prioritas']) ?></span>
        <span>📅 <?= date('d M Y H:i', strtotime($ticket['created_at'])) ?></span>
        <?php if ($ticket['auto_close_at']): ?>
        <span>⏰ Auto-close: <?= date('d M Y', strtotime($ticket['auto_close_at'])) ?></span>
        <?php endif; ?>
    </div>

    <!-- Chat messages -->
    <div class="chat-body" id="chatBody">
        <?php while ($chat = $chats->fetch_assoc()):
            $isSystem = $chat['is_system'];
            $sender   = $chat['sender'];
            $rawPesan = $chat['pesan'];

            // Parse image tag
            $imgFile = null;
            if (preg_match('/\[img:([^\]]+)\]/', $rawPesan, $m)) {
                $imgFile  = $m[1];
                $rawPesan = trim(str_replace($m[0], '', $rawPesan));
            }
            // Parse markdown bold
            $rawPesan = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($rawPesan));
            $rawPesan = nl2br($rawPesan);
        ?>

        <?php if ($isSystem): ?>
        <div style="display:flex;justify-content:center">
            <div class="chat-bubble system">
                <?= $rawPesan ?>
                <div class="chat-time"><?= date('d M Y H:i', strtotime($chat['created_at'])) ?></div>
            </div>
        </div>

        <?php elseif ($sender === 'admin'): ?>
        <div class="chat-bubble-wrap admin">
            <div class="chat-avatar admin-av">IT</div>
            <div>
                <div style="font-size:.68rem;color:var(--text-muted);margin-bottom:3px">Admin Helpdesk IT</div>
                <div class="chat-bubble admin">
                    <?= $rawPesan ?>
                    <?php if ($imgFile): ?>
                    <img src="assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src)">
                    <?php endif; ?>
                    <div class="chat-time"><?= date('d M Y H:i', strtotime($chat['created_at'])) ?></div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="chat-bubble-wrap user">
            <div>
                <div style="font-size:.68rem;color:var(--text-muted);margin-bottom:3px;text-align:right"><?= htmlspecialchars($ticket['nama_pelapor']) ?></div>
                <div class="chat-bubble user">
                    <?= $rawPesan ?>
                    <?php if ($imgFile): ?>
                    <img src="assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src)">
                    <?php endif; ?>
                    <div class="chat-time"><?= date('d M Y H:i', strtotime($chat['created_at'])) ?></div>
                </div>
            </div>
            <div class="chat-avatar user-av"><?= strtoupper(substr($ticket['nama_pelapor'],0,1)) ?></div>
        </div>
        <?php endif; ?>

        <?php endwhile; ?>
    </div>

    <!-- Footer -->
    <div class="chat-footer">
        <?php if ($isWaiting): ?>
        <!-- Waiting Confirm -->
        <div class="chat-confirm-bar">
            ✅ Admin telah menandai masalah ini sebagai <strong>Selesai</strong>.<br>
            Apakah masalah Anda sudah benar-benar terselesaikan?
        </div>
        <div style="max-width:800px;margin:0 auto;display:flex;gap:10px">
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-success btn-full">✅ Ya, Masalah Sudah Selesai</button>
            </form>
            <form method="POST" enctype="multipart/form-data" style="flex:1" id="chatForm">
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="pesan" value="Masalah belum terselesaikan. Mohon bantuannya kembali.">
                <button type="submit" class="btn btn-danger btn-full">❌ Belum, Masih Ada Masalah</button>
            </form>
        </div>

        <?php elseif ($isClosed): ?>
        <div class="chat-closed-bar">
            🔒 Tiket ini telah ditutup. Jika mengalami masalah baru, silakan <a href="index.php" style="color:var(--primary);font-weight:600">buat tiket baru</a>.
        </div>

        <?php else: ?>
        <!-- Input chat -->
        <form method="POST" enctype="multipart/form-data" id="chatForm" class="chat-input-area">
            <input type="hidden" name="action" value="send">
            <label for="chatFoto" style="cursor:pointer;font-size:1.3rem;padding:8px;color:var(--text-muted)" title="Upload gambar">📷</label>
            <input type="file" id="chatFoto" name="foto" accept="image/*" style="display:none" onchange="previewChatImg(this)">
            <textarea name="pesan" id="chatPesan" placeholder="Tulis pesan..." rows="1"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('chatForm').submit()}"
                      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
            <button type="submit" class="btn btn-primary">Kirim →</button>
        </form>
        <div id="chatImgPreview" style="max-width:800px;margin:8px auto 0;display:none">
            <img id="chatImgThumb" style="max-height:60px;border-radius:6px">
            <span onclick="clearImg()" style="cursor:pointer;color:var(--danger);font-size:.75rem;margin-left:8px">✕ Hapus</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto scroll ke bawah
const cb = document.getElementById('chatBody');
if (cb) cb.scrollTop = cb.scrollHeight;

// Auto refresh tiap 5 detik
setTimeout(() => location.reload(), 5000);

function previewChatImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('chatImgThumb').src = e.target.result;
            document.getElementById('chatImgPreview').style.display = 'block';
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
