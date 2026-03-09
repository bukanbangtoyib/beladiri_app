<?php
/**
 * API untuk mendapatkan data kota
 * 
 * Mode 1: Filter by provinsi_id (optional)
 *   - Parameter: provinsi_id (optional)
 *   - Jika provinsi_id disediakan, mengembalikan kota berdasarkan provinsi
 *   - Jika provinsi_id TIDAK disediakan, mengembalikan semua kota
 * 
 * Mode 2: Get all cities
 *   - Tanpa parameter provinsi_id
 *   - Mengembalikan semua kota dengan informasi provinsi dan negara
 */

header('Content-Type: application/json');
include '../config/database.php';

$provinsi_id = isset($_GET['provinsi_id']) ? (int)$_GET['provinsi_id'] : 0;

// Mode: Get all cities (no filter)
if ($provinsi_id === 0) {
    // Query all cities from kota table with province and negara info
    $sql = "SELECT k.id, k.nama, k.kode, k.provinsi_id, p.nama as provinsi_nama, p.negara_id, n.nama as negara_nama 
            FROM kota k 
            LEFT JOIN provinsi p ON k.provinsi_id = p.id 
            LEFT JOIN negara n ON p.negara_id = n.id 
            ORDER BY n.nama, p.nama, k.nama";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Query failed: ' . $conn->error
        ]);
        exit();
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $location = array_filter([
            $row['provinsi_nama'] ?? '',
            $row['negara_nama'] ?? ''
        ]);
        $locationStr = implode(', ', $location);
        
        $display = $row['nama'];
        if (!empty($locationStr)) {
            $display .= ' (' . $locationStr . ')';
        }
        $row['display'] = $display;
        $data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit();
}

// Mode: Filter by provinsi_id
$sql = "SELECT id, nama, kode FROM kota WHERE provinsi_id = ? ORDER BY nama";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $provinsi_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
    exit();
}

$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>
