<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Allow both admin and teknisi
requireLogin('../index.php');
if (!in_array($_SESSION['role'], ['admin','teknisi'])) {
    header('Location: ../user/dashboard.php');
    exit();
}

$user = getCurrentUser();
$role = $user['role'];
$uid  = $user['id'];

// ── Stats ──────────────────────────────────────────────────
$totalTickets   = (int)$conn->query("SELECT COUNT(*) c FROM tickets")->fetch_assoc()['c'];
$totalOpen      = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Open'")->fetch_assoc()['c'];
$totalProgress  = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='On Progress'")->fetch_assoc()['c'];
$totalSolved    = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Solved'")->fetch_assoc()['c'];
$totalClosed    = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Closed'")->fetch_assoc()['c'];
$totalReopen    = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Re-Open'")->fetch_assoc()['c'];
$totalUsers     = (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
$totalTeknisi   = (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='teknisi'")->fetch_assoc()['c'];

// High priority open
$highPriority   = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE prioritas='High' AND status NOT IN ('Solved','Closed')")->fetch_assoc()['c'];

// Teknisi: only assigned tickets
if ($role === 'teknisi') {
    $myTickets  = (int)$conn->query("SELECT COUNT(*) c FROM tickets WHERE id_teknisi=$uid AND status NOT IN('Closed')")->fetch_assoc()['c'];
}

// ── Recent 10 tickets ──────────────────────────────────────
$recentQ = $conn->query("
    SELECT t.*, k.nama_kategori, u.nama as user_nama, u2.nama as teknisi_nama
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN users u2 ON t.id_teknisi = u2.id_user
    ORDER BY t.created_at DESC
    LIMIT 10
");

// ── Tickets per category ───────────────────────────────────
$catQ = $conn->query("
    SELECT k.nama_kategori, COUNT(t.id_ticket) as total
    FROM kategori k
    LEFT JOIN tickets t ON k.id_kategori = t.id_kategori
    GROUP BY k.id_kategori
    ORDER BY total DESC
    LIMIT 6
");

// ── Top teknisi ─────────────────────────────────────────────
$teknisiQ = $conn->query("
    SELECT u.nama, COUNT(t.id_ticket) as handled,
           SUM(t.status='Solved') as solved
    FROM users u
    LEFT JOIN tickets t ON u.id_user = t.id_teknisi
    WHERE u.role='teknisi'
    GROUP BY u.id_user
    ORDER BY handled DESC
    LIMIT 5
");

// ── Recent logs ─────────────────────────────────────────────
$logsQ = $conn->query("
    SELECT tl.*, u.nama as user_nama, t.kode_ticket, t.judul
    FROM ticket_logs tl
    JOIN users u ON tl.id_user = u.id_user
    JOIN tickets t ON tl.id_ticket = t.id_ticket
    ORDER BY tl.created_at DESC
    LIMIT 8
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
<title>Dashboard <?= ucfirst($role) ?> — Helpdesk IT NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.admin-header-banner {
    background: linear-gradient(135deg, #002855 0%, #003d7a 60%, #1565c0 100%);
    border-radius: var(--radius);
    padding: 26px 28px;
    margin-bottom: 24px;
    color: #fff;
    display: flex; align-items: center;
    justify-content: space-between; gap: 16px;
    box-shadow: 0 4px 24px rgba(0,40,85,.35);
    position: relative;
    overflow: hidden;
}
.admin-header-banner::before {
    content: '';
    position: absolute; right: -20px; top: -30px;
    width: 180px; height: 180px;
    background: rgba(255,255,255,.05);
    border-radius: 50%;
}
.admin-header-banner::after {
    content: '';
    position: absolute; right: 60px; bottom: -50px;
    width: 120px; height: 120px;
    background: rgba(255,255,255,.04);
    border-radius: 50%;
}
.admin-header-banner .brand { display: flex; align-items: center; gap: 14px; }
.admin-header-banner img { width: 56px; height: 56px; border-radius: 50%; border: 2px solid var(--accent); }
.admin-header-banner h2 { font-size: 1.15rem; font-weight: 700; }
.admin-header-banner p  { font-size: .8rem; opacity: .8; margin-top: 4px; }
.banner-stats { display: flex; gap: 24px; position: relative; z-index: 1; }
.banner-stat  { text-align: center; }
.banner-stat .val { font-size: 1.6rem; font-weight: 700; color: var(--accent); line-height: 1; }
.banner-stat .lbl { font-size: .7rem; opacity: .7; margin-top: 2px; }

.alert-high {
    background: #fff0f0;
    border: 1px solid #fca5a5;
    border-left: 4px solid var(--danger);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    font-size: .82rem;
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 20px;
    color: var(--danger);
}

.user-tag {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .76rem; font-weight: 500;
    background: #f0f4ff;
    color: var(--primary);
    padding: 2px 8px;
    border-radius: 20px;
}
</style>
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-icon-btn" onclick="toggleSidebar()" id="menuBtn" style="display:none">☰</button>
                <div>
                    <div class="topbar-title">Dashboard <?= ucfirst($role) ?></div>
                    <div class="topbar-subtitle"><?= date('l, d F Y') ?></div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-search">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Cari tiket...">
                </div>
                <div class="topbar-icon-btn" title="Notifikasi">
                    🔔 <?php if ($totalOpen > 0): ?><span class="notif-dot"></span><?php endif; ?>
                </div>
                <?php if ($role === 'admin'): ?>
                <a href="users.php" class="btn btn-accent btn-sm">+ Tambah User</a>
                <?php endif; ?>
            </div>
        </header>

        <main class="page-content">

            <!-- ── High Priority Alert ── -->
            <?php if ($highPriority > 0): ?>
            <div class="alert-high">
                🚨 <strong><?= $highPriority ?> tiket prioritas HIGH</strong> belum terselesaikan — perlu perhatian segera!
                <a href="tickets.php?prioritas=High" style="margin-left:auto;font-weight:600;text-decoration:underline">Lihat →</a>
            </div>
            <?php endif; ?>

            <!-- ── Banner Header ── -->
            <div class="admin-header-banner">
                <div class="brand">
                    <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj" alt="NSC">
                    <div>
                        <h2><?= $greeting ?>, <?= htmlspecialchars($user['nama']) ?>!</h2>
                        <p>Politeknik NSC Surabaya — Sistem Helpdesk IT Terpadu</p>
                    </div>
                </div>
                <div class="banner-stats">
                    <div class="banner-stat">
                        <div class="val"><?= $totalTickets ?></div>
                        <div class="lbl">Total Tiket</div>
                    </div>
                    <div class="banner-stat">
                        <div class="val"><?= $totalOpen ?></div>
                        <div class="lbl">Open</div>
                    </div>
                    <div class="banner-stat">
                        <div class="val"><?= $totalProgress ?></div>
                        <div class="lbl">Progress</div>
                    </div>
                    <div class="banner-stat">
                        <div class="val"><?= $totalSolved ?></div>
                        <div class="lbl">Solved</div>
                    </div>
                </div>
            </div>

            <!-- ── Stats Grid ── -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">🎫</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalTickets ?></div>
                        <div class="label">Total Semua Tiket</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">📬</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalOpen ?></div>
                        <div class="label">Tiket Open</div>
                        <div class="trend trend-down">⚠ Menunggu penanganan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">⚙️</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalProgress ?></div>
                        <div class="label">On Progress</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalSolved ?></div>
                        <div class="label">Solved</div>
                        <div class="trend trend-up">✓ Terselesaikan</div>
                    </div>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="stat-card">
                    <div class="stat-icon blue">👤</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalUsers ?></div>
                        <div class="label">Total Pengguna</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon teal">🔧</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalTeknisi ?></div>
                        <div class="label">Teknisi Aktif</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="stat-card">
                    <div class="stat-icon teal">🔧</div>
                    <div class="stat-info">
                        <div class="value"><?= $myTickets ?? 0 ?></div>
                        <div class="label">Tiket Saya Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">🔄</div>
                    <div class="stat-info">
                        <div class="value"><?= $totalReopen ?></div>
                        <div class="label">Re-Open</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Main Grid ── -->
            <div class="grid-2" style="margin-bottom:20px">

                <!-- Recent Tickets Table -->
                <div class="card col-span-2">
                    <div class="card-header">
                        <h3>🎫 Tiket Terbaru</h3>
                        <div class="flex-gap">
                            <a href="tickets.php?status=Open" class="btn btn-sm btn-outline">Open (<?= $totalOpen ?>)</a>
                            <a href="tickets.php" class="btn btn-sm btn-primary">Semua Tiket</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Judul</th>
                                    <th>Pengguna</th>
                                    <th>Kategori</th>
                                    <th>Teknisi</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = $recentQ->fetch_assoc()): ?>
                            <tr>
                                <td><span class="ticket-code"><?= htmlspecialchars($row['kode_ticket']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars(substr($row['judul'],0,40)) ?><?= strlen($row['judul'])>40?'...':'' ?></strong>
                                    <?php if ($row['lokasi']): ?>
                                    <div class="sub">📍 <?= htmlspecialchars($row['lokasi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="user-tag">👤 <?= htmlspecialchars($row['user_nama']) ?></span></td>
                                <td><?= htmlspecialchars($row['nama_kategori'] ?? '—') ?></td>
                                <td>
                                    <?php if ($row['teknisi_nama']): ?>
                                    <span class="user-tag" style="background:#e0f2f1;color:#00695c">🔧 <?= htmlspecialchars($row['teknisi_nama']) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">— Belum ditugaskan</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= badgePrioritas($row['prioritas']) ?></td>
                                <td><?= badgeStatus($row['status']) ?></td>
                                <td>
                                    <?= date('d M Y', strtotime($row['created_at'])) ?>
                                    <div class="sub"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <a href="ticket_detail.php?id=<?= $row['id_ticket'] ?>" class="btn btn-sm btn-outline btn-icon">👁</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid-2" style="gap:20px">
                <!-- Categories -->
                <div class="card">
                    <div class="card-header">
                        <h3>🏷️ Tiket per Kategori</h3>
                        <?php if ($role==='admin'): ?>
                        <a href="kategori.php" class="btn btn-sm btn-outline">Kelola</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php
                        $maxCat = 1;
                        $catRows = [];
                        while ($row = $catQ->fetch_assoc()) { $catRows[] = $row; if ($row['total'] > $maxCat) $maxCat = $row['total']; }
                        $colors = ['blue','green','orange','red','purple','teal'];
                        foreach ($catRows as $i => $row):
                            $pct = round($row['total']/$maxCat*100);
                            $c = $colors[$i % count($colors)];
                        ?>
                        <div style="margin-bottom:14px">
                            <div class="flex-between" style="margin-bottom:5px">
                                <span style="font-size:.8rem;font-weight:500"><?= htmlspecialchars($row['nama_kategori']) ?></span>
                                <span style="font-size:.77rem;color:var(--text-muted)"><?= $row['total'] ?> tiket</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?= $c ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($catRows)): ?>
                        <p class="text-muted" style="text-align:center;padding:20px 0">Belum ada data</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right column: Teknisi performance + Activity log -->
                <div style="display:flex;flex-direction:column;gap:20px">

                <?php if ($role === 'admin'): ?>
                <!-- Top Teknisi -->
                <div class="card">
                    <div class="card-header">
                        <h3>🔧 Performa Teknisi</h3>
                        <a href="users.php?role=teknisi" class="btn btn-sm btn-outline">Lihat</a>
                    </div>
                    <div class="card-body" style="padding:0">
                        <table>
                            <thead>
                                <tr>
                                    <th>Teknisi</th>
                                    <th>Ditangani</th>
                                    <th>Solved</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = $teknisiQ->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div class="sidebar-avatar" style="width:28px;height:28px;font-size:.7rem">
                                            <?= strtoupper(substr($row['nama'],0,1)) ?>
                                        </div>
                                        <?= htmlspecialchars($row['nama']) ?>
                                    </div>
                                </td>
                                <td><strong><?= $row['handled'] ?></strong></td>
                                <td><?= badgeStatus('Solved') ?> <?= $row['solved'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3>📋 Aktivitas Terbaru</h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                        <?php while ($log = $logsQ->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <strong><?= htmlspecialchars($log['user_nama']) ?></strong> —
                                <span class="ticket-code" style="font-size:.7rem"><?= htmlspecialchars($log['kode_ticket']) ?></span><br>
                                <?= htmlspecialchars(substr($log['keterangan'],0,80)) ?><?= strlen($log['keterangan'])>80?'...':'' ?>
                                <div class="meta">
                                    <?= date('d M Y H:i', strtotime($log['created_at'])) ?>
                                    <?php if ($log['status']): ?> · <?= badgeStatus($log['status']) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php
                        // Check if empty
                        $checkLogs = $conn->query("SELECT COUNT(*) c FROM ticket_logs");
                        if ((int)$checkLogs->fetch_assoc()['c'] === 0): ?>
                        <p class="text-muted" style="text-align:center;padding:10px 0">Belum ada aktivitas</p>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                </div><!-- end right col -->
            </div>

            <!-- ── Status Summary ── -->
            <div class="card" style="margin-top:20px">
                <div class="card-header">
                    <h3>📊 Ringkasan Status Tiket</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid" style="margin-bottom:0">
                        <?php
                        $statusInfo = [
                            ['Open',        $totalOpen,     'orange', '📬'],
                            ['On Progress', $totalProgress, 'blue',   '⚙️'],
                            ['Solved',      $totalSolved,   'green',  '✅'],
                            ['Closed',      $totalClosed,   'teal',   '🔒'],
                            ['Re-Open',     $totalReopen,   'red',    '🔄'],
                        ];
                        foreach ($statusInfo as [$s,$v,$c,$icon]): ?>
                        <a href="tickets.php?status=<?= urlencode($s) ?>" style="text-decoration:none">
                            <div class="stat-card">
                                <div class="stat-icon <?= $c ?>"><?= $icon ?></div>
                                <div class="stat-info">
                                    <div class="value"><?= $v ?></div>
                                    <div class="label"><?= $s ?></div>
                                    <?php $pct = $totalTickets > 0 ? round($v/$totalTickets*100) : 0; ?>
                                    <div class="trend"><?= $pct ?>% dari total</div>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
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
