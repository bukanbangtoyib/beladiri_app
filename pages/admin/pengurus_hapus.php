<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];

// Cek pengurus ada
$check = $conn->query("SELECT nama_pengurus, jenis_pengurus FROM pengurus WHERE id = $id");
if ($check->num_rows == 0) {
    die("Pengurus tidak ditemukan!");
}

$pengurus = $check->fetch_assoc();

// Cek apakah ada pengurus anak
$anak_check = $conn->query("SELECT COUNT(*) as count FROM pengurus WHERE pengurus_induk_id = $id");
$anak_count = $anak_check->fetch_assoc();

if ($anak_count['count'] > 0) {
    die("Tidak bisa menghapus! Masih ada " . $anak_count['count'] . " pengurus yang dinaungi oleh pengurus ini.");
}

// Jika pengurus kota, cek ranting
if ($pengurus['jenis_pengurus'] == 'kota') {
    $ranting_check = $conn->query("SELECT COUNT(*) as count FROM ranting WHERE pengurus_kota_id = $id");
    $ranting_count = $ranting_check->fetch_assoc();
    
    if ($ranting_count['count'] > 0) {
        die("Tidak bisa menghapus! Masih ada " . $ranting_count['count'] . " unit/ranting yang dinaungi.");
    }
}

// Hapus pengurus
$conn->query("DELETE FROM pengurus WHERE id = $id");

header("Location: pengurus.php?msg=deleted");
exit();
?>