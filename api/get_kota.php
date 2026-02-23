<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include '../config/database.php';

$provinsi_id = isset($_GET['provinsi_id']) ? (int)$_GET['provinsi_id'] : 0;

if ($provinsi_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid provinsi_id']);
    exit();
}

// Query cities from kota table based on province_id
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
