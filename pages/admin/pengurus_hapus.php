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
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

// Check if user has permission to delete
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

$id = (int)$_GET['id'];
$jenis = $_GET['jenis'] ?? 'pusat';

if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

// Map jenis to table names
$table_map = [
    'pusat' => 'negara',
    'provinsi' => 'provinsi',
    'kota' => 'kota'
];

$table = $table_map[$jenis];

// Check ownership and prevent users from deleting their own data
if ($user_role === 'negara') {
    if ($jenis === 'pusat') {
        // Negara cannot delete themselves
        die("❌ Anda tidak dapat menghapus data negara sendiri!");
    }
    // Check if the data belongs to their negara
    if ($jenis === 'provinsi') {
        $check = $conn->query("SELECT id FROM $table WHERE id = $id AND negara_id = " . (int)$user_pengurus_id);
        if ($check->num_rows == 0) {
            die("❌ Data tidak ditemukan atau bukan bagian dari wilayah Anda!");
        }
    } elseif ($jenis === 'kota') {
        $check = $conn->query("SELECT k.id FROM kota k JOIN provinsi p ON k.provinsi_id = p.id WHERE k.id = $id AND p.negara_id = " . (int)$user_pengurus_id);
        if ($check->num_rows == 0) {
            die("❌ Data tidak ditemukan atau bukan bagian dari wilayah Anda!");
        }
    }
} elseif ($user_role === 'pengprov') {
    if ($jenis === 'provinsi') {
        // Pengprov cannot delete their own province
        if ($id == $user_pengurus_id) {
            die("❌ Anda tidak dapat menghapus data provinsi sendiri!");
        }
        $check = $conn->query("SELECT id FROM $table WHERE id = $id AND id = " . (int)$user_pengurus_id);
        if ($check->num_rows == 0) {
            die("❌ Data tidak ditemukan!");
        }
    } elseif ($jenis === 'kota') {
        $check = $conn->query("SELECT id FROM $table WHERE id = $id AND provinsi_id = " . (int)$user_pengurus_id);
        if ($check->num_rows == 0) {
            die("❌ Data tidak ditemukan atau bukan bagian dari wilayah Anda!");
        }
    }
} elseif ($user_role === 'pengkot') {
    if ($jenis === 'kota') {
        // Pengkot cannot delete their own kota
        if ($id == $user_pengurus_id) {
            die("❌ Anda tidak dapat menghapus data kota sendiri!");
        }
        $check = $conn->query("SELECT id FROM $table WHERE id = $id AND id = " . (int)$user_pengurus_id);
        if ($check->num_rows == 0) {
            die("❌ Data tidak ditemukan!");
        }
    } else {
        die("❌ Akses ditolak!");
    }
} elseif (!in_array($user_role, ['admin', 'superadmin'])) {
    die("❌ Akses ditolak!");
}

// Check for child entities before deletion
if ($jenis === 'pusat') {
    // Check if there are provinsi under this negara
    $child_check = $conn->query("SELECT COUNT(*) as count FROM provinsi WHERE negara_id = $id");
    $child_count = $child_check->fetch_assoc();
    if ($child_count['count'] > 0) {
        die("❌ Tidak bisa menghapus! Masih ada " . $child_count['count'] . " provinsi di bawah negara ini.");
    }
} elseif ($jenis === 'provinsi') {
    // Check if there are kota under this provinsi
    $child_check = $conn->query("SELECT COUNT(*) as count FROM kota WHERE provinsi_id = $id");
    $child_count = $child_check->fetch_assoc();
    if ($child_count['count'] > 0) {
        die("❌ Tidak bisa menghapus! Masih ada " . $child_count['count'] . " kota di bawah provinsi ini.");
    }
} elseif ($jenis === 'kota') {
    // Check if there are ranting under this kota
    $child_check = $conn->query("SELECT COUNT(*) as count FROM ranting WHERE kota_id = $id");
    $child_count = $child_check->fetch_assoc();
    if ($child_count['count'] > 0) {
        die("❌ Tidak bisa menghapus! Masih ada " . $child_count['count'] . " unit/ranting di bawah kota ini.");
    }
}

// Map role for user deletion
$role_map = [
    'pusat' => 'negara',
    'provinsi' => 'pengprov',
    'kota' => 'pengkot'
];
$target_role = $role_map[$jenis];

// Delete associated user
$conn->query("DELETE FROM users WHERE pengurus_id = $id AND role = '$target_role'");

// Delete from organization tables
$conn->query("DELETE FROM $table WHERE id = $id");

header("Location: pengurus_list.php?jenis=$jenis");
exit();
?>
