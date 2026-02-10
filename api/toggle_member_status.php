<?php
/**
 * API Endpoint: Toggle Member Status (Aktif/Tidak)
 * Path: api/toggle_member_status.php
 * Method: POST
 * Parameters: anggota_id, status (1 = aktif, 0 = tidak aktif)
 */

session_start();
header('Content-Type: application/json');

// Include database config
$base_dir = dirname(dirname(__FILE__));
include $base_dir . '/config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if is_active column exists, add if not
$col_check = $conn->query("SHOW COLUMNS FROM anggota LIKE 'is_active'");
if ($col_check->num_rows == 0) {
    $alter_sql = "ALTER TABLE anggota ADD COLUMN is_active TINYINT DEFAULT 1";
    if (!$conn->query($alter_sql)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat kolom is_active']);
        exit();
    }
}

// Parse input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
}

$anggota_id = isset($data['anggota_id']) ? (int)$data['anggota_id'] : 0;
$is_active = isset($data['status']) ? (int)$data['status'] : 0;

if ($anggota_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid anggota_id']);
    exit();
}

// Update status
$update_sql = "UPDATE anggota SET is_active = $is_active WHERE id = $anggota_id";

if ($conn->query($update_sql)) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Status berhasil diubah',
        'anggota_id' => $anggota_id,
        'status' => $is_active
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
