<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('user', '../index.php');
$user = getCurrentUser();
$uid  = $user['id'];
$id   = (int)($_GET['id'] ?? 0);

$ticketQ = $conn->query("
    SELECT t.*, k.nama_kategori, u2.nama as teknisi_nama
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    LEFT JOIN users u2 ON t.id_teknisi = u2.id_user
    WHERE t.id_ticket = $id AND t.id_user = $uid
");
$ticket = $ticketQ->fetch_assoc();
if (!$ticket) { header('Location: tickets.php'); exit(); }

// Re-open request
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket['status'] === 'Closed') {
    $conn->query("UPDATE tickets SET status='Re-Open', updated_at=NOW() WHERE id_ticket=$id");
    $ket = "Pengguna meminta re-open tiket";
    $stmt = $conn->prepare("INSERT INTO ticket_logs (id_ticket,id_user,keterangan,status) VALUES (?,?,?,'Re-Open')");
    $stmt->bind_param('iis', $id, $uid, $ket);
    $stmt->execute();
    $success = "Permintaan re-open berhasil dikirim.";
    header("Location: ticket_detail.php?id=$id");
    exit();
}

$logs = $conn->query("
    SELECT tl.*, u.nama as user_nama FROM ticket_logs tl
    JOIN users u ON tl.id_user = u.id_user
    WHERE tl.id_ticket=$id ORDER BY tl.created_at ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($ticket['kode_ticket']) ?> — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title"><?= htmlspecialchars($ticket['kode_ticket']) ?></div>
            </div>
            <div class="topbar-right">
                <a href="tickets.php" class="btn btn-outline btn-sm">← Kembali</a>
            </div>
        </header>
        <main class="page-content">
            <div class="grid-2">
                <div>
                    <div class="card" style="margin-bottom:20px">
                        <div class="card-header">
                            <h3><?= htmlspecialchars($ticket['judul']) ?></h3>
                            <div style="display:flex;gap:6px">
                                <?= badgePrioritas($ticket['prioritas']) ?>
                                <?= badgeStatus($ticket['status']) ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table style="font-size:.83rem;width:100%">
                                <tr><td style="color:var(--text-muted);padding:5px 0;width:130px">Kategori</td><td><?= htmlspecialchars($ticket['nama_kategori'] ?? '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:5px 0">Lokasi</td><td><?= htmlspecialchars($ticket['lokasi'] ?: '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:5px 0">Teknisi</td><td><?= $ticket['teknisi_nama'] ? '🔧 '.htmlspecialchars($ticket['teknisi_nama']) : '<span class="text-muted">Belum ditugaskan</span>' ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:5px 0">Dibuat</td><td><?= date('d M Y H:i', strtotime($ticket['created_at'])) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:5px 0">Diperbarui</td><td><?= date('d M Y H:i', strtotime($ticket['updated_at'])) ?></td></tr>
                            </table>
                            <hr class="divider">
                            <p style="font-size:.84rem;line-height:1.8"><?= nl2br(htmlspecialchars($ticket['deskripsi'])) ?></p>
                            <?php if ($ticket['status'] === 'Closed'): ?>
                            <hr class="divider">
                            <form method="POST">
                                <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px">Masalah belum terselesaikan? Ajukan re-open.</p>
                                <button type="submit" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger)">
                                    🔄 Ajukan Re-Open
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>📋 Riwayat Aktivitas</h3></div>
                    <div class="card-body">
                        <div class="timeline">
                        <?php while ($log = $logs->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <strong><?= htmlspecialchars($log['user_nama']) ?></strong>
                                <?php if ($log['status']): ?> — <?= badgeStatus($log['status']) ?><?php endif; ?>
                                <div style="margin-top:3px;font-size:.8rem"><?= nl2br(htmlspecialchars($log['keterangan'])) ?></div>
                                <div class="meta"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
