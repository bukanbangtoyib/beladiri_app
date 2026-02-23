<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

if ($_SESSION['role'] != 'admin') {
    die("Akses ditolak!");
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

$error = '';
$success = '';

// Helper function untuk sanitasi nama
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_anggota = $conn->real_escape_string($_POST['no_anggota']);
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $tempat_lahir = $conn->real_escape_string($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $ranting_awal_id = !empty($_POST['ranting_awal_id']) ? (int)$_POST['ranting_awal_id'] : NULL;
    $ranting_awal_manual = $conn->real_escape_string($_POST['ranting_awal_manual'] ?? '');
    $ranting_saat_ini_id = !empty($_POST['ranting_saat_ini_id']) ? (int)$_POST['ranting_saat_ini_id'] : NULL;
    $tingkat_id = !empty($_POST['tingkat_id']) ? (int)$_POST['tingkat_id'] : NULL;
    $jenis_anggota = $_POST['jenis_anggota'];
    $tahun_bergabung = !empty($_POST['tahun_bergabung']) ? (int)$_POST['tahun_bergabung'] : NULL;
    $no_handphone = $conn->real_escape_string($_POST['no_handphone'] ?? '');
    
    // Convert dd/mm/yyyy to yyyy-mm-dd for MySQL
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
    
    // Generate no_anggota server-side
    if (empty($no_anggota) && !empty($ranting_awal_id) && !empty($tahun_bergabung)) {
        // Get ranting info to find kota, provinsi, negara
        $rantingQuery = $conn->query("SELECT r.kode as ranting_kode, k.kode as kota_kode, k.provinsi_id, p.kode as prov_kode, p.negara_id, n.kode as negara_kode 
            FROM ranting r 
            JOIN kota k ON r.kota_id = k.id 
            JOIN provinsi p ON k.provinsi_id = p.id 
            JOIN negara n ON p.negara_id = n.id 
            WHERE r.id = $ranting_awal_id");
        if ($ranting = $rantingQuery->fetch_assoc()) {
            $negara_kode = str_pad($ranting['negara_kode'] ?? '0', 2, '0', STR_PAD_LEFT);
            $prov_kode = str_pad($ranting['prov_kode'] ?? '0', 3, '0', STR_PAD_LEFT);
            $kota_kode = str_pad($ranting['kota_kode'] ?? '0', 3, '0', STR_PAD_LEFT);
            $ranting_kode = str_pad($ranting['ranting_kode'] ?? '0', 3, '0', STR_PAD_LEFT);
            
            // Get next sequence for the year
            $seqQuery = $conn->query("SELECT MAX(CAST(RIGHT(no_anggota, 3) AS UNSIGNED)) as max_urut FROM anggota WHERE no_anggota LIKE '%-" . $tahun_bergabung . "%'");
            $max_urut = ($seq = $seqQuery->fetch_assoc()) ? (int)($seq['max_urut'] ?? 0) : 0;
            $next_urut = $max_urut + 1;
            $urut_kode = str_pad($next_urut, 3, '0', STR_PAD_LEFT);
            
            $no_anggota = $negara_kode . $prov_kode . $kota_kode . '.' . $ranting_kode . '-' . $tahun_bergabung . $urut_kode;
        }
    }
    
    // Handle foto upload - SIMPAN KE FOLDER DENGAN FORMAT ranting_nama_anggota.ext
    $foto_path = NULL;
    
    if (isset($_FILES['foto']) && $_FILES['foto']['size'] > 0) {
        $file = $_FILES['foto'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi
        if (!in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $error = "Format foto harus JPG atau PNG!";
        } elseif ($file['size'] > 5242880) { // 5MB
            $error = "Ukuran foto maksimal 5MB!";
        } else {
            // Buat folder jika belum ada
            $upload_dir = '../../uploads/foto_anggota/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Dapatkan nama ranting
            $ranting_name = '';
            $ranting_id_for_photo = !empty($ranting_saat_ini_id) ? $ranting_saat_ini_id : $ranting_awal_id;
            if (!empty($ranting_id_for_photo)) {
                $rantingQuery = $conn->query("SELECT nama_ranting FROM ranting WHERE id = " . (int)$ranting_id_for_photo);
                if ($ranting = $rantingQuery->fetch_assoc()) {
                    $ranting_name = sanitize_name($ranting['nama_ranting']) . '_';
                }
            }
            
            // Format nama file: ranting_nama_anggota.ext
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
        // Cek no anggota sudah ada (hanya jika tidak kosong)
        if (!empty($no_anggota)) {
            $check = $conn->query("SELECT id FROM anggota WHERE no_anggota = '$no_anggota'");
            if ($check->num_rows > 0) {
                $error = "No Anggota sudah terdaftar!";
            }
        }
        
        // Lanjutkan ke INSERT jika tidak ada error
        if (!$error) {
            $sql = "INSERT INTO anggota (
                no_anggota, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin,
                ranting_awal_id, ranting_awal_manual, ranting_saat_ini_id, tingkat_id, jenis_anggota,
                tahun_bergabung, no_handphone, ukt_terakhir, nama_foto
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // Total 14 parameter
                $stmt->bind_param("ssssssssssssss", 
                    $no_anggota,           // s
                    $nama_lengkap,         // s
                    $tempat_lahir,         // s
                    $tanggal_lahir,        // s
                    $jenis_kelamin,        // s
                    $ranting_awal_id,      // s
                    $ranting_awal_manual,  // s
                    $ranting_saat_ini_id,  // s
                    $tingkat_id,           // s
                    $jenis_anggota,        // s
                    $tahun_bergabung,      // s
                    $no_handphone,         // s
                    $ukt_terakhir,         // s
                    $foto_path             // s
                );
                
                if ($stmt->execute()) {
                    $anggota_id = $stmt->insert_id;
                    
                    // Insert prestasi jika ada [BARU]
                    if (!empty($_POST['event_name'][0])) {
                        for ($i = 0; $i < count($_POST['event_name']); $i++) {
                            if (!empty($_POST['event_name'][$i])) {
                                $event = $conn->real_escape_string($_POST['event_name'][$i]);
                                $tgl = $_POST['tanggal_pelaksanaan'][$i] ?? NULL;
                                $penyelenggara = $conn->real_escape_string($_POST['penyelenggara'][$i] ?? '');
                                $kategori = $conn->real_escape_string($_POST['kategori'][$i] ?? '');
                                $prestasi = $conn->real_escape_string($_POST['prestasi'][$i] ?? '');
                                
                                $prestasi_sql = "INSERT INTO prestasi (anggota_id, event_name, tanggal_pelaksanaan, penyelenggara, kategori, prestasi) 
                                        VALUES ($anggota_id, '$event', '$tgl', '$penyelenggara', '$kategori', '$prestasi')";
                                $conn->query($prestasi_sql);
                            }
                        }
                    }
                    
                    $success = "Anggota berhasil ditambahkan!";
                    header("refresh:2;url=anggota.php");
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error prepare: " . $conn->error;
            }
        }
    }
}

$ranting_result = $conn->query("SELECT id, nama_ranting, kode FROM ranting ORDER BY nama_ranting");
$tingkatan_result = $conn->query("SELECT id, nama_tingkat FROM tingkatan ORDER BY urutan");

// Get data for cascade filter
$negara_result = $conn->query("SELECT id, nama, kode FROM negara ORDER BY nama");
$provinsi_result = $conn->query("SELECT id, nama, kode, negara_id FROM provinsi ORDER BY nama");
$kota_result = $conn->query("SELECT id, nama, kode, provinsi_id FROM kota ORDER BY nama");
$jenis_result = $conn->query("SELECT id, nama_jenis FROM jenis_anggota ORDER BY id");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Anggota - Sistem Beladiri</title>
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
            align-items: center;
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
            padding: 15px 18px;
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
        
        /* Prestasi Section Styling [BARU] */
        .prestasi-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .prestasi-item {
            background: white;
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
    </style>
</head>
<body>
    <?php renderNavbar('➕ Tambah Anggota Baru'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Formulir Pendaftaran Anggota Baru</h1>
            <p class="form-subtitle">Silahkan isi semua kolom yang bertanda bintang merah (*)</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Bagian 1: Data Organisasi -->
                <h3>🏢 Data Organisasi</h3>
                
                <!-- Cascade Filter: Negara -> Provinsi -> Kota -> Ranting -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Negara</label>
                        <select name="filter_negara" id="filter_negara" onchange="updateProvinsiForm()">
                            <option value="">-- Pilih Negara --</option>
                            <?php 
                            $negara_result->data_seek(0);
                            while ($row = $negara_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>"><?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Negara</label>
                        <input type="text" id="kode_negara_display" readonly placeholder="-">
                    </div>
                    
                    <div class="form-group">
                        <label>Provinsi</label>
                        <select name="filter_provinsi" id="filter_provinsi" onchange="updateKotaForm()" disabled>
                            <option value="">-- Pilih Provinsi --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Provinsi</label>
                        <input type="text" id="kode_provinsi_display" readonly placeholder="-">
                    </div>
                    
                    <div class="form-group">
                        <label>Kota/Kabupaten</label>
                        <select name="filter_kota" id="filter_kota" onchange="updateRantingForm()" disabled>
                            <option value="">-- Pilih Kota --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Kota</label>
                        <input type="text" id="kode_kota_display" readonly placeholder="-">
                    </div>
                    
                    <div class="form-group">
                        <label>Unit/Ranting Saat Ini <span class="required">*</span></label>
                        <select name="ranting_saat_ini_id" id="ranting_saat_ini_id" required onchange="updateRantingKode()">
                            <option value="">-- Pilih Ranting --</option>
                            <?php 
                            $ranting_result->data_seek(0);
                            while ($row = $ranting_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>"><?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama_ranting']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Ranting</label>
                        <input type="text" id="kode_ranting_display" readonly placeholder="-">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Unit/Ranting Awal Masuk <span class="required">*</span></label>
                    
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="ranting_database" name="ranting_awal_pilihan" value="database" checked onchange="toggleRantingAwal()">
                            <label for="ranting_database">Pilih dari Database</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="ranting_manual" name="ranting_awal_pilihan" value="manual" onchange="toggleRantingAwal()">
                            <label for="ranting_manual">Input Manual</label>
                        </div>
                    </div>
                    
                    <div id="ranting_awal_select" class="form-group">
                        <select name="ranting_awal_id">
                            <option value="">-- Pilih Unit/Ranting --</option>
                            <?php 
                            $ranting_result->data_seek(0);
                            while ($row = $ranting_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>" data-kode="<?php echo $row['kode']; ?>"><?php echo htmlspecialchars($row['kode'] . ' - ' . $row['nama_ranting']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">Pilih Unit/Ranting yang tersedia di database</div>
                    </div>
                    
                    <div id="ranting_awal_manual" class="conditional-field">
                        <input type="text" name="ranting_awal_manual" placeholder="Masukkan nama Unit/Ranting">
                        <div class="form-hint">Masukkan nama Unit/Ranting secara manual</div>
                    </div>
                </div>
                
                <div class="form-row">                                       
                    <div class="form-group">
                        <label>Tingkat <span class="required">*</span></label>
                        <select name="tingkat_id" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <?php while ($row = $tingkatan_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>">
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
                            if ($jenis_result && $jenis_result->num_rows > 0) {
                                $jenis_result->data_seek(0);
                                while ($row = $jenis_result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nama_jenis']) . '</option>';
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
                        <input type="number" name="tahun_bergabung" min="1900" max="2100" required placeholder="Contoh: 2024">
                        <div class="form-hint">Tahun anggota bergabung</div>
                    </div>

                    <div class="form-group">
                        <label>UKT Terakhir</label>
                        <input type="text" name="ukt_terakhir" placeholder="Format: dd/mm/yyyy atau yyyy">
                        <div class="form-hint">Format: 15/07/2024 atau 2024</div>
                    </div>
                </div>                
                
                <hr>
                
                <!-- Bagian 2: Data Pribadi -->
                <h3>📋 Data Pribadi</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>No Anggota <span class="required">*</span></label>
                        <input type="text" id="no_anggota_display" readonly style="background-color: #e0e0e0;" placeholder="Otomatis生成">
                        <input type="hidden" name="no_anggota" id="no_anggota">
                        <div class="form-hint">No Anggota akan otomatis dibuat setelah memilih Unit/Ranting dan Tahun Bergabung. Format mengikuti pengaturan sistem.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" required placeholder="Masukkan nama lengkap">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" required placeholder="Contoh: Jakarta">
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Kelamin <span class="required">*</span></label>
                        <select name="jenis_kelamin" required>
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>No. Handphone</label>
                        <input type="tel" name="no_handphone" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="Contoh: 08xxxxxxxxxx">
                        <div class="form-hint">Nomor telepon yang dapat dihubungi</div>
                    </div>
                </div>
                 
                <div class="form-row">
                    <div class="form-group">
                        <label>Foto Profil</label>
                        <input type="file" name="foto" accept="image/*">
                        <div class="form-hint">Format: JPG, PNG (Ukuran maksimal 5MB) | Nama file akan menjadi: NoAnggota_NamaLengkap.jpg</div>
                    </div>
                </div>
                
                <hr>
                
                
                <!-- Bagian 3: Prestasi yang Diraih [BARU] -->
                <h3>🏆 Prestasi yang Diraih (Opsional)</h3>
                
                <p class="form-hint" style="margin-bottom: 20px;">Tambahkan prestasi yang pernah diraih anggota ini. Anda dapat menambahkan lebih dari satu prestasi.</p>
                
                <div class="prestasi-container">
                    <div id="prestasiList"></div>
                    <button type="button" class="btn btn-add-prestasi" onclick="addPrestasi()">+ Tambah Prestasi</button>
                </div>
                
                <!-- Template Prestasi [HIDDEN] -->
                <div class="prestasi-item template" id="prestasiTemplate">
                    <div class="form-row full">
                        <div class="form-group">
                            <label>Nama Event</label>
                            <input type="text" name="event_name[]" placeholder="Contoh: Kejuaraan Nasional">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Pelaksanaan</label>
                            <input type="date" name="tanggal_pelaksanaan[]">
                        </div>
                        
                        <div class="form-group">
                            <label>Penyelenggara</label>
                            <input type="text" name="penyelenggara[]" placeholder="Contoh: KONI, Pengprov, dll">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori yang Diikuti</label>
                            <input type="text" name="kategori[]" placeholder="Contoh: Putra -60kg">
                        </div>
                        
                        <div class="form-group">
                            <label>Prestasi</label>
                            <input type="text" name="prestasi[]" placeholder="Contoh: Juara 1, Juara 2, dll">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-remove-prestasi" onclick="removePrestasi(this)">🗑️ Hapus Prestasi</button>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Data Anggota</button>
                    <a href="anggota.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Cascade functions for Negara -> Provinsi -> Kota -> Ranting
        function updateProvinsiForm() {
            const negaraSelect = document.getElementById('filter_negara');
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_awal_select').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id');
            
            const negaraId = negaraSelect.value;
            
            // Reset dropdowns
            provinsiSelect.innerHTML = '<option value="">-- Pilih Provinsi --</option>';
            kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
            
            // Reset kode displays
            document.getElementById('kode_negara_display').value = '';
            document.getElementById('kode_provinsi_display').value = '';
            document.getElementById('kode_kota_display').value = '';
            document.getElementById('kode_ranting_display').value = '';
            
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
            document.getElementById('kode_negara_display').value = negaraKode;
            
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
        
        function updateKotaForm() {
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_awal_select').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id');
            
            const provinsiId = provinsiSelect.value;
            
            // Reset dropdown
            kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
            
            // Reset kode displays
            document.getElementById('kode_provinsi_display').value = '';
            document.getElementById('kode_kota_display').value = '';
            document.getElementById('kode_ranting_display').value = '';
            
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
            document.getElementById('kode_provinsi_display').value = provKode;
            
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
        
        function updateRantingForm() {
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_awal_select').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id');
            
            const kotaId = kotaSelect.value;
            
            // Reset ranting dropdowns
            rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
            if (rantingSaatIniSelect) {
                rantingSaatIniSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting Saat Ini --</option>';
            }
            
            // Reset kode displays
            document.getElementById('kode_kota_display').value = '';
            document.getElementById('kode_ranting_display').value = '';
            
            // Reset no anggota when kota changes
            document.getElementById('no_anggota').value = '';
            
            if (kotaId === '') {
                return;
            }
            
            // Show kota kode
            const kotaOption = kotaSelect.options[kotaSelect.selectedIndex];
            const kotaKode = kotaOption.getAttribute('data-kode') || '';
            document.getElementById('kode_kota_display').value = kotaKode;
            
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
                        
                        // Trigger generate no anggota after options are populated
                        if (typeof generateNoAnggota === 'function') {
                            generateNoAnggota();
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Function to update ranting kode display
        function updateRantingKode() {
            const rantingSelect = document.getElementById('ranting_saat_ini_id');
            const rantingId = rantingSelect.value;
            
            if (rantingId === '') {
                document.getElementById('kode_ranting_display').value = '';
                return;
            }
            
            const rantingOption = rantingSelect.options[rantingSelect.selectedIndex];
            const rantingKode = rantingOption.getAttribute('data-kode') || '';
            document.getElementById('kode_ranting_display').value = rantingKode;
            
            // Trigger generate no anggota
            if (typeof generateNoAnggota === 'function') {
                generateNoAnggota();
            }
        }
        
        // Function to generate No Anggota
        function generateNoAnggota() {
            const negaraSelect = document.getElementById('filter_negara');
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_awal_select').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id');
            const tahunInput = document.querySelector('input[name="tahun_bergabung"]');
            const noAnggotaInput = document.getElementById('no_anggota');
            
            const negaraId = negaraSelect ? negaraSelect.value : '';
            const provinsiId = provinsiSelect ? provinsiSelect.value : '';
            const kotaId = kotaSelect ? kotaSelect.value : '';
            
            // Use ranting_awal_id if selected, otherwise use ranting_saat_ini_id
            let rantingId = rantingSelect ? rantingSelect.value : '';
            if (!rantingId && rantingSaatIniSelect) {
                rantingId = rantingSaatIniSelect.value;
            }
            
            const tahun = tahunInput ? tahunInput.value : new Date().getFullYear();
            
            // Debug log
            console.log('Generating no anggota:', { negaraId, provinsiId, kotaId, rantingId, tahun });
            
            // Need all required fields to generate no anggota
            if (!negaraId || !provinsiId || !kotaId || !rantingId || !tahun) {
                console.log('Missing required fields for no anggota');
                return;
            }
            
            // Call API to generate no anggota
            fetch('../../api/generate_no_anggota.php?negara_id=' + negaraId 
                + '&provinsi_id=' + provinsiId 
                + '&kota_id=' + kotaId 
                + '&ranting_id=' + rantingId 
                + '&tahun=' + tahun)
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    if (data.success) {
                        // Use display format for showing, full format for hidden field
                        document.getElementById('no_anggota_display').value = data.no_anggota_display || data.no_anggota;
                        document.getElementById('no_anggota').value = data.no_anggota;
                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => console.error('Error generating no anggota:', error));
        }
        
        // Add event listener to ranting dropdown after it's loaded
        function setupRantingListener() {
            const rantingSelect = document.getElementById('ranting_awal_select').querySelector('select');
            const rantingSaatIniSelect = document.getElementById('ranting_saat_ini_id');
            const tahunInput = document.querySelector('input[name="tahun_bergabung"]');
            
            if (rantingSelect) {
                rantingSelect.addEventListener('change', generateNoAnggota);
            }
            
            if (rantingSaatIniSelect) {
                rantingSaatIniSelect.addEventListener('change', generateNoAnggota);
            }
            
            if (tahunInput) {
                tahunInput.addEventListener('change', generateNoAnggota);
                tahunInput.addEventListener('input', function() {
                    // Also generate when user types 4 digits (complete year)
                    if (this.value.length === 4) {
                        generateNoAnggota();
                    }
                });
            }
            
            // Also trigger on radio button change
            const radioButtons = document.querySelectorAll('input[name="ranting_awal_pilihan"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', generateNoAnggota);
            });
        }
        
        // Setup listeners on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(setupRantingListener, 500);
        });
        
        function toggleRantingAwal() {
            const databaseOption = document.getElementById('ranting_database');
            const selectField = document.getElementById('ranting_awal_select');
            const manualField = document.getElementById('ranting_awal_manual');
            
            if (databaseOption.checked) {
                selectField.style.display = 'block';
                manualField.classList.remove('show');
                document.querySelector('input[name="ranting_awal_manual"]').value = '';
            } else {
                selectField.style.display = 'none';
                manualField.classList.add('show');
                document.querySelector('select[name="ranting_awal_id"]').value = '';
            }
        }
        
        // Fungsi untuk tambah prestasi [BARU]
        function addPrestasi() {
            const template = document.getElementById('prestasiTemplate').cloneNode(true);
            template.classList.remove('template');
            document.getElementById('prestasiList').appendChild(template);
        }
        
        // Fungsi untuk hapus prestasi [BARU]
        function removePrestasi(btn) {
            btn.parentElement.remove();
        }
        
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