<?php
/**
 * API untuk manajemen kode kota/kabupaten
 * CRUD operations untuk tabel kota
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
$conn->query("UPDATE kota SET aktif = 0 WHERE periode_akhir IS NOT NULL AND periode_akhir != '' AND periode_akhir < '$today' AND aktif = 1");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Helper function untuk generate kode kota (3-digit zero-padded, per provinsi)
 */
function generateKotaCode($conn, $urutan) {
    return str_pad($urutan, 3, '0', STR_PAD_LEFT);
}

switch ($action) {
    case 'list':
        $provinsi_id = (int)($_GET['provinsi_id'] ?? 0);
        
        if ($provinsi_id > 0) {
            $result = $conn->query("SELECT * FROM kota WHERE provinsi_id = $provinsi_id ORDER BY nama ASC");
        } else {
            $result = $conn->query("SELECT k.*, p.nama as nama_provinsi FROM kota k LEFT JOIN provinsi p ON k.provinsi_id = p.id ORDER BY p.nama ASC, k.urutan ASC");
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'add':
        $provinsi_id = (int)$_POST['provinsi_id'];
        $negara_id = (int)($_POST['negara_id'] ?? 0);
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        
        if (empty($provinsi_id)) {
            echo json_encode(['success' => false, 'message' => 'Provinsi harus dipilih!']);
            exit();
        }
        
        if ($negara_id <= 0) {
            // Get negara_id from province
            $provResult = $conn->query("SELECT negara_id FROM provinsi WHERE id = $provinsi_id");
            if ($prov = $provResult->fetch_assoc()) {
                $negara_id = $prov['negara_id'];
            } else {
                $negara_id = 1;
            }
        }
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama kota tidak boleh kosong!']);
            exit();
        }
        
        // Get next urutan within this province
        $count = $conn->query("SELECT COUNT(*) as cnt FROM kota WHERE provinsi_id = $provinsi_id")->fetch_assoc();
        $urutan = ($count['cnt'] ?? 0) + 1;
        $kode = generateKotaCode($conn, $urutan);
        
        $sql = "INSERT INTO kota (negara_id, provinsi_id, kode, nama) VALUES ($negara_id, $provinsi_id, '$kode', '$nama')";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Kota berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan kota: ' . $conn->error]);
        }
        break;
        
    case 'update':
        $id = (int)$_POST['id'];
        $provinsi_id = (int)$_POST['provinsi_id'];
        $negara_id = (int)($_POST['negara_id'] ?? 0);
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        
        if (empty($provinsi_id)) {
            echo json_encode(['success' => false, 'message' => 'Provinsi harus dipilih!']);
            exit();
        }
        
        if ($negara_id <= 0) {
            // Get negara_id from province
            $provResult = $conn->query("SELECT negara_id FROM provinsi WHERE id = $provinsi_id");
            if ($prov = $provResult->fetch_assoc()) {
                $negara_id = $prov['negara_id'];
            } else {
                $negara_id = 1;
            }
        }
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama kota tidak boleh kosong!']);
            exit();
        }
        
        // Keep existing kode when updating
        $sql = "UPDATE kota SET negara_id = $negara_id, provinsi_id = $provinsi_id, nama = '$nama' WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Kota berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui kota: ' . $conn->error]);
        }
        break;
        
    case 'delete':
        $id = (int)$_POST['id'];
        
        // Check if there are ranting using this city
        $check = $conn->query("SELECT COUNT(*) as cnt FROM ranting WHERE jenis = 'unit' AND id_kota = $id");
        $count = $check->fetch_assoc();
        
        if ($count['cnt'] > 0) {
            // Soft delete - toggle aktif status
            $current = $conn->query("SELECT aktif FROM kota WHERE id = $id")->fetch_assoc();
            $newStatus = $current['aktif'] == 1 ? 0 : 1;
            
            $statusText = $newStatus == 1 ? 'diaktifkan' : 'dinonaktifkan';
            
            // Also toggle aktif status for all ranting in this city
            $conn->query("UPDATE ranting SET aktif = $newStatus WHERE jenis = 'unit' AND id_kota = $id");
            
            $sql = "UPDATE kota SET aktif = $newStatus WHERE id = $id";
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => "Kota berhasil $statusText! (Unit ranting terkait juga $statusText)"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengubah status kota: ' . $conn->error]);
            }
        } else {
            // No ranting, just toggle status
            $current = $conn->query("SELECT aktif FROM kota WHERE id = $id")->fetch_assoc();
            $newStatus = $current['aktif'] == 1 ? 0 : 1;
            
            $statusText = $newStatus == 1 ? 'diaktifkan' : 'dinonaktifkan';
            
            $sql = "UPDATE kota SET aktif = $newStatus WHERE id = $id";
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => "Kota berhasil $statusText!"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengubah status kota: ' . $conn->error]);
            }
        }
        break;
        
    case 'reorder':
        $orders = $_POST['orders'] ?? [];
        $provinsi_id = (int)$_POST['provinsi_id'] ?? 0;
        
        if (empty($orders) || !is_array($orders)) {
            echo json_encode(['success' => false, 'message' => 'Data urutan tidak valid!']);
            exit();
        }
        
        $conn->query("START TRANSACTION");
        
        try {
            foreach ($orders as $item) {
                $id = (int)$item['id'];
                $kode = generateKotaCode($conn, $id);
                
                $conn->query("UPDATE kota SET kode = '$kode' WHERE id = $id");
            }
            
            $conn->query("COMMIT");
            echo json_encode(['success' => true, 'message' => 'Urutan kota berhasil diperbarui!']);
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate urutan: ' . $e->getMessage()]);
        }
        break;
        
    case 'get':
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT k.*, p.nama as nama_provinsi FROM kota k LEFT JOIN provinsi p ON k.provinsi_id = p.id WHERE k.id = $id");
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kota tidak ditemukan!']);
        }
        break;
        
    case 'get_by_provinsi':
        $provinsi_id = (int)$_GET['provinsi_id'];
        $result = $conn->query("SELECT * FROM kota WHERE provinsi_id = $provinsi_id AND aktif = 1 ORDER BY nama ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
}
