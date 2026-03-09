<?php
/**
 * API untuk mendapatkan data ranting
 * 
 * Mode 1: Filter by kota_id/pengkot_id
 *   - Parameter: kota_id atau pengkot_id
 *   - Mengembalikan ranting berdasarkan kota yang dipilih
 * 
 * Mode 2: Search all ranting
 *   - Parameter: q (optional, untuk pencarian)
 *   - Mengembalikan semua ranting dengan format: kode - nama_ranting (kota, provinsi, negara)
 */

header('Content-Type: application/json');
include '../config/database.php';

// Check if filtering by kota_id
$kota_id = isset($_GET['kota_id']) ? (int)$_GET['kota_id'] : 0;
if ($kota_id === 0) {
    $kota_id = isset($_GET['pengkot_id']) ? (int)$_GET['pengkot_id'] : 0;
}

// Mode 1: Filter by kota_id
if ($kota_id > 0) {
    $result = $conn->query("SELECT id, nama_ranting, kode FROM ranting WHERE kota_id = $kota_id ORDER BY nama_ranting");
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit();
}

// Mode 2: Search all ranting (only by nama_ranting)
$search = isset($_GET['q']) ? $_GET['q'] : '';
$search = trim($search);

$sql = "SELECT r.id, r.kode, r.nama_ranting, k.nama as kota_nama, p.nama as provinsi_nama, n.nama as negara_nama
        FROM ranting r
        LEFT JOIN kota k ON r.kota_id = k.id
        LEFT JOIN provinsi p ON k.provinsi_id = p.id
        LEFT JOIN negara n ON p.negara_id = n.id";

if (!empty($search)) {
    $sql .= " WHERE r.nama_ranting LIKE ?";
}

$sql .= " ORDER BY r.nama_ranting ASC LIMIT 50";

$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $location = array_filter([
        $row['kota_nama'] ?? '',
        $row['provinsi_nama'] ?? '',
        $row['negara_nama'] ?? ''
    ]);
    $locationStr = implode(', ', $location);
    
    $display = $row['nama_ranting'];
    if (!empty($locationStr)) {
        $display .= ' (' . $locationStr . ')';
    }
    
    $data[] = [
        'id' => $row['id'],
        'kode' => $row['kode'],
        'nama_ranting' => $row['nama_ranting'],
        'location' => $locationStr,
        'display' => $display
    ];
}

echo json_encode(['success' => true, 'data' => $data]);
