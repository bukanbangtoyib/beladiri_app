<?php
/**
 * API untuk manajemen kode provinsi
 * CRUD operations untuk tabel provinsi
 */

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit();
}

include dirname(dirname(__FILE__)) . '/config/database.php';

// Auto-update status based on periode_akhir
$today = date('Y-m-d');
$conn->query("UPDATE provinsi SET aktif = 0 WHERE periode_akhir IS NOT NULL AND periode_akhir != '' AND periode_akhir < '$today' AND aktif = 1");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $result = $conn->query("SELECT p.*, n.nama as nama_negara FROM provinsi p LEFT JOIN negara n ON p.negara_id = n.id ORDER BY p.negara_id ASC, p.id ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'add':
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        $id_negara = (int)($_POST['id_negara'] ?? 0);
        $kode = strtoupper($conn->real_escape_string($_POST['kode'] ?? ''));
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama provinsi tidak boleh kosong!']);
            exit();
        }
        
        if ($id_negara <= 0) {
            echo json_encode(['success' => false, 'message' => 'Negara harus dipilih!']);
            exit();
        }
        
        $conn->query("INSERT INTO provinsi (nama, negara_id, kode, aktif) VALUES ('$nama', $id_negara, '$kode', 1)");
        echo json_encode(['success' => true, 'message' => 'Provinsi berhasil ditambahkan!']);
        break;
        
    case 'update':
        $id = (int)$_POST['id'];
        $id_negara = (int)($_POST['id_negara'] ?? 0);
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        $kode = strtoupper($conn->real_escape_string($_POST['kode'] ?? ''));
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama provinsi tidak boleh kosong!']);
            exit();
        }
        
        $conn->query("UPDATE provinsi SET nama = '$nama', negara_id = $id_negara, kode = '$kode' WHERE id = $id");
        echo json_encode(['success' => true, 'message' => 'Provinsi berhasil diperbarui!']);
        break;
        
    case 'delete':
        $id = (int)$_POST['id'];
        
        // Toggle aktif status
        $current = $conn->query("SELECT aktif FROM provinsi WHERE id = $id")->fetch_assoc();
        $newStatus = $current['aktif'] == 1 ? 0 : 1;
        
        $statusText = $newStatus == 1 ? 'diaktifkan' : 'dinonaktifkan';
        
        // Also toggle for all cities in this province
        $conn->query("UPDATE kota SET aktif = $newStatus WHERE provinsi_id = $id");
        
        $conn->query("UPDATE provinsi SET aktif = $newStatus WHERE id = $id");
        echo json_encode(['success' => true, 'message' => "Provinsi berhasil $statusText!"]);
        break;
        
    case 'get_by_negara':
        $id_negara = (int)$_GET['id_negara'];
        $result = $conn->query("SELECT * FROM provinsi WHERE negara_id = $id_negara ORDER BY nama ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
}
