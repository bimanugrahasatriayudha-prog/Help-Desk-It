<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teknisi' ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        // Cek password plain text (sesuai database)
        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            if ($user['role'] === 'admin' || $user['role'] === 'teknisi') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: user/dashboard.php');
            }
            exit();
        } else {
            $error = 'Email atau password salah.';
        }
    } else {
        $error = 'Harap isi semua field.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Helpdesk IT Politeknik NSC Surabaya</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.input-wrapper { position: relative; }
.input-wrapper .ico {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: #b0b8c4; font-size: .9rem; pointer-events: none;
}
.input-wrapper .form-control { padding-left: 36px; }
</style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj"
                 alt="NSC Logo" class="login-logo">
            <h1>Politeknik NSC Surabaya</h1>
            <p>Sistem Helpdesk IT Terpadu</p>
        </div>
        <div class="login-body">
            <h2>Selamat Datang 👋</h2>
            <p class="subtitle">Masuk ke akun Anda untuk melanjutkan</p>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="ico">✉</span>
                        <input type="email" name="email" class="form-control"
                               placeholder="nama@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="ico">🔒</span>
                        <input type="password" name="password" class="form-control"
                               placeholder="Masukkan password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;">
                    Masuk ke Dashboard
                </button>
            </form>

            <!-- Info akun demo -->
            <div style="margin-top:20px;background:#f7f9fc;border-radius:8px;padding:14px;font-size:.75rem;color:var(--text-muted)">
                <strong style="color:var(--text)">Akun tersedia:</strong><br><br>
                🔴 Admin &nbsp;&nbsp;: admin@gmail.com / <strong>admin123</strong><br>
                🔧 Teknisi : budi@gmail.com / <strong>teknisi123</strong><br>
                👤 User &nbsp;&nbsp;&nbsp;: user@gmail.com / <strong>user123</strong>
            </div>

            <p class="login-footer-text" style="margin-top:14px">
                © <?= date('Y') ?> Politeknik NSC Surabaya. All rights reserved.
            </p>
        </div>
    </div>
</div>
</body>
</html>
