<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../../config/database.php';

$tingkat_id = (int)($_GET['tingkat_id'] ?? 0);

if ($tingkat_id == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid tingkat_id']);
    exit();
}

// Get current tingkat urutan
$current = $conn->query("SELECT urutan FROM tingkatan WHERE id = $tingkat_id");

if ($current->num_rows == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tingkat not found']);
    exit();
}

$current_data = $current->fetch_assoc();
$next_urutan = $current_data['urutan'] + 1;

// Get next tingkat
$next = $conn->query("SELECT id, nama_tingkat FROM tingkatan WHERE urutan = $next_urutan");

header('Content-Type: application/json');

if ($next->num_rows > 0) {
    $next_data = $next->fetch_assoc();
    echo json_encode([
        'success' => true,
        'next_tingkat' => $next_data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Sudah tingkat tertinggi'
    ]);
}
?>