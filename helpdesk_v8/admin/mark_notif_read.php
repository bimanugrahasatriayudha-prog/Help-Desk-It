<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
$admin = getAdmin();
$auid  = $conn->real_escape_string($admin['uid']);
$conn->query("UPDATE notifikasi SET is_read=1 WHERE uid_admin='$auid'");
echo 'ok';
