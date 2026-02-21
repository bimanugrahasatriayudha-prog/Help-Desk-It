<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin($redirect = '../index.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit();
    }
}

function requireRole($role, $redirect = '../index.php') {
    requireLogin($redirect);
    if ($_SESSION['role'] !== $role) {
        header("Location: $redirect");
        exit();
    }
}

function getCurrentUser() {
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'nama'  => $_SESSION['nama'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role'  => $_SESSION['role'] ?? '',
    ];
}

function generateKodeTicket() {
    return 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

function badgeStatus($status) {
    $map = [
        'Open'        => 'badge-open',
        'On Progress' => 'badge-progress',
        'Solved'      => 'badge-solved',
        'Closed'      => 'badge-closed',
        'Re-Open'     => 'badge-reopen',
    ];
    $cls = $map[$status] ?? 'badge-open';
    return "<span class=\"badge $cls\">$status</span>";
}

function badgePrioritas($p) {
    $map = [
        'Low'    => 'badge-low',
        'Medium' => 'badge-medium',
        'High'   => 'badge-high',
    ];
    $cls = $map[$p] ?? 'badge-medium';
    return "<span class=\"badge $cls\">$p</span>";
}
?>
