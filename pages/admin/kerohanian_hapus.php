<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';

// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$id = (int)$_GET['id'];

// Cek data ada
$check = $conn->query("SELECT anggota_id FROM kerohanian WHERE id = $id");
if ($check->num_rows == 0) {
    die("Data kerohanian tidak ditemukan!");
}

$data = $check->fetch_assoc();
$anggota_id = $data['anggota_id'];

// Hapus kerohanian
$conn->query("DELETE FROM kerohanian WHERE id = $id");

// Update status kerohanian di anggota menjadi belum
$conn->query("UPDATE anggota SET status_kerohanian = 'belum', tanggal_pembukaan_kerohanian = NULL WHERE id = $anggota_id");

header("Location: kerohanian.php?msg=deleted");
exit();
?>