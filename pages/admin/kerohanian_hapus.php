<?php
session_start();

// Allow admin, negara, pengprov, pengkot to delete their own data
$allowed_roles = ['admin', 'negara', 'pengprov', 'pengkot'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
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
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$user_role = $_SESSION['role'];
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

// Get user's organization name
$user_org_name = '';
if ($user_role == 'negara' && $user_pengurus_id) {
    $org_result = $conn->query("SELECT nama FROM negara WHERE id = $user_pengurus_id");
    if ($org_result && $org_result->num_rows > 0) {
        $user_org_name = $org_result->fetch_assoc()['nama'];
    }
} elseif ($user_role == 'pengprov' && $user_pengurus_id) {
    $org_result = $conn->query("SELECT nama FROM provinsi WHERE id = $user_pengurus_id");
    if ($org_result && $org_result->num_rows > 0) {
        $user_org_name = $org_result->fetch_assoc()['nama'];
    }
} elseif ($user_role == 'pengkot' && $user_pengurus_id) {
    $org_result = $conn->query("SELECT nama FROM kota WHERE id = $user_pengurus_id");
    if ($org_result && $org_result->num_rows > 0) {
        $user_org_name = $org_result->fetch_assoc()['nama'];
    }
}

$id = (int)$_GET['id'];

// Cek data ada dan get penyelenggara
$check = $conn->query("SELECT anggota_id, penyelenggara FROM kerohanian WHERE id = $id");
if ($check->num_rows == 0) {
    die("Data kerohanian tidak ditemukan!");
}

$data = $check->fetch_assoc();
$anggota_id = $data['anggota_id'];
$record_penyelenggara = $data['penyelenggara'] ?? '';

// Check ownership - admin can delete all, others can only delete their own organization's data
$is_owner = ($user_role === 'admin') || ($record_penyelenggara === $user_org_name);

if (!$is_owner) {
    die("❌ Anda hanya bisa menghapus data yang dibuat oleh organisasi Anda!");
}

// Hapus kerohanian
$conn->query("DELETE FROM kerohanian WHERE id = $id");

// Update status kerohanian di anggota menjadi belum
$conn->query("UPDATE anggota SET status_kerohanian = 'belum', tanggal_pembukaan_kerohanian = NULL WHERE id = $anggota_id");

header("Location: kerohanian.php?msg=deleted");
exit();
?>