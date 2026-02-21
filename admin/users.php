<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('admin', '../index.php');
$user = getCurrentUser();

$success = $error = '';

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nama     = trim($_POST['nama'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';

    if ($nama && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (nama,email,password,role) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $nama, $email, $hash, $role);
        $stmt->execute() ? ($success = "Pengguna berhasil ditambahkan.") : ($error = "Gagal: " . $conn->error);
    } else {
        $error = "Harap isi semua field.";
    }
}

// Delete
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    if ($did !== (int)$user['id']) {
        $conn->query("DELETE FROM users WHERE id_user=$did");
        $success = "Pengguna dihapus.";
    } else {
        $error = "Anda tidak dapat menghapus akun sendiri.";
    }
}

$roleF = $_GET['role'] ?? '';
$where = $roleF ? "WHERE role='$roleF'" : '';
$users = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manajemen Pengguna — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">Manajemen Pengguna</div>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a>
            </div>
        </header>
        <main class="page-content">
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="grid-2">
                <!-- User list -->
                <div class="card col-span-2">
                    <div class="card-header">
                        <h3>👥 Daftar Pengguna</h3>
                        <div class="flex-gap">
                            <a href="users.php" class="btn btn-sm btn-outline <?= !$roleF?'btn-primary':'' ?>">Semua</a>
                            <a href="users.php?role=user" class="btn btn-sm btn-outline">User</a>
                            <a href="users.php?role=teknisi" class="btn btn-sm btn-outline">Teknisi</a>
                            <a href="users.php?role=admin" class="btn btn-sm btn-outline">Admin</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>#</th><th>Nama</th><th>Email</th><th>Role</th><th>Bergabung</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                        <?php $no=1; while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="sidebar-avatar" style="width:30px;height:30px;font-size:.75rem;flex-shrink:0">
                                        <?= strtoupper(substr($u['nama'],0,1)) ?>
                                    </div>
                                    <?= htmlspecialchars($u['nama']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['id_user'] !== (int)$user['id']): ?>
                                <a href="users.php?delete=<?= $u['id_user'] ?>"
                                   onclick="return confirm('Hapus pengguna ini?')"
                                   class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger)">🗑️</a>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Add User Form -->
                <div class="card col-span-2" style="max-width:500px">
                    <div class="card-header"><h3>➕ Tambah Pengguna Baru</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama" class="form-control" placeholder="Nama lengkap" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="email@nsc.ac.id" required>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                                <div class="form-group">
                                    <label class="form-label">Password <span class="required">*</span></label>
                                    <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Role <span class="required">*</span></label>
                                    <select name="role" class="form-control">
                                        <option value="user">User</option>
                                        <option value="teknisi">Teknisi</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                                ➕ Tambah Pengguna
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
