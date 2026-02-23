<?php
header('Content-Type: application/json');

include '../config/database.php';

// Accept both pengkot_id (legacy) and kota_id
$kota_id = isset($_GET['kota_id']) ? (int)$_GET['kota_id'] : 0;
if ($kota_id === 0) {
    $kota_id = isset($_GET['pengkot_id']) ? (int)$_GET['pengkot_id'] : 0;
}

if ($kota_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid kota_id']);
    exit();
}

// Query ranting yang berada di bawah kota yang dipilih
$result = $conn->query("SELECT id, nama_ranting, kode FROM ranting WHERE kota_id = $kota_id ORDER BY nama_ranting");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>