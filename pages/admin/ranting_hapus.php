<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];

// Cek ranting ada
$check = $conn->query("SELECT nama_ranting FROM ranting WHERE id = $id");
if ($check->num_rows == 0) {
    die("Unit/Ranting tidak ditemukan!");
}

$ranting = $check->fetch_assoc();

// Cek apakah ada anggota di ranting ini
$anggota_check = $conn->query("SELECT COUNT(*) as count FROM anggota WHERE ranting_saat_ini_id = $id");
$anggota_count = $anggota_check->fetch_assoc();

if ($anggota_count['count'] > 0) {
    die("Tidak bisa menghapus! Masih ada " . $anggota_count['count'] . " anggota di unit/ranting ini.");
}

// Hapus jadwal latihan dulu
$conn->query("DELETE FROM jadwal_latihan WHERE ranting_id = $id");

// Hapus ranting
$conn->query("DELETE FROM ranting WHERE id = $id");

header("Location: ranting.php?msg=deleted");
exit();
?>