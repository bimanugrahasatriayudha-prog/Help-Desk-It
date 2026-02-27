<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (isAdminLoggedIn()) { header('Location: dashboard'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $hash = md5($password);
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email=? AND password=?");
        $stmt->bind_param('ss', $email, $hash);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin) {
            $_SESSION['admin_uid']   = $admin['uid'];
            $_SESSION['admin_nama']  = $admin['nama'];
            $_SESSION['admin_email'] = $admin['email'];
            header('Location: dashboard');
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
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login Admin — Helpdesk NSC</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.input-icon { position:relative; }
.input-icon .ico { position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#b0b8c4;pointer-events:none; }
.input-icon .form-control { padding-left:34px; }
</style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj" alt="NSC" class="login-logo">
            <h1>Politeknik NSC Surabaya</h1>
            <p>Panel Admin — Helpdesk IT</p>
        </div>
        <div class="login-body">
            <p style="font-size:.85rem;font-weight:600;margin-bottom:16px">Masuk sebagai Administrator</p>
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-icon">
                        <span class="ico">✉</span>
                        <input type="email" name="email" class="form-control" placeholder="admin@nsc.ac.id" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-icon">
                        <span class="ico">🔒</span>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Masuk ke Dashboard</button>
            </form>
            <p class="login-footer-text">© <?= date('Y') ?> Politeknik NSC Surabaya</p>
        </div>
    </div>
</div>
</body>
</html>
