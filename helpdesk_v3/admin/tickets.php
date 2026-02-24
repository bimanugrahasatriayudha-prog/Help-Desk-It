<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
autoCloseTickets($conn);

$statusF   = $_GET['status'] ?? '';
$prioritasF= $_GET['prioritas'] ?? '';
$katF      = $_GET['kategori'] ?? '';

$where = '1=1';
if ($statusF)    $where .= " AND t.status='" . $conn->real_escape_string($statusF) . "'";
if ($prioritasF) $where .= " AND t.prioritas='" . $conn->real_escape_string($prioritasF) . "'";
if ($katF)       $where .= " AND k.nama_kategori='" . $conn->real_escape_string($katF) . "'";

$tickets = $conn->query("
    SELECT t.*, k.nama_kategori,
           (SELECT COUNT(*) FROM chats c WHERE c.uid_ticket=t.uid AND c.sender='user' AND c.dibaca=0) as unread_user
    FROM tickets t
    LEFT JOIN kategori k ON t.uid_kategori = k.uid
    WHERE $where
    ORDER BY
        FIELD(t.prioritas,'High','Medium','Low'),
        FIELD(t.status,'Open','On Progress','Waiting Confirm','Solved','Closed'),
        t.created_at DESC
");
$total = $tickets->num_rows;
$cats  = $conn->query("SELECT DISTINCT nama_kategori FROM kategori ORDER BY nama_kategori");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manajemen Tiket — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.filter-bar { background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
.filter-bar select { padding:7px 11px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem;color:var(--text);background:#fff; }
.unread-dot { display:inline-block;width:8px;height:8px;background:var(--danger);border-radius:50%;margin-left:4px;vertical-align:middle; }
</style>
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div>
                <div class="topbar-title">Manajemen Tiket</div>
                <div class="topbar-subtitle"><?= $total ?> tiket ditemukan</div>
            </div>
        </header>
        <main class="page-content">
            <form method="GET" class="filter-bar">
                <select name="status">
                    <option value="">Semua Status</option>
                    <?php foreach (['Open','On Progress','Waiting Confirm','Solved','Closed'] as $s): ?>
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
                    <?php while ($c = $cats->fetch_assoc()): ?>
                    <option value="<?= $c['nama_kategori'] ?>" <?= $katF===$c['nama_kategori']?'selected':'' ?>><?= $c['nama_kategori'] ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="tickets.php" class="btn btn-outline btn-sm">Reset</a>
            </form>

            <div class="card">
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>#</th><th>Kode</th><th>Pelapor</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Masuk</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php $no=1; while ($row = $tickets->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--text-muted)"><?= $no++ ?></td>
                        <td>
                            <span class="ticket-code"><?= htmlspecialchars($row['kode_ticket']) ?></span>
                            <?php if ($row['unread_user'] > 0): ?><span class="unread-dot" title="<?= $row['unread_user'] ?> pesan baru"></span><?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['nama_pelapor']) ?></strong>
                            <div class="sub">📧 <?= htmlspecialchars($row['email']) ?></div>
                            <div class="sub">📱 <?= htmlspecialchars($row['no_telpon']) ?></div>
                        </td>
                        <td>
                            <?= htmlspecialchars(substr($row['judul'],0,45)) ?><?= strlen($row['judul'])>45?'...':'' ?>
                            <?php if ($row['foto']): ?><div class="sub">📷 Ada foto</div><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['nama_kategori'] ?? '—') ?></td>
                        <td><?= badgePrioritas($row['prioritas']) ?></td>
                        <td><?= badgeStatus($row['status']) ?></td>
                        <td>
                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                            <div class="sub"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td>
                            <a href="ticket_detail.php?uid=<?= urlencode($row['uid']) ?>" class="btn btn-sm btn-primary">💬 Chat</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($total === 0): ?>
                    <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">📭 Tidak ada tiket</td></tr>
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
