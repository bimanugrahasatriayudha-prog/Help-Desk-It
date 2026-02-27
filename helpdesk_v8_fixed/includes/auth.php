<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isAdminLoggedIn() {
    return isset($_SESSION['admin_uid']);
}

function requireAdmin($redirect = '../admin/login.php') {
    if (!isAdminLoggedIn()) {
        header("Location: $redirect");
        exit();
    }
}

function getAdmin() {
    return [
        'uid'   => $_SESSION['admin_uid']  ?? null,
        'nama'  => $_SESSION['admin_nama'] ?? '',
        'email' => $_SESSION['admin_email'] ?? '',
    ];
}

function badgeStatus($status) {
    $map = [
        'Open'             => ['badge-open',     '📬 Open'],
        'On Progress'      => ['badge-progress', '⚙️ On Progress'],
        'Solved'           => ['badge-solved',   '✅ Solved'],
        'Waiting Confirm'  => ['badge-waiting',  '🕐 Waiting Confirm'],
        'Closed'           => ['badge-closed',   '🔒 Closed'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-open', $status];
    return "<span class=\"badge $cls\">$label</span>";
}

function badgePrioritas($p) {
    $map = [
        'Low'    => ['badge-low',    '🟢 Low'],
        'Medium' => ['badge-medium', '🟡 Medium'],
        'High'   => ['badge-high',   '🔴 High'],
    ];
    [$cls, $label] = $map[$p] ?? ['badge-medium', $p];
    return "<span class=\"badge $cls\">$label</span>";
}

// Auto-close tickets that have been in Waiting Confirm > 5 days
function autoCloseTickets($conn) {
    $conn->query("
        UPDATE tickets
        SET status = 'Closed', confirmed_at = NOW()
        WHERE status = 'Waiting Confirm'
          AND auto_close_at IS NOT NULL
          AND auto_close_at <= NOW()
    ");

    // Insert system chat for auto-closed tickets
    $res = $conn->query("
        SELECT uid, kode_ticket FROM tickets
        WHERE status = 'Closed'
          AND confirmed_at IS NOT NULL
          AND uid NOT IN (
              SELECT uid_ticket FROM chats
              WHERE is_system = 1 AND pesan LIKE '%otomatis ditutup%'
          )
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cuid = generateUUID();
            $tid  = $conn->real_escape_string($row['uid']);
            $conn->query("INSERT INTO chats (uid, uid_ticket, sender, pesan, is_system)
                          VALUES ('$cuid', '$tid', 'admin',
                          'Tiket ini otomatis ditutup setelah 5 hari tanpa konfirmasi dari pelapor. Terima kasih telah menggunakan layanan Helpdesk IT NSC.', 1)");
        }
    }
}
?>
