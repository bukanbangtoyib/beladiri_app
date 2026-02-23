<?php
/**
 * API untuk manajemen tingkatan
 * CRUD operations untuk tabel tingkatan
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
        $nama_tingkat = $conn->real_escape_string($_POST['nama_tingkat'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);
        
        if (empty($nama_tingkat)) {
            echo json_encode(['success' => false, 'message' => 'Nama tingkat tidak boleh kosong!']);
            exit();
        }
        
        $sql = "INSERT INTO tingkatan (nama_tingkat, urutan) VALUES ('$nama_tingkat', $urutan)";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Tingkat berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan tingkat: ' . $conn->error]);
        }
        break;
        
    case 'read':
        $result = $conn->query("SELECT * FROM tingkatan ORDER BY urutan ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Hitung jumlah anggota pada tingkat ini
            $count = $conn->query("SELECT COUNT(*) as cnt FROM anggota WHERE tingkat_id = " . $row['id'])->fetch_assoc();
            $row['jumlah_anggota'] = $count['cnt'];
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'update':
        $id = (int)$_POST['id'];
        $nama_tingkat = $conn->real_escape_string($_POST['nama_tingkat'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);
        
        if (empty($nama_tingkat)) {
            echo json_encode(['success' => false, 'message' => 'Nama tingkat tidak boleh kosong!']);
            exit();
        }
        
        $sql = "UPDATE tingkatan SET nama_tingkat = '$nama_tingkat', urutan = $urutan WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Tingkat berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui tingkat: ' . $conn->error]);
        }
        break;
        
    case 'delete':
        $id = (int)$_POST['id'];
        
        // Cek apakah ada anggota pada tingkat ini
        $check = $conn->query("SELECT COUNT(*) as cnt FROM anggota WHERE tingkat_id = $id");
        $count = $check->fetch_assoc();
        
        if ($count['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus tingkat ini karena ada ' . $count['cnt'] . ' anggota yang menggunakannya!']);
            exit();
        }
        
        // Cek apakah ada UKT peserta pada tingkat ini
        $check2 = $conn->query("SELECT COUNT(*) as cnt FROM ukt_peserta WHERE tingkat_dari_id = $id OR tingkat_ke_id = $id");
        $count2 = $check2->fetch_assoc();
        
        if ($count2['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus tingkat ini karena ada ' . $count2['cnt'] . ' data UKT yang menggunakannya!']);
            exit();
        }
        
        $sql = "DELETE FROM tingkatan WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Tingkat berhasil dihapus!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus tingkat: ' . $conn->error]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
}
