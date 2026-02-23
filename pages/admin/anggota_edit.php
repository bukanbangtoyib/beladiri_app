<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';
include '../../config/settings.php';

// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Helper function untuk format tanggal input
function formatDateInput($date) {
    if (empty($date) || $date === '0000-00-00' || $date === 'NULL') {
        return '';
    }
    // Coba parse sebagai yyyy-mm-dd (MySQL format)
    $timestamp = strtotime($date);
    if ($timestamp !== false && $timestamp > 0) {
        return date('d/m/Y', $timestamp);
    }
    // Coba parse sebagai dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        return $date; // Already in correct format
    }
    // Coba parse sebagai year saja
    if (preg_match('/^(\d{4})$/', $date, $matches)) {
        return $matches[1];
    }
    return '';
}

// Helper function untuk format no_anggota sesuai pengaturan
function formatNoAnggotaDisplay($no_anggota, $pengaturan_nomor) {
    // Parse no_anggota format: NNPPPKKK.RRR-YYYYXXX or variations
    // We need to extract components and rebuild based on settings
    
    // Default: return as-is if can't parse
    if (empty($no_anggota)) return $no_anggota;
    
    // Try to parse the format
    // Pattern: alphanumeric.alphanumeric-alphanumeric or variations
    $parts = [];
    
    // Check if it contains dot and dash (full format)
    // Allow alphanumeric characters for kode (e.g., "ID" for Indonesia)
    if (preg_match('/^([A-Za-z0-9]+)\.([A-Za-z0-9]+)-([A-Za-z0-9]+)$/', $no_anggota, $matches)) {
        // Format: NNNNNNNN.RRR-YYYYXXX
        $kode_full = $matches[1]; // NNPPPKKK or similar
        $ranting_kode = $matches[2]; // RRR
        $year_seq = $matches[3]; // YYYYXXX
    } elseif (preg_match('/^([A-Za-z0-9]+)-([A-Za-z0-9]+)$/', $no_anggota, $matches)) {
        // Format: RRR-YYYYXXX (no leading kode)
        $kode_full = '';
        $ranting_kode = $matches[1];
        $year_seq = $matches[2];
    } elseif (preg_match('/^([A-Za-z0-9]+)\.([A-Za-z0-9]+)$/', $no_anggota, $matches)) {
        // Format: NNNNNNNN.RRR (no year/seq)
        $kode_full = $matches[1];
        $ranting_kode = $matches[2];
        $year_seq = '';
    } else {
        // Just return as-is for other formats
        return $no_anggota;
    }
    
    // Extract kode components (assume 2+3+3 = 8 chars max)
    $negara_kode = '';
    $provinsi_kode = '';
    $kota_kode = '';
    
    if (strlen($kode_full) >= 2) {
        $negara_kode = substr($kode_full, 0, 2);
    }
    if (strlen($kode_full) >= 5) {
        $provinsi_kode = substr($kode_full, 2, 3);
    }
    if (strlen($kode_full) >= 8) {
        $kota_kode = substr($kode_full, 5, 3);
    }
    
    // Extract year and sequence
    $tahun = '';
    $urutan = '';
    if (strlen($year_seq) >= 4) {
        $tahun = substr($year_seq, 0, 4);
        $urutan = substr($year_seq, 4);
    }
    
    // Rebuild based on settings
    $result_parts = [];
    
    // Kode parts (negara, provinsi, kota) - combined without separator
    $kode_parts = [];
    if ($pengaturan_nomor['kode_negara'] ?? true) {
        $kode_parts[] = $negara_kode;
    }
    if ($pengaturan_nomor['kode_provinsi'] ?? true) {
        $kode_parts[] = $provinsi_kode;
    }
    if ($pengaturan_nomor['kode_kota'] ?? true) {
        $kode_parts[] = $kota_kode;
    }
    $kode_str = implode('', $kode_parts);
    
    // Ranting
    $ranting_str = '';
    if ($pengaturan_nomor['kode_ranting'] ?? true) {
        if (!empty($kode_str)) {
            $ranting_str = '.' . $ranting_kode;
        } else {
            $ranting_str = $ranting_kode;
        }
    }
    
    // Year and sequence
    $year_seq_str = '';
    $year_part = ($pengaturan_nomor['tahun_daftar'] ?? true) ? $tahun : '';
    $seq_part = ($pengaturan_nomor['urutan_daftar'] ?? true) ? $urutan : '';
    
    if (!empty($year_part) || !empty($seq_part)) {
        if (!empty($kode_str) || !empty($ranting_str)) {
            $year_seq_str = '-' . $year_part . $seq_part;
        } else {
            $year_seq_str = $year_part . $seq_part;
        }
    }
    
    return $kode_str . $ranting_str . $year_seq_str;
}

$id = (int)$_GET['id'];
$error = '';
$success = '';

$result = $conn->query("SELECT a.*, 
    r.kota_id, r.kode as ranting_kode,
    k.provinsi_id as kota_provinsi_id, k.kode as kota_kode, k.negara_id as kota_negara_id, 
    p.negara_id as prov_negara_id, p.kode as provinsi_kode,
    n.kode as negara_kode
    FROM anggota a 
    LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id 
    LEFT JOIN kota k ON r.kota_id = k.id 
    LEFT JOIN provinsi p ON k.provinsi_id = p.id 
    LEFT JOIN negara n ON p.negara_id = n.id 
    WHERE a.id = $id");
if ($result->num_rows == 0) {
    die("Anggota tidak ditemukan!");
}
$anggota = $result->fetch_assoc();

// Helper function untuk sanitasi nama [LAMA - TETAP SAMA]
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Use the full no_anggota from hidden field, not the formatted display
    $no_anggota = $conn->real_escape_string($_POST['no_anggota_full'] ?? $_POST['no_anggota']);
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $tempat_lahir = $conn->real_escape_string($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    
    // Process ranting_awal - either from dropdown or manual input
    $ranting_awal_id = !empty($_POST['ranting_awal_id_edit']) ? (int)$_POST['ranting_awal_id_edit'] : NULL;
    $ranting_awal_manual = $conn->real_escape_string($_POST['ranting_awal_manual_edit'] ?? '');
    
    $ranting_saat_ini_id = $_POST['ranting_saat_ini_id'] ?: NULL;
    $tingkat_id = $_POST['tingkat_id'] ?: NULL;
    $jenis_anggota = $_POST['jenis_anggota'];
    $tahun_bergabung = !empty($_POST['tahun_bergabung']) ? (int)$_POST['tahun_bergabung'] : NULL;
    $no_handphone = $conn->real_escape_string($_POST['no_handphone'] ?? '');
    
    // Process UKT format - if only year is entered, set to 02/07/YYYY
    // Also convert dd/mm/yyyy to yyyy-mm-dd for MySQL
    $ukt_terakhir = $_POST['ukt_terakhir'] ?? '';
    if (!empty($ukt_terakhir)) {
        $ukt_terakhir = trim($ukt_terakhir);
        // If only year (4 digits), convert to 02/07/YYYY
        if (preg_match('/^\d{4}$/', $ukt_terakhir)) {
            $ukt_terakhir = '02/07/' . $ukt_terakhir;
        }
        // Convert dd/mm/yyyy to yyyy-mm-dd for MySQL
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $ukt_terakhir, $matches)) {
            $ukt_terakhir = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
    } else {
        $ukt_terakhir = NULL;
    }
    
    // Validasi no_anggota jika berubah [BARU]
    if ($no_anggota != $anggota['no_anggota']) {
        $check = $conn->prepare("SELECT id FROM anggota WHERE no_anggota = ? AND id != ?");
        $check->bind_param("si", $no_anggota, $id);
        $check->execute();
        if ($check->num_rows > 0) {
            $error = "No Anggota sudah digunakan anggota lain!";
        }
    }
    
    // Handle foto upload / penggantian [BARU - FORMAT: ranting_nama_anggota.ext]
    $foto_path = $anggota['nama_foto']; // default: foto lama
    
    if (isset($_FILES['foto']) && $_FILES['foto']['size'] > 0) {
        $file = $_FILES['foto'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi
        $allowed_mimes = ['image/jpeg', 'image/png'];
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
        if (!in_array($mime, $allowed_mimes)) {
            $error = "Format foto harus JPG atau PNG!";
        } elseif ($file['size'] > 5242880) { // 5MB
            $error = "Ukuran foto maksimal 5MB!";
        } else {
            $upload_dir = '../../uploads/foto_anggota/';
            
            // Hapus foto lama jika ada
            if (!empty($anggota['nama_foto'])) {
                $old_file = $upload_dir . $anggota['nama_foto'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Dapatkan nama ranting dari data anggota yang ada
            $ranting_name = '';
            $ranting_id_for_photo = !empty($ranting_saat_ini_id) ? (int)$ranting_saat_ini_id : (int)$ranting_awal_id;
            if (!empty($ranting_id_for_photo)) {
                $rantingQuery = $conn->query("SELECT nama_ranting FROM ranting WHERE id = " . $ranting_id_for_photo);
                if ($ranting = $rantingQuery->fetch_assoc()) {
                    $ranting_name = sanitize_name($ranting['nama_ranting']) . '_';
                }
            }
            
            // Upload foto baru dengan format: ranting_nama_anggota.ext
            // Contoh: Tenggilis_Budi_Santoso.jpg
            $nama_clean = sanitize_name($nama_lengkap);
            $file_name = $ranting_name . $nama_clean . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $foto_path = $file_name;
            } else {
                $error = "Gagal upload foto!";
            }
        }
    }
    
    if (!$error) {
        // Process form data
        $sql = "UPDATE anggota SET 
                no_anggota = ?,
                nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, 
                jenis_kelamin = ?, ranting_awal_id = ?, ranting_awal_manual = ?, ranting_saat_ini_id = ?, tingkat_id = ?, 
                jenis_anggota = ?, tahun_bergabung = ?, no_handphone = ?,
                ukt_terakhir = ?, nama_foto = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Total 15 parameter: no_anggota, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, ranting_awal_id, ranting_awal_manual, ranting_saat_ini_id, tingkat_id, jenis_anggota, tahun_bergabung, no_handphone, ukt_terakhir, nama_foto, id
            $stmt->bind_param(
                "sssssissiiisiss",
                $no_anggota,
                $nama_lengkap, 
                $tempat_lahir, 
                $tanggal_lahir, 
                $jenis_kelamin,
                $ranting_awal_id,
                $ranting_awal_manual,
                $ranting_saat_ini_id, 
                $tingkat_id, 
                $jenis_anggota,
                $tahun_bergabung,
                $no_handphone,
                $ukt_terakhir,
                $foto_path,
                $id
            );
            
            if ($stmt->execute()) {
                // Update prestasi [BARU]
                if (!empty($_POST['prestasi_id'])) {
                    for ($i = 0; $i < count($_POST['prestasi_id']); $i++) {
                        if ($_POST['prestasi_id'][$i] != '') {
                            $pid = (int)$_POST['prestasi_id'][$i];
                            $event = $conn->real_escape_string($_POST['prestasi_event_name'][$i] ?? '');
                            $tgl = $_POST['prestasi_tanggal'][$i] ?? NULL;
                            $penyelenggara = $conn->real_escape_string($_POST['prestasi_penyelenggara'][$i] ?? '');
                            $kategori = $conn->real_escape_string($_POST['prestasi_kategori'][$i] ?? '');
                            $prestasi = $conn->real_escape_string($_POST['prestasi_prestasi_name'][$i] ?? '');
                            
                            if ($event) {
                                $conn->query("UPDATE prestasi SET 
                                            event_name = '$event',
                                            tanggal_pelaksanaan = '$tgl',
                                            penyelenggara = '$penyelenggara',
                                            kategori = '$kategori',
                                            prestasi = '$prestasi'
                                            WHERE id = $pid AND anggota_id = $id");
                            }
                        }
                    }
                }
                
                // Insert prestasi baru [BARU]
                if (!empty($_POST['new_prestasi_event'])) {
                    for ($i = 0; $i < count($_POST['new_prestasi_event']); $i++) {
                        if (!empty($_POST['new_prestasi_event'][$i])) {
                            $event = $conn->real_escape_string($_POST['new_prestasi_event'][$i]);
                            $tgl = $_POST['new_prestasi_tanggal'][$i] ?? NULL;
                            $penyelenggara = $conn->real_escape_string($_POST['new_prestasi_penyelenggara'][$i] ?? '');
                            $kategori = $conn->real_escape_string($_POST['new_prestasi_kategori'][$i] ?? '');
                            $prestasi = $conn->real_escape_string($_POST['new_prestasi_prestasi'][$i] ?? '');
                            
                            $conn->query("INSERT INTO prestasi (anggota_id, event_name, tanggal_pelaksanaan, penyelenggara, kategori, prestasi) 
                                        VALUES ($id, '$event', '$tgl', '$penyelenggara', '$kategori', '$prestasi')");
                        }
                    }
                }
                
                // Delete prestasi [BARU]
                if (!empty($_POST['delete_prestasi_ids'])) {
                    $ids = array_map('intval', explode(',', $_POST['delete_prestasi_ids']));
                    foreach ($ids as $pid) {
                        $conn->query("DELETE FROM prestasi WHERE id = $pid AND anggota_id = $id");
                    }
                }
                
                $success = "Data anggota berhasil diupdate!";
                header("refresh:2;url=anggota_detail.php?id=$id");
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error prepare: " . $conn->error;
        }
    }
}

$ranting_result = $conn->query("SELECT id, nama_ranting, kode FROM ranting ORDER BY nama_ranting");
$tingkatan_result = $conn->query("SELECT id, nama_tingkat FROM tingkatan ORDER BY urutan");
$jenis_result = $conn->query("SELECT id, nama_jenis FROM jenis_anggota ORDER BY id");
$prestasi_result = $conn->query("SELECT * FROM prestasi WHERE anggota_id = $id ORDER BY tanggal_pelaksanaan DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Anggota - Sistem Beladiri</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
        }
        
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 26px;
        }
        
        .form-subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="date"],
        input[type="file"],
        input[type="number"],
        input[type="tel"],
        select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.3s;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .required {
            color: #dc3545;
            font-weight: 700;
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
            font-style: italic;
        }
        
        hr {
            margin: 40px 0;
            border: none;
            border-top: 2px solid #f0f0f0;
        }
        
        h3 {
            color: #333;
            margin-bottom: 25px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
        }
        
        .radio-group {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .radio-option label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 0;
        }
        
        .conditional-field {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 3px solid #667eea;
            border-radius: 4px;
        }
        
        .conditional-field.show {
            display: block;
        }
        
        .conditional-field label {
            color: #667eea;
        }
        
        .photo-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .photo-preview {
            margin-bottom: 15px;
        }
        
        .photo-preview img {
            max-width: 200px;
            height: 250px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .no-photo {
            display: inline-block;
            width: 200px;
            height: 250px;
            background: #e0e0e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #999;
        }
        
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-add-prestasi {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 12px;
            margin-top: 15px;
        }
        
        .btn-add-prestasi:hover {
            background: #218838;
        }
        
        .btn-remove-prestasi {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .btn-remove-prestasi:hover {
            background: #c82333;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #c00;
            border-left-color: #dc3545;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #060;
            border-left-color: #28a745;
        }
        
        /* Prestasi Section Styling */
        .prestasi-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .prestasi-item {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .prestasi-item:last-child {
            margin-bottom: 0;
        }
        
        .prestasi-item.template {
            display: none;
        }
        
        .prestasi-item.marked-delete {
            opacity: 0.5;
            background: #fff5f5;
        }
    </style>
</head>
<body>
    <?php renderNavbar('✏️ Edit Anggota'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Edit Data Anggota</h1>
            <p class="form-subtitle">Silahkan isi semua kolom yang bertanda bintang merah (*)</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <!-- Foto Section [LAMA - TETAP SAMA] -->
                <h3>📸 Foto Profil</h3>
                
                <div class="photo-section">
                    <div class="photo-preview">
                        <?php 
                        $foto_path = '../../uploads/foto_anggota/' . $anggota['nama_foto'];
                        if (!empty($anggota['nama_foto']) && file_exists($foto_path)): 
                        ?>
                            <img src="<?php echo $foto_path; ?>" alt="Foto Profil">
                        <?php else: ?>
                            <div class="no-photo">📷</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Ganti Foto Profil</label>
                        <input type="file" name="foto" accept="image/*">
                        <div class="form-hint">Format: JPG, PNG (Ukuran maksimal 5MB) - Kosongi jika tidak ingin mengubah | Nama file akan menjadi: NoAnggota_NamaLengkap.jpg</div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Data Organisasi -->
                <h3>🏢 Data Organisasi</h3>
                
                <!-- Cascade Filter: Negara -> Provinsi -> Kota -> Ranting -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Negara</label>
                        <select name="filter_negara_edit" id="filter_negara_edit" onchange="updateProvinsiFormEdit()">
                            <option value="">-- Pilih Negara --</option>
                            <?php
                            // Get all negara
                            $negara_all = $conn->query("SELECT * FROM negara WHERE aktif = 1 ORDER BY nama ASC");
                            while ($row = $negara_all->fetch_assoc()):
                            ?>
                                <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>" <?php echo ($anggota['kota_negara_id'] ?? '') == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Negara</label>
                        <input type="text" id="kode_negara_display_edit" readonly placeholder="-" value="<?php echo htmlspecialchars($anggota['negara_kode'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Provinsi</label>
                        <select name="filter_provinsi_edit" id="filter_provinsi_edit" onchange="updateKotaFormEdit()" <?php echo empty($anggota['kota_negara_id']) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php
                            // Get provinces for the current negara
                            if (!empty($anggota['kota_negara_id'])) {
                                $negara_id = $anggota['kota_negara_id'];
                                $provinsi_all = $conn->query("SELECT * FROM provinsi WHERE negara_id = $negara_id AND aktif = 1 ORDER BY nama ASC");
                                while ($row = $provinsi_all->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>" <?php echo ($anggota['kota_provinsi_id'] ?? '') == $row['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama']); ?>
                                    </option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Provinsi</label>
                        <input type="text" id="kode_provinsi_display_edit" readonly placeholder="-" value="<?php echo htmlspecialchars($anggota['provinsi_kode'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Kota/Kabupaten</label>
                        <select name="filter_kota_edit" id="filter_kota_edit" onchange="updateRantingFormEdit()" <?php echo empty($anggota['kota_provinsi_id']) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Kota --</option>
                            <?php
                            // Get kota for the current provinsi
                            if (!empty($anggota['kota_provinsi_id'])) {
                                $provinsi_id = $anggota['kota_provinsi_id'];
                                $kota_all = $conn->query("SELECT * FROM kota WHERE provinsi_id = $provinsi_id AND aktif = 1 ORDER BY nama ASC");
                                while ($row = $kota_all->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>" <?php echo ($anggota['kota_id'] ?? '') == $row['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama']); ?>
                                    </option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Kota</label>
                        <input type="text" id="kode_kota_display_edit" readonly placeholder="-" value="<?php echo htmlspecialchars($anggota['kota_kode'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Unit/Ranting Saat Ini <span class="required">*</span></label>
                        <select name="ranting_saat_ini_id" id="ranting_saat_ini_id_edit" required onchange="updateRantingKodeEdit()">
                            <option value="">-- Pilih Ranting --</option>
                            <?php 
                            $ranting_result->data_seek(0);
                            while ($row = $ranting_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>" <?php echo $anggota['ranting_saat_ini_id'] == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama_ranting']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Ranting</label>
                        <input type="text" id="kode_ranting_display_edit" readonly placeholder="-" value="<?php echo htmlspecialchars($anggota['ranting_kode'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Unit/Ranting Awal Masuk <span class="required">*</span></label>
                    
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="ranting_database_edit" name="ranting_awal_pilihan_edit" value="database" <?php echo (empty($anggota['ranting_awal_manual']) || !empty($anggota['ranting_awal_id'])) ? 'checked' : ''; ?> onchange="toggleRantingAwalEdit()">
                            <label for="ranting_database_edit">Pilih dari Database</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="ranting_manual_edit" name="ranting_awal_pilihan_edit" value="manual" <?php echo !empty($anggota['ranting_awal_manual']) ? 'checked' : ''; ?> onchange="toggleRantingAwalEdit()">
                            <label for="ranting_manual_edit">Input Manual</label>
                        </div>
                    </div>
                    
                    <div id="ranting_awal_select_edit" class="form-group" <?php echo !empty($anggota['ranting_awal_manual']) ? 'style="display:none;"' : ''; ?>>
                        <select name="ranting_awal_id_edit">
                            <option value="">-- Pilih Unit/Ranting --</option>
                            <?php 
                            $ranting_result->data_seek(0);
                            while ($row = $ranting_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>" <?php echo ($anggota['ranting_awal_id'] == $row['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama_ranting']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">Pilih Unit/Ranting yang tersedia di database</div>
                    </div>
                    
                    <div id="ranting_awal_manual_edit" class="conditional-field" style="<?php echo !empty($anggota['ranting_awal_manual']) ? 'display:block;' : 'display:none;'; ?>">
                        <input type="text" name="ranting_awal_manual_edit" value="<?php echo htmlspecialchars($anggota['ranting_awal_manual'] ?? ''); ?>" placeholder="Masukkan nama Unit/Ranting">
                        <div class="form-hint">Masukkan nama Unit/Ranting secara manual</div>
                    </div>
                </div>
                
                <div class="form-row">                                       
                    <div class="form-group">
                        <label>Tingkat <span class="required">*</span></label>
                        <select name="tingkat_id" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <?php $tingkatan_result->data_seek(0); while ($row = $tingkatan_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $anggota['tingkat_id'] == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nama_tingkat']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">Pilih dari 13 tingkatan resmi</div>
                    </div>

                    <div class="form-group">
                        <label>Jenis Anggota <span class="required">*</span></label>
                        <select name="jenis_anggota" required>
                            <option value="">-- Pilih Jenis Anggota --</option>
                            <?php
                            $current_jenis = isset($anggota['jenis_anggota']) ? $anggota['jenis_anggota'] : '';
                            $jenis_query = $conn->query("SELECT id, nama_jenis FROM jenis_anggota ORDER BY id");
                            if ($jenis_query && $jenis_query->num_rows > 0) {
                                while ($row = $jenis_query->fetch_assoc()) {
                                    $isSelected = ($current_jenis == $row['id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $isSelected . '>' . htmlspecialchars($row['nama_jenis']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <div class="form-hint">Tentukan status anggota</div>
                    </div>                    
                </div>
                
                <div class="form-row">                    
                    <div class="form-group">
                        <label>Tahun Bergabung <span class="required">*</span></label>
                        <input type="number" name="tahun_bergabung" min="1900" max="2100" required 
                            value="<?php echo htmlspecialchars($anggota['tahun_bergabung'] ?? ''); ?>"
                            placeholder="Contoh: 2024">
                        <div class="form-hint">Tahun anggota bergabung</div>
                    </div>

                    <div class="form-group">
                        <label>UKT Terakhir</label>
                        <input type="text" name="ukt_terakhir" 
                            value="<?php echo isset($anggota) && !empty($anggota['ukt_terakhir']) && $anggota['ukt_terakhir'] != '0000-00-00' ? formatDateInput($anggota['ukt_terakhir']) : ''; ?>"
                            placeholder="Format: dd/mm/yyyy atau yyyy">
                        <div class="form-hint">Format: 15/07/2024 atau 2024</div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Data Pribadi -->
                <h3>📋 Data Pribadi</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>No Anggota <span class="required">*</span></label>
                        <input type="text" name="no_anggota" id="no_anggota_display_edit" value="<?php echo formatNoAnggotaDisplay($anggota['no_anggota'], $pengaturan_nomor); ?>" required readonly style="background-color: #e0e0e0;">
                        <input type="hidden" name="no_anggota_full" value="<?php echo $anggota['no_anggota']; ?>">
                        <div class="form-hint">Nomor anggota otomatis, tidak dapat diubah. Format mengikuti pengaturan sistem.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($anggota['nama_lengkap']); ?>" required placeholder="Masukkan nama lengkap">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" value="<?php echo htmlspecialchars($anggota['tempat_lahir']); ?>" required placeholder="Contoh: Jakarta">
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" value="<?php echo $anggota['tanggal_lahir']; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Kelamin <span class="required">*</span></label>
                        <select name="jenis_kelamin" required>
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="L" <?php echo $anggota['jenis_kelamin'] == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="P" <?php echo $anggota['jenis_kelamin'] == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>No. Handphone</label>
                        <input type="tel" name="no_handphone" 
                            value="<?php echo htmlspecialchars($anggota['no_handphone'] ?? ''); ?>"
                            pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');" 
                            placeholder="Contoh: 08xxxxxxxxxx">
                        <div class="form-hint">Nomor telepon yang dapat dihubungi</div>
                    </div>
                </div>
                
                <hr>               
                                
                <!-- Prestasi Section -->
                <h3>🏆 Prestasi yang Diraih (Opsional)</h3>
                
                <p class="form-hint" style="margin-bottom: 20px;">Kelola prestasi yang diraih anggota ini. Anda dapat menambahkan lebih dari satu prestasi.</p>
                
                <div class="prestasi-container">
                    <div id="prestasiList">
                        <?php 
                        if ($prestasi_result && $prestasi_result->num_rows > 0):
                            while ($p = $prestasi_result->fetch_assoc()):
                        ?>
                        <div class="prestasi-item" data-prestasi-id="<?php echo $p['id']; ?>">
                            <input type="hidden" name="prestasi_id[]" value="<?php echo $p['id']; ?>">
                            
                            <div class="form-row full">
                                <div class="form-group">
                                    <label>Nama Event</label>
                                    <input type="text" name="prestasi_event_name[]" value="<?php echo htmlspecialchars($p['event_name']); ?>" placeholder="Contoh: Kejuaraan Nasional">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Tanggal Pelaksanaan</label>
                                    <input type="date" name="prestasi_tanggal[]" value="<?php echo $p['tanggal_pelaksanaan'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Penyelenggara</label>
                                    <input type="text" name="prestasi_penyelenggara[]" value="<?php echo htmlspecialchars($p['penyelenggara'] ?? ''); ?>" placeholder="Contoh: KONI, Pengprov, dll">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Kategori yang Diikuti</label>
                                    <input type="text" name="prestasi_kategori[]" value="<?php echo htmlspecialchars($p['kategori'] ?? ''); ?>" placeholder="Contoh: Putra -60kg">
                                </div>
                                
                                <div class="form-group">
                                    <label>Prestasi</label>
                                    <input type="text" name="prestasi_prestasi_name[]" value="<?php echo htmlspecialchars($p['prestasi'] ?? ''); ?>" placeholder="Contoh: Juara 1, Juara 2, dll">
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-remove-prestasi" onclick="markPrestasi(this)">🗑️ Tandai Hapus</button>
                        </div>
                        <?php 
                            endwhile;
                        endif;
                        ?>
                    </div>
                    <button type="button" class="btn btn-add-prestasi" onclick="addPrestasi()">+ Tambah Prestasi</button>
                </div>
                
                <!-- Template Prestasi Baru -->
                <div class="prestasi-item template" id="prestasiTemplate">
                    <div class="form-row full">
                        <div class="form-group">
                            <label>Nama Event</label>
                            <input type="text" name="new_prestasi_event[]" placeholder="Contoh: Kejuaraan Nasional">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Pelaksanaan</label>
                            <input type="date" name="new_prestasi_tanggal[]">
                        </div>
                        
                        <div class="form-group">
                            <label>Penyelenggara</label>
                            <input type="text" name="new_prestasi_penyelenggara[]" placeholder="Contoh: KONI, Pengprov, dll">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori yang Diikuti</label>
                            <input type="text" name="new_prestasi_kategori[]" placeholder="Contoh: Putra -60kg">
                        </div>
                        
                        <div class="form-group">
                            <label>Prestasi</label>
                            <input type="text" name="new_prestasi_prestasi[]" placeholder="Contoh: Juara 1, Juara 2, dll">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-remove-prestasi" onclick="this.parentElement.remove()">🗑️ Hapus Prestasi</button>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="anggota_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
                
                <input type="hidden" id="deletePrestasiIds" name="delete_prestasi_ids" value="">
            </form>
        </div>
    </div>
    
    <script>
        let deletePrestasiIds = [];
        
        // Cascade functions for Negara -> Provinsi -> Kota -> Ranting
        function updateProvinsiFormEdit() {
            const negaraSelect = document.getElementById('filter_negara_edit');
            const provinsiSelect = document.getElementById('filter_provinsi_edit');
            const kotaSelect = document.getElementById('filter_kota_edit');
            const rantingSelect = document.getElementById('ranting_awal_select_edit').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id_edit');
            
            const negaraId = negaraSelect.value;
            
            // Reset dropdowns
            provinsiSelect.innerHTML = '<option value="">-- Pilih Provinsi --</option>';
            kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
            
            // Reset kode displays
            document.getElementById('kode_negara_display_edit').value = '';
            document.getElementById('kode_provinsi_display_edit').value = '';
            document.getElementById('kode_kota_display_edit').value = '';
            document.getElementById('kode_ranting_display_edit').value = '';
            
            // Reset ranting dropdowns
            if (rantingSelect) rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
            if (rantingSaatIniSelect) rantingSaatIniSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting Saat Ini --</option>';
            
            if (negaraId === '') {
                provinsiSelect.disabled = true;
                kotaSelect.disabled = true;
                return;
            }
            
            // Show negara kode
            const negaraOption = negaraSelect.options[negaraSelect.selectedIndex];
            const negaraKode = negaraOption.getAttribute('data-kode') || '';
            document.getElementById('kode_negara_display_edit').value = negaraKode;
            
            provinsiSelect.disabled = false;
            
            // Fetch provinces by negara
            fetch('../../api/manage_provinsi.php?action=get_by_negara&id_negara=' + negaraId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(prov => {
                            const option = document.createElement('option');
                            option.value = prov.id;
                            option.textContent = (prov.kode || '000') + ' - ' + prov.nama;
                            option.setAttribute('data-kode', prov.kode || '000');
                            provinsiSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function updateKotaFormEdit() {
            const provinsiSelect = document.getElementById('filter_provinsi_edit');
            const kotaSelect = document.getElementById('filter_kota_edit');
            const rantingSelect = document.getElementById('ranting_awal_select_edit').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id_edit');
            
            const provinsiId = provinsiSelect.value;
            
            // Reset dropdown
            kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
            
            // Reset kode displays
            document.getElementById('kode_provinsi_display_edit').value = '';
            document.getElementById('kode_kota_display_edit').value = '';
            document.getElementById('kode_ranting_display_edit').value = '';
            
            // Reset ranting dropdowns
            if (rantingSelect) rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
            if (rantingSaatIniSelect) rantingSaatIniSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting Saat Ini --</option>';
            
            if (provinsiId === '') {
                kotaSelect.disabled = true;
                return;
            }
            
            // Show province kode
            const provOption = provinsiSelect.options[provinsiSelect.selectedIndex];
            const provKode = provOption.getAttribute('data-kode') || '';
            document.getElementById('kode_provinsi_display_edit').value = provKode;
            
            kotaSelect.disabled = false;
            
            // Fetch cities by province
            fetch('../../api/manage_kota.php?action=get_by_provinsi&provinsi_id=' + provinsiId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(kota => {
                            const option = document.createElement('option');
                            option.value = kota.id;
                            option.textContent = (kota.kode || '000') + ' - ' + kota.nama;
                            option.setAttribute('data-kode', kota.kode || '000');
                            kotaSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function updateRantingFormEdit() {
            const kotaSelect = document.getElementById('filter_kota_edit');
            const rantingSelect = document.getElementById('ranting_awal_select_edit').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id_edit');
            
            const kotaId = kotaSelect.value;
            
            // Reset ranting dropdowns
            if (rantingSelect) rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
            if (rantingSaatIniSelect) rantingSaatIniSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting Saat Ini --</option>';
            
            // Reset kode displays
            document.getElementById('kode_kota_display_edit').value = '';
            document.getElementById('kode_ranting_display_edit').value = '';
            
            if (kotaId === '') {
                return;
            }
            
            // Show kota kode
            const kotaOption = kotaSelect.options[kotaSelect.selectedIndex];
            const kotaKode = kotaOption.getAttribute('data-kode') || '';
            document.getElementById('kode_kota_display_edit').value = kotaKode;
            
            // Fetch ranting by kota
            fetch('../../api/get_ranting.php?kota_id=' + kotaId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(ranting => {
                            const rantingKode = ranting.kode || '001';
                            const rantingText = rantingKode + ' - ' + ranting.nama_ranting;
                            
                            const option1 = document.createElement('option');
                            option1.value = ranting.id;
                            option1.textContent = rantingText;
                            option1.setAttribute('data-kode', rantingKode);
                            rantingSelect.appendChild(option1);
                            
                            if (rantingSaatIniSelect) {
                                const option2 = document.createElement('option');
                                option2.value = ranting.id;
                                option2.textContent = rantingText;
                                option2.setAttribute('data-kode', rantingKode);
                                rantingSaatIniSelect.appendChild(option2);
                            }
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Function to update ranting kode display
        function updateRantingKodeEdit() {
            const rantingSelect = document.getElementById('ranting_saat_ini_id_edit');
            const rantingId = rantingSelect.value;
            
            if (rantingId === '') {
                document.getElementById('kode_ranting_display_edit').value = '';
                return;
            }
            
            const rantingOption = rantingSelect.options[rantingSelect.selectedIndex];
            const rantingKode = rantingOption.getAttribute('data-kode') || '';
            document.getElementById('kode_ranting_display_edit').value = rantingKode;
        }
        
        function toggleRantingAwalEdit() {
            const databaseOption = document.getElementById('ranting_database_edit');
            const selectField = document.getElementById('ranting_awal_select_edit');
            const manualField = document.getElementById('ranting_awal_manual_edit');
            
            if (databaseOption.checked) {
                selectField.style.display = 'block';
                manualField.classList.remove('show');
                manualField.style.display = 'none';
                document.querySelector('input[name="ranting_awal_manual_edit"]').value = '';
            } else {
                selectField.style.display = 'none';
                manualField.classList.add('show');
                manualField.style.display = 'block';
                document.querySelector('select[name="ranting_awal_id_edit"]').value = '';
            }
        }
        
        function markPrestasi(btn) {
            const item = btn.parentElement;
            const prestasiId = item.getAttribute('data-prestasi-id');
            
            if (item.classList.contains('marked-delete')) {
                // Batalkan hapus
                item.classList.remove('marked-delete');
                deletePrestasiIds = deletePrestasiIds.filter(x => x != prestasiId);
            } else {
                // Tandai untuk hapus
                item.classList.add('marked-delete');
                deletePrestasiIds.push(prestasiId);
            }
            
            document.getElementById('deletePrestasiIds').value = deletePrestasiIds.join(',');
        }
        
        function addPrestasi() {
            const template = document.getElementById('prestasiTemplate').cloneNode(true);
            template.classList.remove('template');
            template.removeAttribute('id');
            document.getElementById('prestasiList').appendChild(template);
        }
        
        document.getElementById('editForm').addEventListener('submit', function() {
            document.getElementById('deletePrestasiIds').value = deletePrestasiIds.join(',');
        });
        
        // UKT date format validation
        document.addEventListener('DOMContentLoaded', function() {
            const uktInputs = document.querySelectorAll('input[name="ukt_terakhir"]');
            
            uktInputs.forEach(uktInput => {
                uktInput.addEventListener('blur', function() {
                    if (this.value.trim() === '') return;
                    
                    const input = this.value.trim();
                    let parsedDate = null;
                    
                    // Format dd/mm/yyyy
                    if (/^\d{2}\/\d{2}\/\d{4}$/.test(input)) {
                        const parts = input.split('/');
                        const day = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10);
                        const year = parseInt(parts[2], 10);
                        
                        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
                            parsedDate = input;
                        }
                    }
                    // Format yyyy (tahun saja)
                    else if (/^\d{4}$/.test(input)) {
                        const year = input;
                        parsedDate = '02/' + '07/' + year;
                        this.value = parsedDate;
                    }
                    // Format yyyy-mm-dd
                    else if (/^\d{4}-\d{2}-\d{2}$/.test(input)) {
                        const date = new Date(input);
                        parsedDate = String(date.getDate()).padStart(2, '0') + '/' + 
                                    String(date.getMonth() + 1).padStart(2, '0') + '/' + 
                                    date.getFullYear();
                        this.value = parsedDate;
                    }
                    else {
                        this.value = '';
                        alert('Format tidak valid! Gunakan: dd/mm/yyyy atau yyyy');
                    }
                });
            });
        });
    </script>
</body>
</html>