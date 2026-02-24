<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
$admin  = getAdmin();
$success = $error = '';

// Add admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($nama && $email && $pass) {
        $uid  = generateUUID();
        $hash = md5($pass);
        $stmt = $conn->prepare("INSERT INTO admins (uid, nama, email, password) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $uid, $nama, $email, $hash);
        $stmt->execute() ? ($success = "Admin berhasil ditambahkan.") : ($error = "Gagal: email mungkin sudah digunakan.");
    } else { $error = "Isi semua field."; }
}

// Delete
if (isset($_GET['delete'])) {
    $duid = $conn->real_escape_string($_GET['delete']);
    if ($duid !== $admin['uid']) {
        $conn->query("DELETE FROM admins WHERE uid='$duid'");
        $success = "Admin dihapus.";
    } else { $error = "Tidak bisa menghapus akun sendiri."; }
}

$admins = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kelola Admin — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-title">👤 Kelola Admin</div>
        </header>
        <main class="page-content">
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="grid-2">
                <div class="card col-2">
                    <div class="card-header"><h3>Daftar Admin</h3></div>
                    <div class="table-responsive">
                    <table>
                        <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Bergabung</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php $no=1; while ($a = $admins->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="sidebar-avatar" style="width:28px;height:28px;font-size:.7rem"><?= strtoupper(substr($a['nama'],0,1)) ?></div>
                                    <?= htmlspecialchars($a['nama']) ?>
                                    <?php if ($a['uid'] === $admin['uid']): ?><span class="badge badge-open" style="font-size:.63rem">Anda</span><?php endif; ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                            <td>
                                <?php if ($a['uid'] !== $admin['uid']): ?>
                                <a href="admins.php?delete=<?= urlencode($a['uid']) ?>" onclick="return confirm('Hapus admin ini?')"
                                   class="btn btn-sm" style="color:var(--danger);border:1px solid var(--danger)">🗑️ Hapus</a>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="card" style="max-width:440px">
                    <div class="card-header"><h3>➕ Tambah Admin Baru</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                                <input type="text" name="nama" class="form-control" placeholder="Nama admin" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="req">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="admin@nsc.ac.id" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password <span class="req">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Password (akan di-MD5)" required>
                            </div>
                            <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:12px">🔒 Password otomatis dienkripsi dengan MD5</p>
                            <button type="submit" class="btn btn-primary btn-full">➕ Tambah Admin</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
