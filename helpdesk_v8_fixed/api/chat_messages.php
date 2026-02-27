<?php
/**
 * API: Ambil pesan chat terbaru untuk realtime polling
 * GET params:
 *   token   = chat_token (untuk user/publik)
 *   uid     = ticket uid  (untuk admin — wajib session admin)
 *   after   = uid chat terakhir yang sudah ada di browser
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Robots-Tag: noindex');

require_once '../includes/db.php';
require_once '../includes/auth.php';

$messages = [];
$status   = '';
$ticket   = null;

// ── Mode USER (pakai token) ────────────────────────────────
if (!empty($_GET['token'])) {
    $token = preg_replace('/[^a-f0-9\-]/', '', $_GET['token']);
    if (!$token) { echo json_encode(['ok'=>false,'error'=>'invalid token']); exit; }

    $tQ = $conn->query("SELECT uid, status FROM tickets WHERE chat_token='$token' LIMIT 1");
    if (!$tQ || !($ticket = $tQ->fetch_assoc())) {
        echo json_encode(['ok'=>false,'error'=>'ticket not found']); exit;
    }

    $tid    = $conn->real_escape_string($ticket['uid']);
    $status = $ticket['status'];

    // Mark admin pesan sebagai dibaca
    $conn->query("UPDATE chats SET dibaca=1 WHERE uid_ticket='$tid' AND sender='admin' AND dibaca=0");
}

// ── Mode ADMIN (pakai uid tiket) ───────────────────────────
elseif (!empty($_GET['uid'])) {
    if (!isAdminLoggedIn()) {
        echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
    }
    $tid = preg_replace('/[^a-f0-9\-]/', '', $_GET['uid']);
    if (!$tid) { echo json_encode(['ok'=>false,'error'=>'invalid uid']); exit; }

    $tQ = $conn->query("SELECT uid, status FROM tickets WHERE uid='$tid' LIMIT 1");
    if (!$tQ || !($ticket = $tQ->fetch_assoc())) {
        echo json_encode(['ok'=>false,'error'=>'ticket not found']); exit;
    }

    $status = $ticket['status'];

    // Mark user pesan sebagai dibaca
    $conn->query("UPDATE chats SET dibaca=1 WHERE uid_ticket='$tid' AND sender='user' AND dibaca=0");
}

else {
    echo json_encode(['ok'=>false,'error'=>'missing params']); exit;
}

// ── Ambil pesan setelah uid tertentu ───────────────────────
$after = preg_replace('/[^a-f0-9\-]/', '', $_GET['after'] ?? '');

if ($after) {
    // Cari created_at dari pesan terakhir yang diketahui browser
    $aQ = $conn->query("SELECT created_at FROM chats WHERE uid='$after' LIMIT 1");
    if ($aQ && $row = $aQ->fetch_assoc()) {
        $afterTime = $conn->real_escape_string($row['created_at']);
        $chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$tid' AND created_at > '$afterTime' ORDER BY created_at ASC");
    } else {
        // uid tidak ditemukan → kirim semua
        $chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$tid' ORDER BY created_at ASC");
    }
} else {
    // Pertama kali → kirim semua
    $chats = $conn->query("SELECT * FROM chats WHERE uid_ticket='$tid' ORDER BY created_at ASC");
}

if ($chats) {
    while ($c = $chats->fetch_assoc()) {
        $messages[] = [
            'uid'        => $c['uid'],
            'sender'     => $c['sender'],
            'is_system'  => (bool)$c['is_system'],
            'pesan'      => $c['pesan'],
            'created_at' => $c['created_at'],
            'dibaca'     => (bool)$c['dibaca'],
        ];
    }
}

echo json_encode([
    'ok'       => true,
    'status'   => $status,
    'messages' => $messages,
    'server_time' => date('Y-m-d H:i:s'),
]);
