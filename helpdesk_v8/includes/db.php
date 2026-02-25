<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'helpdesk_v3');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<div style="padding:20px;color:red">Koneksi database gagal: ' . $conn->connect_error . '</div>');
}
$conn->set_charset('utf8mb4');

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,
        mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
    );
}

function generateKode() {
    return 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
}
?>
