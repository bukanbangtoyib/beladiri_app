<?php
/**
 * API untuk mencari anggota berdasarkan nama
 * Mode 1: Tanpa parameter exclude_kerohanian - mengembalikan semua anggota
 * Mode 2: Dengan parameter exclude_kerohanian=1 - mengembalikan anggota yang belum pembukaan kerohanian
 */

header('Content-Type: application/json');
include '../config/database.php';

// Handle Detail Mode (by ID)
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $sql = "SELECT a.*, t.nama_tingkat 
            FROM anggota a
            LEFT JOIN tingkatan t ON a.tingkat_id = t.urutan
            WHERE a.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Anggota tidak ditemukan']);
    }
    exit;
}

// Handle Search Mode
$search = isset($_GET['q']) ? $_GET['q'] : '';
$search = trim($search);
$exclude_kerohanian = isset($_GET['exclude_kerohanian']) ? (int)$_GET['exclude_kerohanian'] : 0;
$jenis_peny = isset($_GET['jenis_peny']) ? $_GET['jenis_peny'] : '';
$peny_id = isset($_GET['peny_id']) ? (int)$_GET['peny_id'] : 0;
$list_all = isset($_GET['list']) ? (int)$_GET['list'] : 0;

// List Mode: return all anggota (for dropdown)
if ($list_all) {
    $sql = "SELECT a.id, a.no_anggota, a.nama_lengkap 
            FROM anggota a
            ORDER BY a.nama_lengkap";
    
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'no_anggota' => $row['no_anggota'],
            'nama_lengkap' => $row['nama_lengkap']
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Search anggota by name or no_anggota
$sql = "SELECT a.id, a.no_anggota, a.nama_lengkap, r.nama_ranting, t.nama_tingkat, t.urutan 
        FROM anggota a
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id
        LEFT JOIN tingkatan t ON a.tingkat_id = t.urutan";

if ($jenis_peny == 'provinsi') {
    $sql .= " LEFT JOIN kota k ON r.kota_id = k.id";
} elseif ($jenis_peny == 'pusat') {
    $sql .= " LEFT JOIN kota k ON r.kota_id = k.id
              LEFT JOIN provinsi p ON k.provinsi_id = p.id";
}

$sql .= " WHERE (a.nama_lengkap LIKE ? OR a.no_anggota LIKE ?)";

if ($exclude_kerohanian) {
    $sql .= " AND NOT EXISTS (SELECT 1 FROM kerohanian WHERE anggota_id = a.id)";
}

if ($jenis_peny == 'kota' && $peny_id > 0) {
    $sql .= " AND r.kota_id = $peny_id AND t.urutan BETWEEN 1 AND 7";
} elseif ($jenis_peny == 'provinsi' && $peny_id > 0) {
    $sql .= " AND k.provinsi_id = $peny_id AND t.urutan BETWEEN 8 AND 9";
} elseif ($jenis_peny == 'pusat' && $peny_id > 0) {
    $sql .= " AND p.negara_id = $peny_id AND t.urutan BETWEEN 10 AND 12";
}

// Filter by jenis_anggota (can be ID or name)
$jenis_filter = isset($_GET['jenis']) ? $_GET['jenis'] : '';
if (!empty($jenis_filter)) {
    if (is_numeric($jenis_filter)) {
        $sql .= " AND a.jenis_anggota = " . (int)$jenis_filter;
    } else {
        // Resolve name to ID via subquery
        $sql .= " AND a.jenis_anggota IN (SELECT id FROM jenis_anggota WHERE nama_jenis LIKE '%" . $conn->real_escape_string($jenis_filter) . "%')";
    }
}

$sql .= " ORDER BY a.nama_lengkap LIMIT 20";

$stmt = $conn->prepare($sql);
$searchParam = "%$search%";
$stmt->bind_param("ss", $searchParam, $searchParam);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $rantingName = $row['nama_ranting'] ?? '-';
    $noAnggota = $row['no_anggota'] ?? '-';
    $namaTingkat = $row['nama_tingkat'] ?? '-';
    $data[] = [
        'id' => $row['id'],
        'no_anggota' => $noAnggota,
        'nama_lengkap' => $row['nama_lengkap'],
        'ranting' => $rantingName,
        'tingkat' => $namaTingkat,
        'display' => "[{$namaTingkat}] " . $row['nama_lengkap'] . ' (' . $noAnggota . ') - ' . $rantingName
    ];
}

echo json_encode(['success' => true, 'data' => $data]);
