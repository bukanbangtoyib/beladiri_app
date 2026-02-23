<?php
/**
 * API untuk generate nomor anggota otomatis
 * Format: NNPPPKKK.RRR-YYYYXXX
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit();
}

include dirname(dirname(__FILE__)) . '/config/database.php';
include dirname(dirname(__FILE__)) . '/config/settings.php';

// Get parameters
$negara_id = isset($_GET['negara_id']) ? (int)$_GET['negara_id'] : 0;
$provinsi_id = isset($_GET['provinsi_id']) ? (int)$_GET['provinsi_id'] : 0;
$kota_id = isset($_GET['kota_id']) ? (int)$_GET['kota_id'] : 0;
$ranting_id = isset($_GET['ranting_id']) ? (int)$_GET['ranting_id'] : 0;
$tahun_bergabung = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

if ($ranting_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ranting harus dipilih!']);
    exit();
}

// Get codes from database
$negara_kode = '';
$provinsi_kode = '';
$kota_kode = '';
$ranting_kode = '';

// Get negara kode
if ($negara_id > 0) {
    $result = $conn->query("SELECT kode FROM negara WHERE id = $negara_id");
    if ($row = $result->fetch_assoc()) {
        $negara_kode = str_pad($row['kode'] ?? '0', 2, '0', STR_PAD_LEFT);
    }
}

// Get provinsi kode
if ($provinsi_id > 0) {
    $result = $conn->query("SELECT kode FROM provinsi WHERE id = $provinsi_id");
    if ($row = $result->fetch_assoc()) {
        $provinsi_kode = str_pad($row['kode'] ?? '0', 3, '0', STR_PAD_LEFT);
    }
}

// Get kota kode
if ($kota_id > 0) {
    $result = $conn->query("SELECT kode FROM kota WHERE id = $kota_id");
    if ($row = $result->fetch_assoc()) {
        $kota_kode = str_pad($row['kode'] ?? '0', 3, '0', STR_PAD_LEFT);
    }
}

// Get ranting kode from ranting table
$raw_kode = null;
$ranting_kode = '0';

// Debug: Check all ranting first - no WHERE clause
$all_ranting = [];
$ranting_all = $conn->query("SELECT id, nama_ranting, kode FROM ranting");
if (!$ranting_all) {
    $debug_error = "Error getting all ranting: " . $conn->error;
} else {
    while ($r = $ranting_all->fetch_assoc()) {
        $all_ranting[] = $r;
    }
}

$ranting_query = $conn->query("SELECT id, kode FROM ranting WHERE id = $ranting_id");

if (!$ranting_query) {
    // Query failed - probably column doesn't exist
    $debug_error = "Query failed: " . $conn->error;
} elseif ($ranting_row = $ranting_query->fetch_assoc()) {
    // Debug: show raw kode value
    $raw_kode = $ranting_row['kode'];
    $ranting_kode = str_pad($raw_kode ?? '0', 3, '0', STR_PAD_LEFT);
} else {
    // No rows returned
    $debug_error = "No rows returned for id=$ranting_id. Available IDs: " . json_encode(array_column($all_ranting, 'id'));
}

// Get next sequence number for this year
// Format: ID001001.001-2002001 (where 2002 is year and 001 is sequence)
// We extract the last 7 characters and get the sequence (last 3 digits)
$sql = "SELECT MAX(CAST(RIGHT(no_anggota, 3) AS UNSIGNED)) as max_urut 
        FROM anggota 
        WHERE no_anggota LIKE '%-" . $tahun_bergabung . "%'";

$result = $conn->query($sql);
$max_urut = 0;
if ($row = $result->fetch_assoc()) {
    $max_urut = (int)($row['max_urut'] ?? 0);
}
$next_urut = $max_urut + 1;
$urut_kode = str_pad($next_urut, 3, '0', STR_PAD_LEFT);

// Build the FULL number (always stored in database): NNPPPKKK.RRR-YYYYXXX
$kode_sebelum_ranting = $negara_kode . $provinsi_kode . $kota_kode;
$no_anggota_full = $kode_sebelum_ranting . '.' . $ranting_kode . '-' . $tahun_bergabung . $urut_kode;

// Build the DISPLAY number based on settings
$kode_parts = [];
if ($pengaturan_nomor['kode_negara'] ?? true) {
    $kode_parts[] = $negara_kode;
}
if ($pengaturan_nomor['kode_provinsi'] ?? true) {
    $kode_parts[] = $provinsi_kode;
}
if ($pengaturan_nomor['kode_kota'] ?? true) {
    $kode_parts[] = $kota_kode;
}
$kode_str = implode('', $kode_parts);

// Ranting
$ranting_str = '';
if ($pengaturan_nomor['kode_ranting'] ?? true) {
    if (!empty($kode_str)) {
        $ranting_str = '.' . $ranting_kode;
    } else {
        $ranting_str = $ranting_kode;
    }
}

// Year and sequence
$year_seq_str = '';
$year_part = ($pengaturan_nomor['tahun_daftar'] ?? true) ? $tahun_bergabung : '';
$seq_part = ($pengaturan_nomor['urutan_daftar'] ?? true) ? $urut_kode : '';

if (!empty($year_part) || !empty($seq_part)) {
    if (!empty($kode_str) || !empty($ranting_str)) {
        $year_seq_str = '-' . $year_part . $seq_part;
    } else {
        $year_seq_str = $year_part . $seq_part;
    }
}

$no_anggota_display = $kode_str . $ranting_str . $year_seq_str;

echo json_encode([
    'success' => true,
    'no_anggota' => $no_anggota_full,  // Full format for database
    'no_anggota_display' => $no_anggota_display,  // Display format based on settings
    'debug' => [
        'ranting_id_received' => $ranting_id,
        'raw_kode' => $raw_kode ?? 'NOT_FOUND',
        'query_error' => $debug_error ?? 'none',
        'negara_kode' => $negara_kode,
        'provinsi_kode' => $provinsi_kode,
        'kota_kode' => $kota_kode,
        'ranting_kode' => $ranting_kode,
        'urut' => $urut_kode,
        'all_ranting' => $all_ranting,
        'pengaturan_nomor' => $pengaturan_nomor
    ]
]);
