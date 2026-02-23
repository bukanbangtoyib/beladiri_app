<?php
/**
 * API untuk manajemen kode unit/ranting
 * CRUD operations untuk tabel ranting (jenis = 'unit')
 */

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit();
}

include dirname(dirname(__FILE__)) . '/config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Helper function untuk generate kode ranting (3-digit zero-padded, per kota)
 */
function generateRantingCode($conn, $urutan) {
    return str_pad($urutan, 3, '0', STR_PAD_LEFT);
}

switch ($action) {
    case 'list':
        $id_kota = (int)($_GET['id_kota'] ?? 0);
        
        if ($id_kota > 0) {
            $result = $conn->query("SELECT r.*, k.nama as nama_kota FROM ranting r LEFT JOIN kota k ON r.id_kota = k.id WHERE r.jenis = 'unit' AND r.id_kota = $id_kota ORDER BY r.urutan ASC");
        } else {
            $result = $conn->query("SELECT r.*, k.nama as nama_kota, p.nama as nama_provinsi FROM ranting r LEFT JOIN kota k ON r.id_kota = k.id LEFT JOIN provinsi p ON k.provinsi_id = p.id WHERE r.jenis = 'unit' ORDER BY p.nama ASC, k.nama ASC, r.urutan ASC");
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'add':
        $id_kota = (int)$_POST['id_kota'];
        $negara_id = (int)($_POST['negara_id'] ?? 0);
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        
        if (empty($id_kota)) {
            echo json_encode(['success' => false, 'message' => 'Kota harus dipilih!']);
            exit();
        }
        
        if ($negara_id <= 0) {
            // Get negara_id from kota
            $kotaResult = $conn->query("SELECT negara_id FROM kota WHERE id = $id_kota");
            if ($kota = $kotaResult->fetch_assoc()) {
                $negara_id = $kota['negara_id'];
            } else {
                $negara_id = 1;
            }
        }
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama unit/ranting tidak boleh kosong!']);
            exit();
        }
        
        // Get next urutan within this city
        $maxUrutan = $conn->query("SELECT MAX(urutan) as max_urutan FROM ranting WHERE jenis = 'unit' AND id_kota = $id_kota")->fetch_assoc();
        $urutan = ($maxUrutan['max_urutan'] ?? 0) + 1;
        $kode = generateRantingCode($conn, $urutan);
        
        $sql = "INSERT INTO ranting (jenis, negara_id, id_kota, kode, nama, urutan) VALUES ('unit', $negara_id, $id_kota, '$kode', '$nama', $urutan)";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Unit/Ranting berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan unit/ranting: ' . $conn->error]);
        }
        break;
        
    case 'update':
        $id = (int)$_POST['id'];
        $id_kota = (int)$_POST['id_kota'];
        $negara_id = (int)($_POST['negara_id'] ?? 0);
        $nama = $conn->real_escape_string($_POST['nama'] ?? '');
        
        if (empty($id_kota)) {
            echo json_encode(['success' => false, 'message' => 'Kota harus dipilih!']);
            exit();
        }
        
        if ($negara_id <= 0) {
            // Get negara_id from kota
            $kotaResult = $conn->query("SELECT negara_id FROM kota WHERE id = $id_kota");
            if ($kota = $kotaResult->fetch_assoc()) {
                $negara_id = $kota['negara_id'];
            } else {
                $negara_id = 1;
            }
        }
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama unit/ranting tidak boleh kosong!']);
            exit();
        }
        
        // Get current urutan for code preservation
        $current = $conn->query("SELECT urutan FROM ranting WHERE jenis = 'unit' AND id = $id")->fetch_assoc();
        $urutan = $current['urutan'];
        $kode = generateRantingCode($conn, $urutan);
        
        $sql = "UPDATE ranting SET negara_id = $negara_id, id_kota = $id_kota, kode = '$kode', nama = '$nama' WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Unit/Ranting berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui unit/ranting: ' . $conn->error]);
        }
        break;
        
        if (empty($id_kota)) {
            echo json_encode(['success' => false, 'message' => 'Kota harus dipilih!']);
            exit();
        }
        
        if (empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'Nama unit/ranting tidak boleh kosong!']);
            exit();
        }
        
        // Get current urutan for code preservation
        $current = $conn->query("SELECT urutan FROM ranting WHERE jenis = 'unit' AND id = $id")->fetch_assoc();
        $urutan = $current['urutan'];
        $kode = generateRantingCode($conn, $urutan);
        
        $sql = "UPDATE ranting SET id_kota = $id_kota, kode = '$kode', nama = '$nama' WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Unit/Ranting berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui unit/ranting: ' . $conn->error]);
        }
        break;
        
    case 'delete':
        $id = (int)$_POST['id'];
        
        // Soft delete - toggle aktif status
        $current = $conn->query("SELECT aktif FROM ranting WHERE id = $id")->fetch_assoc();
        $newStatus = $current['aktif'] == 1 ? 0 : 1;
        
        $statusText = $newStatus == 1 ? 'diaktifkan' : 'dinonaktifkan';
        
        $sql = "UPDATE ranting SET aktif = $newStatus WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => "Unit/Ranting berhasil $statusText!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah status unit/ranting: ' . $conn->error]);
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
                $kode = generateRantingCode($conn, $urutan);
                
                $conn->query("UPDATE ranting SET urutan = $urutan, kode = '$kode' WHERE id = $id");
            }
            
            $conn->query("COMMIT");
            echo json_encode(['success' => true, 'message' => 'Urutan unit/ranting berhasil diperbarui!']);
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate urutan: ' . $e->getMessage()]);
        }
        break;
        
    case 'get':
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT r.*, k.nama as nama_kota, p.nama as nama_provinsi FROM ranting r LEFT JOIN kota k ON r.id_kota = k.id LEFT JOIN provinsi p ON k.provinsi_id = p.id WHERE r.jenis = 'unit' AND r.id = $id");
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unit/Ranting tidak ditemukan!']);
        }
        break;
        
    case 'get_by_kota':
        $id_kota = (int)$_GET['id_kota'];
        $result = $conn->query("SELECT * FROM ranting WHERE jenis = 'unit' AND id_kota = $id_kota AND aktif = 1 ORDER BY nama ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'get_by_provinsi':
        $provinsi_id = (int)$_GET['provinsi_id'];
        $result = $conn->query("SELECT r.*, k.nama as nama_kota FROM ranting r LEFT JOIN kota k ON r.id_kota = k.id WHERE r.jenis = 'unit' AND k.provinsi_id = $provinsi_id AND r.aktif = 1 ORDER BY k.nama ASC, r.urutan ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
}
