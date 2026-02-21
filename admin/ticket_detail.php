<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin('../index.php');
if (!in_array($_SESSION['role'], ['admin','teknisi'])) {
    header('Location: ../user/dashboard.php'); exit();
}
$user = getCurrentUser();
$id   = (int)($_GET['id'] ?? 0);

$ticketQ = $conn->query("
    SELECT t.*, k.nama_kategori, u.nama as user_nama, u.email as user_email, u2.nama as teknisi_nama
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN users u2 ON t.id_teknisi = u2.id_user
    WHERE t.id_ticket = $id
");
$ticket = $ticketQ->fetch_assoc();
if (!$ticket) { echo "<p>Tiket tidak ditemukan.</p>"; exit(); }

$success = $error = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus   = $_POST['status'] ?? $ticket['status'];
    $newTeknisi  = (int)($_POST['id_teknisi'] ?? 0) ?: null;
    $keterangan  = trim($_POST['keterangan'] ?? '');

    $nullTeknisi = $newTeknisi ? $newTeknisi : 'NULL';
    $conn->query("UPDATE tickets SET status='$newStatus', id_teknisi=" . ($newTeknisi ?: 'NULL') . ", updated_at=NOW() WHERE id_ticket=$id");

    if ($keterangan) {
        $stmt = $conn->prepare("INSERT INTO ticket_logs (id_ticket,id_user,keterangan,status) VALUES (?,?,?,?)");
        $stmt->bind_param('iiss', $id, $user['id'], $keterangan, $newStatus);
        $stmt->execute();
    }
    $success = "Tiket berhasil diperbarui.";
    header("Location: ticket_detail.php?id=$id&updated=1");
    exit();
}

$teknisiList = $conn->query("SELECT id_user, nama FROM users WHERE role='teknisi' ORDER BY nama");
$logs = $conn->query("
    SELECT tl.*, u.nama as user_nama
    FROM ticket_logs tl
    JOIN users u ON tl.id_user = u.id_user
    WHERE tl.id_ticket = $id
    ORDER BY tl.created_at ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Detail Tiket <?= htmlspecialchars($ticket['kode_ticket']) ?> — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">Detail Tiket</div>
            </div>
            <div class="topbar-right">
                <a href="tickets.php" class="btn btn-outline btn-sm">← Kembali</a>
            </div>
        </header>
        <main class="page-content">
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">✅ Tiket berhasil diperbarui.</div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- Ticket Info -->
                <div>
                    <div class="card" style="margin-bottom:20px">
                        <div class="card-header">
                            <h3><?= htmlspecialchars($ticket['kode_ticket']) ?> — <?= htmlspecialchars($ticket['judul']) ?></h3>
                        </div>
                        <div class="card-body">
                            <table style="width:100%;font-size:.83rem">
                                <tr><td style="color:var(--text-muted);padding:6px 0;width:130px">Status</td><td><?= badgeStatus($ticket['status']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Prioritas</td><td><?= badgePrioritas($ticket['prioritas']) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Kategori</td><td><?= htmlspecialchars($ticket['nama_kategori'] ?? '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Pengguna</td><td>👤 <?= htmlspecialchars($ticket['user_nama']) ?> <span class="sub">(<?= htmlspecialchars($ticket['user_email']) ?>)</span></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Teknisi</td><td><?= $ticket['teknisi_nama'] ? '🔧 '.htmlspecialchars($ticket['teknisi_nama']) : '<span class="text-muted">Belum ditugaskan</span>' ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Lokasi</td><td><?= htmlspecialchars($ticket['lokasi'] ?: '—') ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Dibuat</td><td><?= date('d M Y H:i', strtotime($ticket['created_at'])) ?></td></tr>
                                <tr><td style="color:var(--text-muted);padding:6px 0">Diperbarui</td><td><?= date('d M Y H:i', strtotime($ticket['updated_at'])) ?></td></tr>
                            </table>
                            <hr class="divider">
                            <div style="font-size:.84rem;line-height:1.8;color:var(--text)">
                                <strong>Deskripsi:</strong><br>
                                <?= nl2br(htmlspecialchars($ticket['deskripsi'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Update Form -->
                    <div class="card">
                        <div class="card-header"><h3>✏️ Update Tiket</h3></div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <?php foreach (['Open','On Progress','Solved','Closed','Re-Open'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tugaskan ke Teknisi</label>
                                    <select name="id_teknisi" class="form-control">
                                        <option value="">— Belum ditugaskan —</option>
                                        <?php while ($tek = $teknisiList->fetch_assoc()): ?>
                                        <option value="<?= $tek['id_user'] ?>" <?= $ticket['id_teknisi']==$tek['id_user']?'selected':'' ?>>
                                            <?= htmlspecialchars($tek['nama']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Keterangan / Catatan</label>
                                    <textarea name="keterangan" class="form-control" rows="3" placeholder="Tambahkan keterangan update..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                                    💾 Simpan Perubahan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Timeline Logs -->
                <div class="card">
                    <div class="card-header"><h3>📋 Log Aktivitas</h3></div>
                    <div class="card-body">
                        <div class="timeline">
                        <?php
                        $logArr = [];
                        while ($log = $logs->fetch_assoc()) $logArr[] = $log;
                        if (empty($logArr)): ?>
                            <p class="text-muted" style="text-align:center;padding:20px 0">Belum ada log aktivitas</p>
                        <?php else:
                            foreach ($logArr as $log): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <strong><?= htmlspecialchars($log['user_nama']) ?></strong>
                                <?php if ($log['status']): ?> — <?= badgeStatus($log['status']) ?><?php endif; ?>
                                <div style="margin-top:4px;font-size:.8rem"><?= nl2br(htmlspecialchars($log['keterangan'])) ?></div>
                                <div class="meta"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
