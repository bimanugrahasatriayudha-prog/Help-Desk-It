<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
autoCloseTickets($conn);

$admin = getAdmin();
$auid  = $conn->real_escape_string($admin['uid']);

// Stats
$total    = (int)$conn->query("SELECT COUNT(*) c FROM tickets")->fetch_assoc()['c'];
$open     = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Open'")->fetch_assoc()['c'];
$progress = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='On Progress'")->fetch_assoc()['c'];
$solved   = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Solved'")->fetch_assoc()['c'];
$waiting  = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Waiting Confirm'")->fetch_assoc()['c'];
$closed   = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Closed'")->fetch_assoc()['c'];
$high     = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE prioritas='High' AND status NOT IN('Solved','Closed')")->fetch_assoc()['c'];
$unread   = (int)$conn->query("SELECT COUNT(*) c FROM notifikasi WHERE uid_admin='$auid' AND is_read=0")->fetch_assoc()['c'];

// Recent tickets
$recent = $conn->query("
    SELECT t.*, k.nama_kategori
    FROM tickets t
    LEFT JOIN kategori k ON t.uid_kategori = k.uid
    ORDER BY t.created_at DESC
    LIMIT 10
");

// Tiket per kategori
$catStats = $conn->query("
    SELECT k.nama_kategori, COUNT(t.uid) total
    FROM kategori k LEFT JOIN tickets t ON k.uid = t.uid_kategori
    GROUP BY k.uid ORDER BY total DESC
");

$h = (int)date('H');
$greeting = $h < 12 ? 'Selamat Pagi' : ($h < 15 ? 'Selamat Siang' : ($h < 18 ? 'Selamat Sore' : 'Selamat Malam'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard Admin — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.banner {
    background: linear-gradient(135deg,#002855,#003d7a,#1565c0);
    border-radius: var(--radius);
    padding: 22px 26px;
    color: #fff;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(0,40,85,.3);
}
.banner h2 { font-size: 1.1rem; font-weight: 700; }
.banner p  { font-size: .8rem; opacity: .8; margin-top: 4px; }
.banner-stats { display: flex; gap: 20px; }
.bstat .val { font-size: 1.5rem; font-weight: 700; color: var(--accent); }
.bstat .lbl { font-size: .68rem; opacity: .7; }
.high-alert { background:#fff0f0;border:1px solid #fca5a5;border-left:4px solid var(--danger);border-radius:var(--radius-sm);padding:11px 15px;font-size:.82rem;display:flex;align-items:center;gap:10px;margin-bottom:18px;color:var(--danger); }
.progress { background:#e9edf4;border-radius:20px;height:7px;overflow:hidden;margin-top:5px; }
.progress-bar { height:100%;border-radius:20px; }
.pb-blue{background:var(--primary-light)} .pb-green{background:var(--success)} .pb-orange{background:var(--warning)} .pb-red{background:var(--danger)} .pb-purple{background:#7c3aed}
</style>
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div>
                <div class="topbar-title">Dashboard Admin</div>
                <div class="topbar-subtitle"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-right">
                <!-- Notif Bell -->
                <div style="position:relative">
                    <div class="notif-btn" onclick="toggleNotif()" id="notifBtn">
                        🔔
                        <?php if ($unread > 0): ?>
                        <span class="notif-count"><?= $unread > 9 ? '9+' : $unread ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-dropdown-header">
                            🔔 Notifikasi
                            <a href="notifikasi.php" style="font-size:.73rem;color:var(--primary)">Lihat Semua</a>
                        </div>
                        <?php
                        $notifs = $conn->query("SELECT n.*, t.kode_ticket FROM notifikasi n JOIN tickets t ON n.uid_ticket=t.uid WHERE n.uid_admin='$auid' ORDER BY n.created_at DESC LIMIT 5");
                        $hasNotif = false;
                        while ($n = $notifs->fetch_assoc()):
                            $hasNotif = true;
                        ?>
                        <a href="ticket_detail.php?uid=<?= urlencode($n['uid_ticket']) ?>" class="notif-item <?= !$n['is_read']?'unread':'' ?>" style="display:block">
                            <div class="notif-title"><?= htmlspecialchars($n['judul']) ?></div>
                            <div style="font-size:.75rem;margin:2px 0"><?= htmlspecialchars(substr($n['pesan'],0,70)) ?>...</div>
                            <div class="notif-time"><?= date('d M H:i', strtotime($n['created_at'])) ?> · <?= $n['kode_ticket'] ?></div>
                        </a>
                        <?php endwhile; ?>
                        <?php if (!$hasNotif): ?>
                        <div class="notif-empty">Tidak ada notifikasi</div>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">🌐 Form User</a>
            </div>
        </header>

        <main class="page-content">
            <?php if ($high > 0): ?>
            <div class="high-alert">
                🚨 <strong><?= $high ?> tiket prioritas HIGH</strong> belum terselesaikan!
                <a href="tickets.php?prioritas=High" style="margin-left:auto;font-weight:600;text-decoration:underline">Tangani →</a>
            </div>
            <?php endif; ?>

            <!-- Banner -->
            <div class="banner">
                <div>
                    <h2><?= $greeting ?>, <?= htmlspecialchars($admin['nama']) ?>! 👋</h2>
                    <p>Helpdesk IT Politeknik NSC Surabaya — <?= $open + $progress ?> tiket aktif perlu perhatian</p>
                </div>
                <div class="banner-stats">
                    <div class="bstat"><div class="val"><?= $total ?></div><div class="lbl">Total</div></div>
                    <div class="bstat"><div class="val"><?= $open ?></div><div class="lbl">Open</div></div>
                    <div class="bstat"><div class="val"><?= $waiting ?></div><div class="lbl">Waiting</div></div>
                    <div class="bstat"><div class="val"><?= $closed ?></div><div class="lbl">Closed</div></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon blue">🎫</div><div class="stat-info"><div class="value"><?= $total ?></div><div class="label">Total Tiket</div></div></div>
                <div class="stat-card"><div class="stat-icon orange">📬</div><div class="stat-info"><div class="value"><?= $open ?></div><div class="label">Open</div></div></div>
                <div class="stat-card"><div class="stat-icon purple">⚙️</div><div class="stat-info"><div class="value"><?= $progress ?></div><div class="label">On Progress</div></div></div>
                <div class="stat-card"><div class="stat-icon teal">🕐</div><div class="stat-info"><div class="value"><?= $waiting ?></div><div class="label">Waiting Confirm</div></div></div>
                <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><div class="value"><?= $solved ?></div><div class="label">Solved</div></div></div>
                <div class="stat-card"><div class="stat-icon red">🔒</div><div class="stat-info"><div class="value"><?= $closed ?></div><div class="label">Closed</div></div></div>
            </div>

            <div class="grid-2">
                <!-- Recent Tickets -->
                <div class="card col-2">
                    <div class="card-header">
                        <h3>🎫 Tiket Terbaru</h3>
                        <a href="tickets.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                    </div>
                    <div class="table-responsive">
                    <table>
                        <thead><tr><th>Kode</th><th>Pelapor</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php while ($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><span class="ticket-code"><?= htmlspecialchars($row['kode_ticket']) ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($row['nama_pelapor']) ?></strong>
                                <div class="sub">📧 <?= htmlspecialchars($row['email']) ?></div>
                                <div class="sub">📱 <?= htmlspecialchars($row['no_telpon']) ?></div>
                            </td>
                            <td><?= htmlspecialchars(substr($row['judul'],0,40)) ?><?= strlen($row['judul'])>40?'...':'' ?></td>
                            <td><?= htmlspecialchars($row['nama_kategori'] ?? '—') ?></td>
                            <td><?= badgePrioritas($row['prioritas']) ?></td>
                            <td><?= badgeStatus($row['status']) ?></td>
                            <td><?= date('d M H:i', strtotime($row['created_at'])) ?></td>
                            <td>
                                <a href="ticket_detail.php?uid=<?= urlencode($row['uid']) ?>" class="btn btn-sm btn-outline">👁 Detail</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Kategori Chart -->
                <div class="card">
                    <div class="card-header"><h3>🏷️ Tiket per Kategori</h3></div>
                    <div class="card-body">
                        <?php
                        $colors = ['pb-blue','pb-green','pb-orange','pb-red'];
                        $catArr = [];
                        while ($c = $catStats->fetch_assoc()) $catArr[] = $c;
                        $maxC = max(1, ...array_column($catArr,'total'));
                        foreach ($catArr as $i => $c):
                            $pct = round($c['total']/$maxC*100);
                        ?>
                        <div style="margin-bottom:14px">
                            <div class="flex-between" style="margin-bottom:4px">
                                <span style="font-size:.8rem;font-weight:500"><?= htmlspecialchars($c['nama_kategori']) ?></span>
                                <span style="font-size:.76rem;color:var(--text-muted)"><?= $c['total'] ?> tiket</span>
                            </div>
                            <div class="progress"><div class="progress-bar <?= $colors[$i%4] ?>" style="width:<?= $pct ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Aksi Cepat -->
                <div class="card">
                    <div class="card-header"><h3>⚡ Aksi Cepat</h3></div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:9px">
                        <a href="tickets.php?status=Open" class="btn btn-primary">📬 Tangani Tiket Open (<?= $open ?>)</a>
                        <a href="tickets.php?status=Waiting+Confirm" class="btn btn-outline">🕐 Waiting Confirm (<?= $waiting ?>)</a>
                        <a href="tickets.php?prioritas=High" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger)">🔴 High Priority (<?= $high ?>)</a>
                        <a href="notifikasi.php" class="btn btn-outline">🔔 Notifikasi (<?= $unread ?> baru)</a>
                        <hr class="divider">
                        <a href="admins.php" class="btn btn-outline">👤 Kelola Admin</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function toggleNotif() {
    const d = document.getElementById('notifDropdown');
    d.classList.toggle('open');
    // Mark as read
    if (d.classList.contains('open')) {
        fetch('mark_notif_read.php', {method:'POST'});
        setTimeout(() => { document.querySelector('.notif-count') && (document.querySelector('.notif-count').style.display='none'); }, 300);
    }
}
document.addEventListener('click', e => {
    if (!document.getElementById('notifBtn').contains(e.target) &&
        !document.getElementById('notifDropdown').contains(e.target)) {
        document.getElementById('notifDropdown').classList.remove('open');
    }
});

// Auto refresh every 30s for new notifs
setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
