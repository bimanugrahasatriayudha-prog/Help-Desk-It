<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('user', '../index.php');

$user = getCurrentUser();
$uid  = $user['id'];

// Stats
$stats = [];
foreach (['Open','On Progress','Solved','Closed','Re-Open'] as $s) {
    $r = $conn->query("SELECT COUNT(*) c FROM tickets WHERE id_user=$uid AND status='$s'");
    $stats[$s] = (int)$r->fetch_assoc()['c'];
}
$total = array_sum($stats);

// Recent tickets
$recentQ = $conn->query("
    SELECT t.id_ticket, t.kode_ticket, t.judul, t.lokasi,
           t.prioritas, t.status, t.created_at,
           k.nama_kategori, u2.nama AS teknisi_nama
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    LEFT JOIN users u2 ON t.id_teknisi = u2.id_user
    WHERE t.id_user = $uid
    ORDER BY t.created_at DESC
    LIMIT 5
");

// All my tickets
$allQ = $conn->query("
    SELECT t.id_ticket, t.kode_ticket, t.judul, t.lokasi,
           t.prioritas, t.status, t.created_at,
           k.nama_kategori
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    WHERE t.id_user = $uid
    ORDER BY t.created_at DESC
    LIMIT 20
");

$greeting = 'Selamat Datang';
$h = (int)date('H');
if ($h < 12) $greeting = 'Selamat Pagi';
elseif ($h < 15) $greeting = 'Selamat Siang';
elseif ($h < 18) $greeting = 'Selamat Sore';
else $greeting = 'Selamat Malam';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Helpdesk IT NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-icon-btn" onclick="toggleSidebar()" style="display:none" id="menuBtn">☰</button>
                <div>
                    <div class="topbar-title">Dashboard Pengguna</div>
                    <div class="topbar-subtitle"><?= date('l, d F Y') ?></div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-search">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Cari tiket...">
                </div>
                <div class="topbar-icon-btn" title="Notifikasi">
                    🔔 <span class="notif-dot"></span>
                </div>
                <a href="create_ticket.php" class="btn btn-accent btn-sm">+ Buat Tiket</a>
            </div>
        </header>

        <main class="page-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div>
                    <h2><?= $greeting ?>, <?= htmlspecialchars($user['nama']) ?>! 👋</h2>
                    <p>Selamat datang di sistem Helpdesk IT Politeknik NSC Surabaya. Ada <?= $stats['Open'] + $stats['On Progress'] ?> tiket aktif Anda.</p>
                </div>
                <div class="welcome-banner-img">🖥️</div>
            </div>

            <!-- Stat Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">🎫</div>
                    <div class="stat-info">
                        <div class="value"><?= $total ?></div>
                        <div class="label">Total Tiket</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">📬</div>
                    <div class="stat-info">
                        <div class="value"><?= $stats['Open'] ?></div>
                        <div class="label">Tiket Open</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">⚙️</div>
                    <div class="stat-info">
                        <div class="value"><?= $stats['On Progress'] ?></div>
                        <div class="label">On Progress</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-info">
                        <div class="value"><?= $stats['Solved'] ?></div>
                        <div class="label">Solved</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon teal">🔒</div>
                    <div class="stat-info">
                        <div class="value"><?= $stats['Closed'] ?></div>
                        <div class="label">Closed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">🔄</div>
                    <div class="stat-info">
                        <div class="value"><?= $stats['Re-Open'] ?></div>
                        <div class="label">Re-Open</div>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <!-- Recent Tickets -->
                <div class="card col-span-2">
                    <div class="card-header">
                        <h3>🎫 Tiket Terbaru Saya</h3>
                        <a href="tickets.php" class="btn btn-outline btn-sm">Lihat Semua</a>
                    </div>
                    <div class="card-body" style="padding:0">
                        <?php if ($total === 0): ?>
                            <div style="padding:32px;text-align:center;color:var(--text-muted)">
                                <div style="font-size:2.5rem;margin-bottom:10px">📭</div>
                                <p>Belum ada tiket. <a href="create_ticket.php" style="color:var(--primary)">Buat tiket pertama Anda</a></p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kode Tiket</th>
                                    <th>Judul</th>
                                    <th>Kategori</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $allQ2 = $conn->query("
                                SELECT t.id_ticket, t.kode_ticket, t.judul, t.lokasi,
                                       t.prioritas, t.status, t.created_at,
                                       k.nama_kategori
                                FROM tickets t
                                LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
                                WHERE t.id_user = $uid
                                ORDER BY t.created_at DESC
                                LIMIT 8
                            ");
                            while ($row = $allQ2->fetch_assoc()): ?>
                            <tr onclick="location='ticket_detail.php?id=<?= $row['id_ticket'] ?>'" style="cursor:pointer">
                                <td><span class="ticket-code"><?= htmlspecialchars($row['kode_ticket']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['judul']) ?></strong>
                                    <?php if ($row['lokasi']): ?>
                                    <div class="sub">📍 <?= htmlspecialchars($row['lokasi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                <td><?= badgePrioritas($row['prioritas']) ?></td>
                                <td><?= badgeStatus($row['status']) ?></td>
                                <td>
                                    <?= date('d M Y', strtotime($row['created_at'])) ?>
                                    <div class="sub"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Progress Chart -->
                <div class="card">
                    <div class="card-header"><h3>📊 Status Tiket</h3></div>
                    <div class="card-body">
                        <?php
                        $items = [
                            ['Open',        $stats['Open'],        'orange'],
                            ['On Progress', $stats['On Progress'], 'blue'],
                            ['Solved',      $stats['Solved'],      'green'],
                            ['Closed',      $stats['Closed'],      'purple'],
                            ['Re-Open',     $stats['Re-Open'],     'red'],
                        ];
                        foreach ($items as [$label, $val, $color]):
                            $pct = $total > 0 ? round($val/$total*100) : 0;
                        ?>
                        <div style="margin-bottom:14px">
                            <div class="flex-between" style="margin-bottom:5px">
                                <span style="font-size:.8rem;font-weight:500"><?= $label ?></span>
                                <span style="font-size:.78rem;color:var(--text-muted)"><?= $val ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?= $color ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header"><h3>⚡ Aksi Cepat</h3></div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                        <a href="create_ticket.php" class="btn btn-primary" style="justify-content:center">
                            ➕ Buat Tiket Baru
                        </a>
                        <a href="tickets.php?status=Open" class="btn btn-outline" style="justify-content:center">
                            📬 Lihat Tiket Open
                        </a>
                        <a href="tickets.php?status=On+Progress" class="btn btn-outline" style="justify-content:center">
                            ⚙️ Tiket On Progress
                        </a>
                        <a href="tickets.php" class="btn btn-outline" style="justify-content:center">
                            📋 Semua Tiket Saya
                        </a>
                        <hr class="divider">
                        <div style="background:#f7f9fc;border-radius:8px;padding:14px">
                            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted);margin-bottom:8px">ℹ️ INFO</div>
                            <div style="font-size:.78rem;color:var(--text-muted);line-height:1.7">
                                Jam Layanan: <strong>08.00–16.00 WIB</strong><br>
                                Kontak: <strong>helpdesk@nsc.ac.id</strong><br>
                                Telepon: <strong>(031) 123-4567</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
window.addEventListener('resize', function() {
    document.getElementById('menuBtn').style.display = window.innerWidth <= 900 ? 'flex' : 'none';
});
if (window.innerWidth <= 900) document.getElementById('menuBtn').style.display = 'flex';
</script>
</body>
</html>
