<?php
/**
 * API untuk mendapatkan semua data negara
 * Mengembalikan semua negara tanpa filter
 */

header('Content-Type: application/json');
include '../config/database.php';

// Query all countries from negara table
$sql = "SELECT id, nama, kode FROM negara ORDER BY nama";

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
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>
