<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
autoCloseTickets($conn);

$admin = getAdmin();
$uid   = trim($_GET['uid'] ?? '');
if (!$uid) { header('Location: tickets.php'); exit(); }

$uid_esc = $conn->real_escape_string($uid);
$tQ = $conn->query("SELECT t.*, k.nama_kategori FROM tickets t LEFT JOIN kategori k ON t.uid_kategori=k.uid WHERE t.uid='$uid_esc' LIMIT 1");
$ticket = $tQ->fetch_assoc();
if (!$ticket) { header('Location: tickets.php'); exit(); }

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
        }
    }

    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? $ticket['status'];
        $extra = '';
        if ($newStatus === 'Waiting Confirm') {
            // Set auto close 5 hari
            $extra = ", solved_at=NOW(), auto_close_at=DATE_ADD(NOW(), INTERVAL 5 DAY)";
            // Chat notif ke user
            $cuid  = generateUUID();
            $pesan = "✅ Tim IT telah menyelesaikan masalah Anda.\n\nMohon konfirmasi apakah masalah sudah benar-benar terselesaikan dengan menekan tombol **\"Ya, Masalah Sudah Selesai\"** di bawah.\n\nJika tidak ada konfirmasi dalam 5 hari, tiket akan otomatis ditutup. Terima kasih! 🙏";
            $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system) VALUES (?,?,'admin',?,1)");
            $stmt->bind_param('sss', $cuid, $uid_esc, $pesan);
            $stmt->execute();
        }
        $conn->query("UPDATE tickets SET status='$newStatus'$extra, updated_at=NOW() WHERE uid='$uid_esc'");

        // System chat for status change
        if ($newStatus !== 'Waiting Confirm') {
            $cuid  = generateUUID();
            $pesan = "Status tiket diperbarui menjadi: $newStatus";
            $stmt = $conn->prepare("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system) VALUES (?,?,'admin',?,1)");
            $stmt->bind_param('sss', $cuid, $uid_esc, $pesan);
            $stmt->execute();
        }
    }

    if ($action === 'update_prioritas') {
        $p = $_POST['prioritas'] ?? $ticket['prioritas'];
        $conn->query("UPDATE tickets SET prioritas='$p', updated_at=NOW() WHERE uid='$uid_esc'");
    }

    header("Location: ticket_detail.php?uid=" . urlencode($uid) . "&updated=1");
    exit();
}

// Reload ticket
$tQ2 = $conn->query("SELECT t.*, k.nama_kategori FROM tickets t LEFT JOIN kategori k ON t.uid_kategori=k.uid WHERE t.uid='$uid_esc' LIMIT 1");
$ticket = $tQ2->fetch_assoc();

// Mark admin notifs as read for this ticket
$auid_esc = $conn->real_escape_string($admin['uid']);
$conn->query("UPDATE notifikasi SET is_read=1 WHERE uid_ticket='$uid_esc' AND uid_admin='$auid_esc'");

// Chats
$chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$uid_esc' ORDER BY created_at ASC");
// Mark user chats as read
$conn->query("UPDATE chats SET dibaca=1 WHERE uid_ticket='$uid_esc' AND sender='user' AND dibaca=0");

$chatLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/chat.php?token=' . $ticket['chat_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tiket <?= htmlspecialchars($ticket['kode_ticket']) ?> — Admin NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.detail-grid { display: grid; grid-template-columns: 340px 1fr; gap: 18px; height: calc(100vh - 120px); }
.chat-panel { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; background: #fff; }
.chat-panel-header { background: var(--primary-dark); color:#fff; padding:12px 16px; font-size:.85rem; font-weight:600; }
.chat-panel-body { flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px; background:#f7f9fc; }
.chat-panel-footer { padding:12px; border-top:1px solid var(--border); background:#fff; }
.info-panel { overflow-y: auto; }
</style>
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div>
                <div class="topbar-title">Detail Tiket — <?= htmlspecialchars($ticket['kode_ticket']) ?></div>
                <div class="topbar-subtitle"><?= htmlspecialchars($ticket['judul']) ?></div>
            </div>
            <div class="topbar-right">
                <a href="javascript:navigator.clipboard.writeText('<?= $chatLink ?>').then(()=>alert('Link disalin!'))" class="btn btn-sm btn-outline">📋 Salin Link User</a>
                <a href="tickets.php" class="btn btn-sm btn-outline">← Kembali</a>
            </div>
        </header>

        <main class="page-content" style="padding:16px">
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success" style="margin-bottom:12px">✅ Berhasil diperbarui.</div>
            <?php endif; ?>

            <div class="detail-grid">
                <!-- LEFT: Info Panel -->
                <div class="info-panel" style="display:flex;flex-direction:column;gap:14px">

                    <!-- Ticket Info -->
                    <div class="card">
                        <div class="card-header"><h3>📋 Info Tiket</h3><?= badgeStatus($ticket['status']) ?></div>
                        <div class="card-body" style="padding:14px">
                            <table style="font-size:.8rem;width:100%">
                                <tr><td style="color:var(--text-muted);padding:4px 0;width:110px">Kode</td><td><span class="ticket-code"><?= htmlspecialchars($ticket['kode_ticket']) ?></span></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Kategori</td><td><?= htmlspecialchars($ticket['nama_kategori'] ?? '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Prioritas</td><td><?= badgePrioritas($ticket['prioritas']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Pelapor</td><td><strong><?= htmlspecialchars($ticket['nama_pelapor']) ?></strong></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Email</td><td><?= htmlspecialchars($ticket['email']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">No. Telp</td><td><?= htmlspecialchars($ticket['no_telpon']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Masuk</td><td><?= date('d M Y H:i', strtotime($ticket['created_at'])) ?></td></tr>
                                <?php if ($ticket['auto_close_at']): ?>
                                <tr><td style="color:var(--text-muted);padding:4px 0">Auto-close</td><td style="color:var(--warning)"><?= date('d M Y H:i', strtotime($ticket['auto_close_at'])) ?></td></tr>
                                <?php endif; ?>
                            </table>
                            <hr class="divider">
                            <p style="font-size:.8rem;line-height:1.7"><?= nl2br(htmlspecialchars($ticket['deskripsi'])) ?></p>
                            <?php if ($ticket['foto']): ?>
                            <div style="margin-top:10px">
                                <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:5px">📷 Foto Pendukung:</p>
                                <img src="../assets/uploads/<?= htmlspecialchars($ticket['foto']) ?>" style="max-width:100%;border-radius:8px;cursor:pointer" onclick="window.open(this.src)">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Update Status -->
                    <?php if (!in_array($ticket['status'], ['Closed'])): ?>
                    <div class="card">
                        <div class="card-header"><h3>✏️ Update</h3></div>
                        <div class="card-body" style="padding:14px;display:flex;flex-direction:column;gap:10px">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="form-group" style="margin-bottom:8px">
                                    <label class="form-label" style="font-size:.76rem">Status Tiket</label>
                                    <select name="status" class="form-control" style="font-size:.8rem">
                                        <?php foreach (['Open','On Progress','Solved','Waiting Confirm','Closed'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm btn-full">💾 Update Status</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_prioritas">
                                <div class="form-group" style="margin-bottom:8px">
                                    <label class="form-label" style="font-size:.76rem">Prioritas</label>
                                    <select name="prioritas" class="form-control" style="font-size:.8rem">
                                        <?php foreach (['Low','Medium','High'] as $p): ?>
                                        <option value="<?= $p ?>" <?= $ticket['prioritas']===$p?'selected':'' ?>><?= $p ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-outline btn-sm btn-full">Update Prioritas</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Chat Panel -->
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
                        <div class="chat-bubble system" style="max-width:90%;font-size:.76rem"><?= $rawPesan ?><div class="chat-time"><?= date('d M H:i', strtotime($chat['created_at'])) ?></div></div>
                    </div>
                    <?php elseif ($sender === 'admin'): ?>
                    <div class="chat-bubble-wrap admin">
                        <div class="chat-avatar admin-av">IT</div>
                        <div>
                            <div class="chat-bubble admin" style="font-size:.8rem">
                                <?= $rawPesan ?>
                                <?php if ($imgFile): ?><img src="../assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src)"><?php endif; ?>
                                <div class="chat-time"><?= date('d M H:i', strtotime($chat['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="chat-bubble-wrap user">
                        <div>
                            <div class="chat-bubble user" style="font-size:.8rem">
                                <?= $rawPesan ?>
                                <?php if ($imgFile): ?><img src="../assets/uploads/<?= htmlspecialchars($imgFile) ?>" class="chat-img" onclick="window.open(this.src)"><?php endif; ?>
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
                            <div style="display:flex;gap:8px;align-items:flex-end">
                                <label for="adminFoto" style="cursor:pointer;font-size:1.2rem;color:var(--text-muted)" title="Upload gambar">📷</label>
                                <input type="file" id="adminFoto" name="foto" accept="image/*" style="display:none">
                                <textarea name="pesan" placeholder="Ketik pesan..." rows="2" class="form-control" style="flex:1;font-size:.8rem;resize:none"
                                          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('adminChatForm').submit()}"></textarea>
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
            </div>
        </main>
    </div>
</div>

<script>
const cb = document.getElementById('chatBody');
if (cb) cb.scrollTop = cb.scrollHeight;
// Auto refresh
setTimeout(() => location.reload(), 8000);
</script>
</body>
</html>
