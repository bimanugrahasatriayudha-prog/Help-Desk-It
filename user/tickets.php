<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('user', '../index.php');
$user = getCurrentUser();
$uid  = $user['id'];

$statusF = $_GET['status'] ?? '';
$where   = "t.id_user = $uid";
if ($statusF) $where .= " AND t.status='" . $conn->real_escape_string($statusF) . "'";

$tickets = $conn->query("
    SELECT t.*, k.nama_kategori, u2.nama as teknisi_nama
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    LEFT JOIN users u2 ON t.id_teknisi = u2.id_user
    WHERE $where
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tiket Saya — Helpdesk IT NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">Tiket Saya</div>
            </div>
            <div class="topbar-right">
                <a href="create_ticket.php" class="btn btn-accent btn-sm">+ Buat Tiket</a>
            </div>
        </header>
        <main class="page-content">
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
                <?php foreach ([''=>'Semua','Open'=>'Open','On Progress'=>'On Progress','Solved'=>'Solved','Closed'=>'Closed'] as $s=>$l): ?>
                <a href="tickets.php?status=<?= urlencode($s) ?>"
                   class="btn btn-sm <?= $statusF===$s?'btn-primary':'btn-outline' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($tickets->num_rows === 0): ?>
            <div class="card" style="text-align:center;padding:40px">
                <div style="font-size:3rem;margin-bottom:12px">📭</div>
                <p style="color:var(--text-muted)">Tidak ada tiket. <a href="create_ticket.php" style="color:var(--primary)">Buat tiket baru</a></p>
            </div>
            <?php else: ?>
            <?php while ($row = $tickets->fetch_assoc()): ?>
            <a href="ticket_detail.php?id=<?= $row['id_ticket'] ?>" style="text-decoration:none">
            <div class="ticket-card">
                <div class="ticket-card-top">
                    <div>
                        <span class="ticket-code"><?= htmlspecialchars($row['kode_ticket']) ?></span>
                        <div class="ticket-title"><?= htmlspecialchars($row['judul']) ?></div>
                    </div>
                    <div style="display:flex;gap:6px">
                        <?= badgePrioritas($row['prioritas']) ?>
                        <?= badgeStatus($row['status']) ?>
                    </div>
                </div>
                <div class="ticket-meta">
                    <span>🏷️ <?= htmlspecialchars($row['nama_kategori'] ?? '—') ?></span>
                    <?php if ($row['lokasi']): ?><span>📍 <?= htmlspecialchars($row['lokasi']) ?></span><?php endif; ?>
                    <span>🔧 <?= $row['teknisi_nama'] ? htmlspecialchars($row['teknisi_nama']) : 'Belum ditugaskan' ?></span>
                    <span>📅 <?= date('d M Y H:i', strtotime($row['created_at'])) ?></span>
                </div>
            </div>
            </a>
            <?php endwhile; ?>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
