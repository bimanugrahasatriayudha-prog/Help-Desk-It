<?php
$user    = getCurrentUser();
$initials = strtoupper(substr($user['nama'], 0, 1));
$current = basename($_SERVER['PHP_SELF'], '.php');

// Count open tickets
$open = 0;
if (isset($conn)) {
    $r = $conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Open'");
    $open = (int)$r->fetch_assoc()['c'];
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logo.png"
             onerror="this.src='https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj'"
             alt="NSC Logo">
        <div class="sidebar-brand-text">
            <h2>Politeknik NSC</h2>
            <p>Helpdesk Admin</p>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $initials ?></div>
        <div class="sidebar-user-info">
            <div class="name"><?= htmlspecialchars($user['nama']) ?></div>
            <span class="role"><?= ucfirst($user['role']) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Dashboard</div>
        <a href="dashboard.php" class="<?= $current==='dashboard'?'active':'' ?>">
            <span class="nav-icon">🏠</span> Dashboard
        </a>

        <div class="nav-section-label">Manajemen Tiket</div>
        <a href="tickets.php" class="<?= $current==='tickets'?'active':'' ?>">
            <span class="nav-icon">🎫</span> Semua Tiket
            <?php if ($open > 0): ?><span class="nav-badge"><?= $open ?></span><?php endif; ?>
        </a>
        <a href="tickets.php?status=Open" class="<?= ($current==='tickets' && ($_GET['status']??'')=='Open')?'active':'' ?>">
            <span class="nav-icon">📬</span> Tiket Open
        </a>
        <a href="tickets.php?status=On+Progress" class="">
            <span class="nav-icon">⚙️</span> On Progress
        </a>

        <div class="nav-section-label">Master Data</div>
        <a href="users.php" class="<?= $current==='users'?'active':'' ?>">
            <span class="nav-icon">👥</span> Pengguna
        </a>
        <a href="kategori.php" class="<?= $current==='kategori'?'active':'' ?>">
            <span class="nav-icon">🏷️</span> Kategori
        </a>

        <?php if ($user['role'] === 'admin'): ?>
        <div class="nav-section-label">Laporan</div>
        <a href="reports.php" class="<?= $current==='reports'?'active':'' ?>">
            <span class="nav-icon">📊</span> Laporan & Statistik
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../includes/logout.php">
            <span>🚪</span> Keluar
        </a>
    </div>
</aside>
