<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin('../index.php');
if (!in_array($_SESSION['role'], ['admin','teknisi'])) {
    header('Location: ../user/dashboard.php'); exit();
}
$user = getCurrentUser();

// Filters
$statusF   = $_GET['status'] ?? '';
$prioritasF= $_GET['prioritas'] ?? '';
$katF      = (int)($_GET['kategori'] ?? 0);
$search    = trim($_GET['q'] ?? '');

$where = '1=1';
if ($statusF)    $where .= " AND t.status='" . $conn->real_escape_string($statusF) . "'";
if ($prioritasF) $where .= " AND t.prioritas='" . $conn->real_escape_string($prioritasF) . "'";
if ($katF)       $where .= " AND t.id_kategori=$katF";
if ($search)     $where .= " AND (t.judul LIKE '%" . $conn->real_escape_string($search) . "%' OR t.kode_ticket LIKE '%" . $conn->real_escape_string($search) . "%')";

$tickets = $conn->query("
    SELECT t.*, k.nama_kategori, u.nama as user_nama, u2.nama as teknisi_nama
    FROM tickets t
    LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN users u2 ON t.id_teknisi = u2.id_user
    WHERE $where
    ORDER BY t.created_at DESC
");

$categories = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");
$total = $tickets->num_rows;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Tiket — Helpdesk IT NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.filter-bar {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.filter-bar select, .filter-bar input {
    padding: 7px 12px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: .82rem;
    color: var(--text);
    background: #fff;
}
.filter-bar select:focus, .filter-bar input:focus {
    outline: none; border-color: var(--primary);
}
</style>
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div>
                    <div class="topbar-title">Manajemen Tiket</div>
                    <div class="topbar-subtitle"><?= $total ?> tiket ditemukan</div>
                </div>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a>
            </div>
        </header>
        <main class="page-content">

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <input type="text" name="q" placeholder="🔍 Cari tiket..." value="<?= htmlspecialchars($search) ?>" style="min-width:200px">
                <select name="status">
                    <option value="">Semua Status</option>
                    <?php foreach (['Open','On Progress','Solved','Closed','Re-Open'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="prioritas">
                    <option value="">Semua Prioritas</option>
                    <?php foreach (['Low','Medium','High'] as $p): ?>
                    <option value="<?= $p ?>" <?= $prioritasF===$p?'selected':'' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= $cat['id_kategori'] ?>" <?= $katF===$cat['id_kategori']?'selected':'' ?>>
                        <?= htmlspecialchars($cat['nama_kategori']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="tickets.php" class="btn btn-outline btn-sm">Reset</a>
            </form>

            <div class="card">
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kode Tiket</th>
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
                    <?php
                    $no = 1;
                    while ($row = $tickets->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--text-muted)"><?= $no++ ?></td>
                        <td><span class="ticket-code"><?= htmlspecialchars($row['kode_ticket']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars(substr($row['judul'],0,45)) ?><?= strlen($row['judul'])>45?'...':'' ?></strong>
                            <?php if ($row['lokasi']): ?><div class="sub">📍 <?= htmlspecialchars($row['lokasi']) ?></div><?php endif; ?>
                        </td>
                        <td><span style="font-size:.78rem">👤 <?= htmlspecialchars($row['user_nama']) ?></span></td>
                        <td><span style="font-size:.78rem"><?= htmlspecialchars($row['nama_kategori'] ?? '—') ?></span></td>
                        <td>
                            <?php if ($row['teknisi_nama']): ?>
                            <span style="font-size:.78rem;color:var(--success)">🔧 <?= htmlspecialchars($row['teknisi_nama']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">— Belum</span>
                            <?php endif; ?>
                        </td>
                        <td><?= badgePrioritas($row['prioritas']) ?></td>
                        <td><?= badgeStatus($row['status']) ?></td>
                        <td>
                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                            <div class="sub"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td>
                            <div class="flex-gap">
                                <a href="ticket_detail.php?id=<?= $row['id_ticket'] ?>" class="btn btn-sm btn-outline btn-icon" title="Detail">👁</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($total === 0): ?>
                    <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted)">📭 Tidak ada tiket ditemukan</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
