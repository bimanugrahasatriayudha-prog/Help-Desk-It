<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
$admin = getAdmin();
$auid  = $conn->real_escape_string($admin['uid']);

// Mark all as read
if (isset($_GET['read_all'])) {
    $conn->query("UPDATE notifikasi SET is_read=1 WHERE uid_admin='$auid'");
    header('Location: notifikasi'); exit();
}

$notifs = $conn->query("
    SELECT n.*, t.kode_ticket, t.judul as tiket_judul, t.uid as tiket_uid
    FROM notifikasi n
    JOIN tickets t ON n.uid_ticket = t.uid
    WHERE n.uid_admin = '$auid'
    ORDER BY n.created_at DESC
    LIMIT 50
");
$unread = (int)$conn->query("SELECT COUNT(*) c FROM notifikasi WHERE uid_admin='$auid' AND is_read=0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notifikasi — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:4px">
                <button class="hamburger" onclick="openSidebar()">☰</button>
                <div>
                    <div class="topbar-title">🔔 Notifikasi</div>
                    <div class="topbar-subtitle"><?= $unread ?> belum dibaca</div>
                </div>
            </div>
            <div class="topbar-right">
                <?php if ($unread > 0): ?>
                <a href="notifikasi?read_all=1" class="btn btn-outline btn-sm">✓ Tandai Semua Dibaca</a>
                <?php endif; ?>
            </div>
        </header>
        <main class="page-content">
            <div class="card">
                <div class="table-responsive">
                <table>
                    <thead><tr><th>Status</th><th>Judul</th><th>Pesan</th><th>Tiket</th><th>Waktu</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php while ($n = $notifs->fetch_assoc()): ?>
                    <tr style="<?= !$n['is_read'] ? 'background:#eff6ff' : '' ?>">
                        <td><?= !$n['is_read'] ? '<span class="badge badge-open">Baru</span>' : '<span class="badge badge-closed">Dibaca</span>' ?></td>
                        <td><strong><?= htmlspecialchars($n['judul']) ?></strong></td>
                        <td style="font-size:.78rem"><?= htmlspecialchars(substr($n['pesan'],0,80)) ?>...</td>
                        <td><span class="ticket-code"><?= htmlspecialchars($n['kode_ticket']) ?></span></td>
                        <td><?= date('d M Y H:i', strtotime($n['created_at'])) ?></td>
                        <td><a href="ticket_detail?uid=<?= urlencode($n['tiket_uid']) ?>" class="btn btn-sm btn-primary">💬 Buka</a></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
