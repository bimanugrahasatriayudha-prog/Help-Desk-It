<?php
$user = getCurrentUser();
$initials = strtoupper(substr($user['nama'], 0, 1));
$current = basename($_SERVER['PHP_SELF'], '.php');

// Count open tickets for this user
$open = 0;
if (isset($conn)) {
    $r = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE id_user={$user['id']} AND status NOT IN ('Closed')");
    $row = $r->fetch_assoc();
    $open = $row['c'];
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logo.png"
             onerror="this.src='https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj'"
             alt="NSC Logo">
        <div class="sidebar-brand-text">
            <h2>Politeknik NSC</h2>
            <p>Helpdesk IT</p>
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
        <div class="nav-section-label">Menu Utama</div>
        <a href="dashboard.php" class="<?= $current==='dashboard'?'active':'' ?>">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a href="tickets.php" class="<?= $current==='tickets'?'active':'' ?>">
            <span class="nav-icon">🎫</span> Tiket Saya
            <?php if ($open > 0): ?><span class="nav-badge"><?= $open ?></span><?php endif; ?>
        </a>
        <a href="create_ticket.php" class="<?= $current==='create_ticket'?'active':'' ?>">
            <span class="nav-icon">➕</span> Buat Tiket
        </a>
        <div class="nav-section-label" style="margin-top:8px;">Lainnya</div>
        <a href="profile.php" class="<?= $current==='profile'?'active':'' ?>">
            <span class="nav-icon">👤</span> Profil Saya
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../includes/logout.php">
            <span>🚪</span> Keluar
        </a>
    </div>
</aside>
