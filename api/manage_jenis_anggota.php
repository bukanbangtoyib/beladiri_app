<?php
/**
 * API untuk manajemen jenis anggota
 * CRUD operations untuk tabel jenis_anggota
 */

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit();
}

include dirname(dirname(__FILE__)) . '/config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        $nama_jenis = $conn->real_escape_string($_POST['nama_jenis'] ?? '');
        
        if (empty($nama_jenis)) {
            echo json_encode(['success' => false, 'message' => 'Nama jenis tidak boleh kosong!']);
            exit();
        }
        
        $sql = "INSERT INTO jenis_anggota (nama_jenis) VALUES ('$nama_jenis')";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Jenis anggota berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan jenis: ' . $conn->error]);
        }
        break;
        
    case 'read':
        $result = $conn->query("SELECT * FROM jenis_anggota ORDER BY id ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Hitung jumlah anggota dengan jenis ini
            $count = $conn->query("SELECT COUNT(*) as cnt FROM anggota WHERE jenis_anggota = " . (int)$row['id'])->fetch_assoc();
            $row['jumlah_anggota'] = $count['cnt'];
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'update':
        $id = (int)$_POST['id'];
        $nama_jenis_baru = $conn->real_escape_string($_POST['nama_jenis'] ?? '');
        
        if (empty($nama_jenis_baru)) {
            echo json_encode(['success' => false, 'message' => 'Nama jenis tidak boleh kosong!']);
            exit();
        }
        
        // Update tabel jenis_anggota
        $sql = "UPDATE jenis_anggota SET nama_jenis = '$nama_jenis_baru' WHERE id = $id";
        if ($conn->query($sql)) {
            // No need to update anggota table since we're using ID now
            echo json_encode(['success' => true, 'message' => 'Jenis anggota berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jenis: ' . $conn->error]);
        }
        break;
        
    case 'delete':
        $id = (int)$_POST['id'];
        
        // Cek apakah ada anggota dengan jenis ini
        $check = $conn->query("SELECT COUNT(*) as cnt FROM anggota WHERE jenis_anggota = $id");
        $count = $check->fetch_assoc();
        
        if ($count['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus jenis ini karena ada ' . $count['cnt'] . ' anggota yang menggunakannya!']);
            exit();
        }
        
        $sql = "DELETE FROM jenis_anggota WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Jenis anggota berhasil dihapus!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus jenis: ' . $conn->error]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
}
