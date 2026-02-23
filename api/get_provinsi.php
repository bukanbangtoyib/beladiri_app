<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include '../config/database.php';

$negara_id = isset($_GET['negara_id']) ? (int)$_GET['negara_id'] : 0;

if ($negara_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid negara_id']);
    exit();
}

// Query provinces from provinsi table based on negara_id
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
