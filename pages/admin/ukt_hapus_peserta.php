<?php
session_start();

if (!isset($_SESSION['user_id'])) {
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

// For pengkot role on UKT pages, use custom permission check instead of general permission
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'pengkot' || $user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    // Continue to UKT-specific permission check later
} else {
    if (!$permission_manager->can('anggota_read')) {
        die("❌ Akses ditolak!");
    }
}

$peserta_id = (int)$_GET['id'];
$ukt_id = (int)$_GET['ukt_id'];

// Cek peserta ada
$check = $conn->query("SELECT * FROM ukt_peserta WHERE id = $peserta_id AND ukt_id = $ukt_id");
if ($check->num_rows == 0) {
    die("Data peserta tidak ditemukan!");
}

// Get UKT data to check permission
$ukt_result = $conn->query("SELECT * FROM ukt WHERE id = $ukt_id");
$ukt = $ukt_result->fetch_assoc();

// Check if user can manage this UKT - special handling for pengkot
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

$can_manage = false;

if ($user_role === 'pengkot') {
    // Pengkot can only manage their own city UKT
    $can_manage = ($ukt['jenis_penyelenggara'] === 'kota' && (int)$ukt['penyelenggara_id'] === (int)$user_pengurus_id);
} elseif ($user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    $can_manage = $permission_manager->canManageUKT('ukt_update', $ukt['jenis_penyelenggara'], $ukt['penyelenggara_id']);
}

if (!$can_manage) {
    die("❌ Akses ditolak! Anda tidak memiliki izin untuk menghapus peserta UKT ini.");
}

// Hapus peserta
$conn->query("DELETE FROM ukt_peserta WHERE id = $peserta_id AND ukt_id = $ukt_id");

// Redirect kembali
$return_url = isset($_GET['return']) ? $_GET['return'] : "ukt_detail.php?id=$ukt_id&msg=deleted";
header("Location: $return_url");
exit();
?>