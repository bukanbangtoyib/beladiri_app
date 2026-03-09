<?php
// File: api/get_penyelenggara.php
// API untuk mendapatkan daftar penyelenggara berdasarkan jenis pengurus atau pencarian umum

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../config/database.php';

$jenis_pengurus = $_GET['jenis_pengurus'] ?? '';
$search = $_GET['q'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Map jenis to table
$table_map = [
    'pusat' => 'negara',
    'provinsi' => 'provinsi',
    'kota' => 'kota'
];

if ($id) {
    // Jika mencari ID spesifik, kita perlu tahu jenisnya atau cari di semua tabel (asumsi ID unik lintas tabel atau butuh parameter tambahan)
    // Untuk kesederhanaan, jika ada jenis_pengurus gunakan itu, jika tidak cari di kota (paling umum)
    $table_name = isset($table_map[$jenis_pengurus]) ? $table_map[$jenis_pengurus] : 'kota';
    
    $stmt = $conn->prepare("SELECT id, nama FROM $table_name WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data, 'jenis' => array_search($table_name, $table_map)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found']);
    }
    $stmt->close();
    exit();
}

if (!empty($jenis_pengurus)) {
    // Validasi jenis pengurus
    if (!isset($table_map[$jenis_pengurus])) {
        http_response_code(400);
        echo json_encode(['error' => 'Jenis pengurus tidak valid']);
        exit();
    }

    $table_name = $table_map[$jenis_pengurus];
    $sql = "SELECT id, nama FROM $table_name";
    
    if (!empty($search)) {
        $sql .= " WHERE nama LIKE ?";
    }
    $sql .= " ORDER BY nama ASC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt->bind_param("s", $searchTerm);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => (int)$row['id'],
            'nama' => $row['nama']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
    $stmt->close();
} else if (!empty($search)) {
    // Search across all types if jenis_pengurus is not provided
    $results = [];
    foreach ($table_map as $type => $table) {
        $stmt = $conn->prepare("SELECT id, nama FROM $table WHERE nama LIKE ? LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id' => (int)$row['id'],
                'nama' => $row['nama'] . " (" . ucfirst($type) . ")",
                'original_nama' => $row['nama'],
                'jenis' => $type
            ];
        }
        $stmt->close();
    }
    echo json_encode(['success' => true, 'data' => $results]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter tidak lengkap']);
}
?>