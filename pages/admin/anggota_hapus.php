<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$user_ranting_id = $_SESSION['ranting_id'] ?? 0;

// Allow admin, ranting, and unit roles
if (!in_array($user_role, ['superadmin', 'admin', 'ranting', 'unit'])) {
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

$id = (int)$_GET['id'];

$result = $conn->query("SELECT nama_lengkap, ranting_saat_ini_id, no_anggota FROM anggota WHERE id = $id");
if ($result->num_rows == 0) {
    die("Anggota tidak ditemukan!");
}
$anggota = $result->fetch_assoc();

// Check ownership for ranting and unit roles
if ($user_role === 'ranting' || $user_role === 'unit') {
    $member_ranting_id = $anggota['ranting_saat_ini_id'] ?? 0;
    if ($member_ranting_id != $user_ranting_id) {
        die("❌ Anda hanya bisa menghapus anggota dari ranting Anda sendiri!");
    }
}

// Hapus user terkait
$conn->query("DELETE FROM users WHERE anggota_id = $id OR (no_anggota IS NOT NULL AND no_anggota = '" . $conn->real_escape_string($anggota['no_anggota'] ?? '') . "')");

$conn->query("DELETE FROM anggota WHERE id = $id");

header("Location: anggota.php?msg=deleted");
exit();
?>