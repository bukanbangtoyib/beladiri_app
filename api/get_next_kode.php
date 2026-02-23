<?php
/**
 * API untuk mendapatkan kode berikutnya berdasarkan parent
 * 
 * Param:
 * - table: nama tabel (provinsi, kota)
 * - parent_id: ID parent (negara_id untuk provinsi, provinsi_id untuk kota)
 * - parent_field: nama kolom parent di tabel
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include dirname(dirname(__FILE__)) . '/config/database.php';

$table = $_GET['table'] ?? '';
$parentId = (int)($_GET['parent_id'] ?? 0);
$parentField = $_GET['parent_field'] ?? '';

if (empty($table) || $parentId <= 0 || empty($parentField)) {
    echo json_encode(['kode' => '001']);
    exit();
}

// Validasi tabel yang diizinkan
$allowedTables = ['provinsi', 'kota'];
if (!in_array($table, $allowedTables)) {
    echo json_encode(['kode' => '001']);
    exit();
}

// Query untuk mendapatkan kode terakhir
$sql = "SELECT kode FROM $table WHERE $parentField = $parentId ORDER BY kode DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    // Ambil angka dari kode terakhir dan tambahkan 1
    $lastKode = $row['kode'];
    $lastNumber = (int)$lastKode;
    $nextNumber = $lastNumber + 1;
    $nextKode = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
} else {
    // Jika tidak ada, mulai dari 001
    $nextKode = '001';
}

echo json_encode(['kode' => $nextKode]);
