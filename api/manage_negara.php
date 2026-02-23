<?php
/**
 * API untuk manajemen kode negara
 * CRUD operations untuk tabel negara
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
$conn->query("UPDATE negara SET aktif = 0 WHERE periode_akhir IS NOT NULL AND periode_akhir != '' AND periode_akhir < '$today' AND aktif = 1");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Helper function untuk generate kode negara
 */
function generateNegaraCode($conn, $urutan) {
    return str_pad($urutan, 2, '0', STR_PAD_LEFT);
}

/**
 * Helper function untuk update urutan dan kode semua negara
 */
function updateAllNegaraOrder($conn) {
    $result = $conn->query("SELECT id FROM negara ORDER BY nama ASC, id ASC");
    $urutan = 1;
    while ($row = $result->fetch_assoc()) {
        $kode = str_pad($urutan, 2, '0', STR_PAD_LEFT);
        $conn->query("UPDATE negara SET urutan = $urutan, kode = '$kode' WHERE id = " . $row['id']);
        $urutan++;
    }
}

switch ($action) {
    case 'list':
        $result = $conn->query("SELECT * FROM negara ORDER BY nama ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'add':
        $kode = $conn->real_escape_string($_POST['kode'] ?? '');
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama negara tidak boleh kosong!']);
            exit();
        }
        
        // Get next urutan
        $count = $conn->query("SELECT COUNT(*) as cnt FROM negara")->fetch_assoc();
        $urutan = ($count['cnt'] ?? 0) + 1;
        
        // Auto-generate kode if not provided
        if (empty($kode)) {
            $kode = generateNegaraCode($conn, $urutan);
        }
        
        // Check duplicate kode
        $check = $conn->query("SELECT id FROM negara WHERE kode = '" . $conn->real_escape_string($kode) . "'");
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Kode negara sudah digunakan!']);
            exit();
        }
        
        $sql = "INSERT INTO negara (kode, nama, aktif) VALUES ('$kode', '$nama', 1)";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Negara berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan negara: ' . $conn->error]);
        }
        break;
        
    case 'update':
        $id = (int)$_POST['id'];
        $kode = $conn->real_escape_string($_POST['kode'] ?? '');
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama negara tidak boleh kosong!']);
            exit();
        }
        
        if (empty($kode)) {
            echo json_encode(['success' => false, 'message' => 'Kode negara tidak boleh kosong!']);
            exit();
        }
        
        // Check duplicate kode (excluding current record)
        $check = $conn->query("SELECT id FROM negara WHERE kode = '$kode' AND id != $id");
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Kode negara sudah digunakan oleh negara lain!']);
            exit();
        }
        
        $sql = "UPDATE negara SET kode = '$kode', nama = '$nama' WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Negara berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui negara: ' . $conn->error]);
        }
        break;
        
    case 'delete':
        $id = (int)$_POST['id'];
        
        // Soft delete - toggle aktif status
        $current = $conn->query("SELECT aktif FROM negara WHERE id = $id")->fetch_assoc();
        $newStatus = $current['aktif'] == 1 ? 0 : 1;
        
        $statusText = $newStatus == 1 ? 'diaktifkan' : 'dinonaktifkan';
        
        $sql = "UPDATE negara SET aktif = $newStatus WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => "Negara berhasil $statusText!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah status negara: ' . $conn->error]);
        }
        break;
        
    case 'reorder':
        $orders = $_POST['orders'] ?? [];
        
        if (empty($orders) || !is_array($orders)) {
            echo json_encode(['success' => false, 'message' => 'Data urutan tidak valid!']);
            exit();
        }
        
        $conn->query("START TRANSACTION");
        
        try {
            foreach ($orders as $item) {
                $id = (int)$item['id'];
                $urutan = (int)$item['urutan'];
                $kode = generateNegaraCode($conn, $id);
                
                $conn->query("UPDATE negara SET kode = '$kode' WHERE id = $id");
            }
            
            $conn->query("COMMIT");
            echo json_encode(['success' => true, 'message' => 'Urutan negara berhasil diperbarui!']);
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate urutan: ' . $e->getMessage()]);
        }
        break;
        
    case 'get':
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT * FROM negara WHERE id = $id");
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Negara dengan ID $id tidak ditemukan!']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
}
