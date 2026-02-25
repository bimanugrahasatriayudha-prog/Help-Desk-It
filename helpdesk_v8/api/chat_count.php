<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');
require_once '../includes/db.php';

$count  = 0;
$status = '';

if (!empty($_GET['token'])) {
    $token = preg_replace('/[^a-f0-9\-]/', '', $_GET['token']);
    $r  = $conn->query("SELECT COUNT(*) c FROM chats ch JOIN tickets t ON ch.uid_ticket=t.uid WHERE t.chat_token='$token'");
    if ($r) $count = (int)$r->fetch_assoc()['c'];
    $tQ = $conn->query("SELECT status FROM tickets WHERE chat_token='$token' LIMIT 1");
    if ($tQ) $status = $tQ->fetch_assoc()['status'] ?? '';
} elseif (!empty($_GET['uid'])) {
    $uid = preg_replace('/[^a-f0-9\-]/', '', $_GET['uid']);
    $r  = $conn->query("SELECT COUNT(*) c FROM chats WHERE uid_ticket='$uid'");
    if ($r) $count = (int)$r->fetch_assoc()['c'];
    $tQ = $conn->query("SELECT status FROM tickets WHERE uid='$uid' LIMIT 1");
    if ($tQ) $status = $tQ->fetch_assoc()['status'] ?? '';
}

echo json_encode(['count' => $count, 'status' => $status]);
