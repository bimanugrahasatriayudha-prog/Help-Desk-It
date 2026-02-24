<?php
$admin   = getAdmin();
$current = basename($_SERVER['PHP_SELF'], '.php');

$openCount = 0;
$unreadNotif = 0;
if (isset($conn)) {
    $r = $conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Open'");
    $openCount = (int)$r->fetch_assoc()['c'];
    $uid_esc = $conn->real_escape_string($admin['uid']);
    $r2 = $conn->query("SELECT COUNT(*) c FROM notifikasi WHERE uid_admin='$uid_esc' AND is_read=0");
    $unreadNotif = (int)$r2->fetch_assoc()['c'];
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logo.png"
             onerror="this.src='https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj'"
             alt="NSC">
        <div>
            <h2>Politeknik NSC</h2>
            <p>Helpdesk IT Admin</p>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($admin['nama'],0,1)) ?></div>
        <div class="sidebar-user-info">
            <div class="name"><?= htmlspecialchars($admin['nama']) ?></div>
            <div class="role">Administrator</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a href="dashboard.php" class="<?= $current==='dashboard'?'active':'' ?>">
            <span>🏠</span> Dashboard
        </a>
        <a href="tickets.php" class="<?= $current==='tickets'?'active':'' ?>">
            <span>🎫</span> Semua Tiket
            <?php if ($openCount > 0): ?><span class="nav-badge"><?= $openCount ?></span><?php endif; ?>
        </a>
        <a href="tickets.php?status=Open" class="">
            <span>📬</span> Tiket Open
        </a>
        <a href="tickets.php?status=Waiting+Confirm" class="">
            <span>🕐</span> Waiting Confirm
        </a>
        <div class="nav-label" style="margin-top:6px">Master</div>
        <a href="admins.php" class="<?= $current==='admins'?'active':'' ?>">
            <span>👤</span> Kelola Admin
        </a>
        <a href="kategori.php" class="<?= $current==='kategori'?'active':'' ?>">
            <span>🏷️</span> Kategori
        </a>
        <div class="nav-label" style="margin-top:6px">Notifikasi</div>
        <a href="notifikasi.php" class="<?= $current==='notifikasi'?'active':'' ?>">
            <span>🔔</span> Notifikasi
            <?php if ($unreadNotif > 0): ?><span class="nav-badge"><?= $unreadNotif ?></span><?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../index.php" target="_blank"><span>🌐</span> Lihat Form User</a>
        <a href="logout.php"><span>🚪</span> Keluar</a>
    </div>
</aside>
