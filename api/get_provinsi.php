<?php
/**
 * API untuk mendapatkan data provinsi
 * 
 * Mode 1: Filter by negara_id (optional)
 *   - Parameter: negara_id (optional)
 *   - Jika negara_id disediakan, mengembalikan provinsi berdasarkan negara
 *   - Jika negara_id TIDAK disediakan, mengembalikan semua provinsi
 * 
 * Mode 2: Get all provinces
 *   - Tanpa parameter negara_id
 *   - Mengembalikan semua provinsi dengan informasi negara
 */

header('Content-Type: application/json');
include '../config/database.php';

$negara_id = isset($_GET['negara_id']) ? (int)$_GET['negara_id'] : 0;

// Mode: Get all provinces (no filter)
if ($negara_id === 0) {
    $sql = "SELECT p.id, p.nama, p.kode, p.negara_id, n.nama as negara_nama 
            FROM provinsi p 
            LEFT JOIN negara n ON p.negara_id = n.id 
            ORDER BY n.nama, p.nama";
    
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
        $display = $row['nama'];
        if (!empty($row['negara_nama'])) {
            $display .= ' (' . $row['negara_nama'] . ')';
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

// Mode: Filter by negara_id
$sql = "SELECT id, nama, kode FROM provinsi WHERE negara_id = ? ORDER BY nama";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $negara_id);
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
