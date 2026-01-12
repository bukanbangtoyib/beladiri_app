<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$peserta_id = (int)$_GET['id'];
$ukt_id = (int)$_GET['ukt_id'];

// Cek peserta ada
$check = $conn->query("SELECT * FROM ukt_peserta WHERE id = $peserta_id AND ukt_id = $ukt_id");
if ($check->num_rows == 0) {
    die("Data peserta tidak ditemukan!");
}

// Hapus peserta
$conn->query("DELETE FROM ukt_peserta WHERE id = $peserta_id AND ukt_id = $ukt_id");

// Redirect kembali
header("Location: ukt_detail.php?id=$ukt_id&msg=deleted");
exit();
?>