<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';

// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

$GLOBALS['permission_manager'] = $permission_manager;

if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'negara';

$error = '';
$success = '';
$import_log = [];

// Helper function untuk parse tanggal dari CSV
function parse_date($date_str) {
    if (empty($date_str)) {
        return null;
    }
    
    $date_str = trim($date_str);
    
    // Format dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_str, $m)) {
        $result = $m[3] . '-' . $m[2] . '-' . $m[1];
        return $result;
    }
    
    // Format YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_str, $m)) {
        $result = $m[1] . '-' . $m[2] . '-' . $m[3];
        return $result;
    }
    
    // Format d-m-yyyy or d/m/yyyy with single digits
    if (preg_match('/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/', $date_str, $m)) {
        $result = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        return $result;
    }
    
    return null;
}

// Handle download template
if (isset($_GET['download'])) {
    $template = $_GET['download'];
    $templates = [
        'negara' => '../../templates/csv/negara_template.csv',
        'provinsi' => '../../templates/csv/provinsi_template.csv',
        'kota' => '../../templates/csv/kota_template.csv'
    ];
    
    if (isset($templates[$template]) && file_exists($templates[$template])) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($templates[$template]) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($templates[$template]));
        readfile($templates[$template]);
        exit();
    }
}

// Handle Negara Import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file']) && $active_tab == 'negara') {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya file CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Baca header
        $header = fgetcsv($handle);
        
        if ($header === false || count($header) < 2) {
            $error = "Format CSV tidak valid! Harus memiliki minimal 2 kolom";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $kode_col = null;
            $nama_col = null;
            $ketua_nama_col = null;
            $sk_col = null;
            $mulai_col = null;
            $akhir_col = null;
            $alamat_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'kode') !== false) $kode_col = $idx;
                if (strpos($col, 'nama') !== false && strpos($col, 'ketua') === false) $nama_col = $idx;
                if (strpos($col, 'ketua') !== false) $ketua_nama_col = $idx;
                if (strpos($col, 'sk') !== false) $sk_col = $idx;
                if (strpos($col, 'mulai') !== false) $mulai_col = $idx;
                if (strpos($col, 'akhir') !== false) $akhir_col = $idx;
                if (strpos($col, 'alamat') !== false) $alamat_col = $idx;
            }
            
            if ($kode_col === null || $nama_col === null || $ketua_nama_col === null) {
                $error = "CSV harus memiliki kolom: kode, nama, ketua_nama";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                while ($row = fgetcsv($handle)) {
                    $row_num++;
                    
                    if (empty($row[0])) {
                        continue;
                    }
                    
                    // Ambil data dari CSV
                    $kode = strtoupper(trim($row[$kode_col] ?? ''));
                    $nama = trim($row[$nama_col] ?? '');
                    $ketua_nama = isset($ketua_nama_col) ? trim($row[$ketua_nama_col] ?? '') : '';
                    $sk = isset($sk_col) ? trim($row[$sk_col] ?? '') : '';
                    $mulai = isset($mulai_col) ? trim($row[$mulai_col] ?? '') : '';
                    $akhir = isset($akhir_col) ? trim($row[$akhir_col] ?? '') : '';
                    $alamat = isset($alamat_col) ? trim($row[$alamat_col] ?? '') : '';
                    
                    // Validasi - semua field wajib diisi
                    if (empty($kode) || empty($nama) || empty($ketua_nama)) {
                        $import_log[] = "Baris $row_num: ⚠️ Kode, nama, atau ketua_nama kosong - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Cek duplikasi
                    $check = $conn->query("SELECT id FROM negara WHERE kode = '$kode'");
                    if ($check->num_rows > 0) {
                        $import_log[] = "Baris $row_num: ⚠️ Kode '$kode' sudah ada - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Parse tanggal
                    $mulai_parsed = parse_date($mulai);
                    $akhir_parsed = parse_date($akhir);
                    
                    // Get count to generate kode
                    $count = $conn->query("SELECT COUNT(*) as cnt FROM negara")->fetch_assoc();
                    $urutan = ($count['cnt'] ?? 0) + 1;
                    
                    // Insert negara
                    $insert_sql = "INSERT INTO negara (kode, nama, ketua_nama, sk_kepengurusan, periode_mulai, periode_akhir, alamat_sekretariat, aktif) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("sssssss", $kode, $nama, $ketua_nama, $sk, $mulai_parsed, $akhir_parsed, $alamat);
                    
                    if (!$insert_stmt->execute()) {
                        $import_log[] = "Baris $row_num: ❌ Error insert - " . $insert_stmt->error;
                        $skipped++;
                        continue;
                    }
                    
                    $import_log[] = "Baris $row_num: ✅ '$nama' ($kode) berhasil ditambahkan";
                    $imported++;
                    $insert_stmt->close();
                }
                
                fclose($handle);
                $success = "Import selesai! $imported data berhasil disimpan, $skipped data di-skip.";
            }
        }
    }
}

// Handle Provinsi Import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file']) && $active_tab == 'provinsi') {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya file CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Baca header
        $header = fgetcsv($handle);
        
        if ($header === false || count($header) < 2) {
            $error = "Format CSV tidak valid! Harus memiliki minimal 2 kolom";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $negara_kode_col = null;
            $nama_col = null;
            $ketua_nama_col = null;
            $sk_col = null;
            $mulai_col = null;
            $akhir_col = null;
            $alamat_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'negara') !== false) $negara_kode_col = $idx;
                if (strpos($col, 'nama') !== false && strpos($col, 'ketua') === false) $nama_col = $idx;
                if (strpos($col, 'ketua') !== false) $ketua_nama_col = $idx;
                if (strpos($col, 'sk') !== false) $sk_col = $idx;
                if (strpos($col, 'mulai') !== false) $mulai_col = $idx;
                if (strpos($col, 'akhir') !== false) $akhir_col = $idx;
                if (strpos($col, 'alamat') !== false) $alamat_col = $idx;
            }
            
            if ($negara_kode_col === null || $nama_col === null || $ketua_nama_col === null) {
                $error = "CSV harus memiliki kolom: negara_kode, nama, ketua_nama";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                while ($row = fgetcsv($handle)) {
                    $row_num++;
                    
                    if (empty($row[0])) {
                        continue;
                    }
                    
                    // Ambil data dari CSV
                    $negara_kode = strtoupper(trim($row[$negara_kode_col] ?? ''));
                    $nama = trim($row[$nama_col] ?? '');
                    $ketua_nama = isset($ketua_nama_col) ? trim($row[$ketua_nama_col] ?? '') : '';
                    $sk = isset($sk_col) ? trim($row[$sk_col] ?? '') : '';
                    $mulai = isset($mulai_col) ? trim($row[$mulai_col] ?? '') : '';
                    $akhir = isset($akhir_col) ? trim($row[$akhir_col] ?? '') : '';
                    $alamat = isset($alamat_col) ? trim($row[$alamat_col] ?? '') : '';
                    
                    // Validasi - semua field wajib diisi
                    if (empty($negara_kode) || empty($nama) || empty($ketua_nama)) {
                        $import_log[] = "Baris $row_num: ⚠️ Negara kode, nama, atau ketua_nama kosong - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Cari negara_id dari kode
                    $negara_result = $conn->query("SELECT id FROM negara WHERE kode = '$negara_kode'");
                    if ($negara_result->num_rows == 0) {
                        $import_log[] = "Baris $row_num: ❌ Negara dengan kode '$negara_kode' tidak ditemukan - di-skip";
                        $skipped++;
                        continue;
                    }
                    $negara = $negara_result->fetch_assoc();
                    $negara_id = $negara['id'];
                    
                    // Parse tanggal
                    $mulai_parsed = parse_date($mulai);
                    $akhir_parsed = parse_date($akhir);
                    
                    // Get count per negara to generate kode (001, 002, 003... per country)
                    $count = $conn->query("SELECT COUNT(*) as cnt FROM provinsi WHERE negara_id = $negara_id")->fetch_assoc();
                    $urutan = ($count['cnt'] ?? 0) + 1;
                    $kode = str_pad($urutan, 3, '0', STR_PAD_LEFT); // Auto-generate: 001, 002, 003...
                    
                    // Insert provinsi
                    $insert_sql = "INSERT INTO provinsi (negara_id, kode, nama, ketua_nama, sk_kepengurusan, periode_mulai, periode_akhir, alamat_sekretariat, aktif) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("isssssss", $negara_id, $kode, $nama, $ketua_nama, $sk, $mulai_parsed, $akhir_parsed, $alamat);
                    
                    if (!$insert_stmt->execute()) {
                        $import_log[] = "Baris $row_num: ❌ Error insert - " . $insert_stmt->error;
                        $skipped++;
                        continue;
                    }
                    
                    $import_log[] = "Baris $row_num: ✅ '$nama' ($negara_kode-$kode) berhasil ditambahkan (kode otomatis)";
                    $imported++;
                    $insert_stmt->close();
                }
                
                fclose($handle);
                $success = "Import selesai! $imported data berhasil disimpan, $skipped data di-skip.";
            }
        }
    }
}

// Handle Kota Import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file']) && $active_tab == 'kota') {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya file CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Baca header
        $header = fgetcsv($handle);
        
        if ($header === false || count($header) < 3) {
            $error = "Format CSV tidak valid! Harus memiliki minimal 3 kolom";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $negara_kode_col = null;
            $provinsi_kode_col = null;
            $nama_col = null;
            $ketua_nama_col = null;
            $sk_col = null;
            $mulai_col = null;
            $akhir_col = null;
            $alamat_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'negara') !== false) $negara_kode_col = $idx;
                if (strpos($col, 'provinsi') !== false) $provinsi_kode_col = $idx;
                if (strpos($col, 'nama') !== false && strpos($col, 'ketua') === false) $nama_col = $idx;
                if (strpos($col, 'ketua') !== false) $ketua_nama_col = $idx;
                if (strpos($col, 'sk') !== false) $sk_col = $idx;
                if (strpos($col, 'mulai') !== false) $mulai_col = $idx;
                if (strpos($col, 'akhir') !== false) $akhir_col = $idx;
                if (strpos($col, 'alamat') !== false) $alamat_col = $idx;
            }
            
            if ($negara_kode_col === null || $provinsi_kode_col === null || $nama_col === null || $ketua_nama_col === null) {
                $error = "CSV harus memiliki kolom: negara_kode, provinsi_kode, nama, ketua_nama";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                while ($row = fgetcsv($handle)) {
                    $row_num++;
                    
                    if (empty($row[0])) {
                        continue;
                    }
                    
                    // Ambil data dari CSV
                    $negara_kode = strtoupper(trim($row[$negara_kode_col] ?? ''));
                    $provinsi_kode = strtoupper(trim($row[$provinsi_kode_col] ?? ''));
                    $nama = trim($row[$nama_col] ?? '');
                    $ketua_nama = isset($ketua_nama_col) ? trim($row[$ketua_nama_col] ?? '') : '';
                    $sk = isset($sk_col) ? trim($row[$sk_col] ?? '') : '';
                    $mulai = isset($mulai_col) ? trim($row[$mulai_col] ?? '') : '';
                    $akhir = isset($akhir_col) ? trim($row[$akhir_col] ?? '') : '';
                    $alamat = isset($alamat_col) ? trim($row[$alamat_col] ?? '') : '';
                    
                    // Validasi - semua field wajib diisi
                    if (empty($negara_kode) || empty($provinsi_kode) || empty($nama) || empty($ketua_nama)) {
                        $import_log[] = "Baris $row_num: ⚠️ Negara kode, Provinsi kode, nama, atau ketua_nama kosong - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Cari provinsi_id dari negara_kode dan provinsi_kode
                    $provinsi_result = $conn->query("
                        SELECT p.id, p.negara_id 
                        FROM provinsi p 
                        JOIN negara n ON p.negara_id = n.id 
                        WHERE n.kode = '$negara_kode' AND p.kode = '$provinsi_kode'
                    ");
                    if ($provinsi_result->num_rows == 0) {
                        $import_log[] = "Baris $row_num: ❌ Provinsi dengan kode '$provinsi_kode' di negara '$negara_kode' tidak ditemukan - di-skip";
                        $skipped++;
                        continue;
                    }
                    $provinsi = $provinsi_result->fetch_assoc();
                    $provinsi_id = $provinsi['id'];
                    $negara_id = $provinsi['negara_id'];
                    
                    // Parse tanggal
                    $mulai_parsed = parse_date($mulai);
                    $akhir_parsed = parse_date($akhir);
                    
                    // Get count per province to generate kode (001, 002, 003... per province)
                    $count = $conn->query("SELECT COUNT(*) as cnt FROM kota WHERE provinsi_id = $provinsi_id")->fetch_assoc();
                    $urutan = ($count['cnt'] ?? 0) + 1;
                    $kode = str_pad($urutan, 3, '0', STR_PAD_LEFT); // Auto-generate: 001, 002, 003...
                    
                    // Insert kota
                    $insert_sql = "INSERT INTO kota (negara_id, provinsi_id, kode, nama, ketua_nama, sk_kepengurusan, periode_mulai, periode_akhir, alamat_sekretariat, aktif) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iisssssss", $negara_id, $provinsi_id, $kode, $nama, $ketua_nama, $sk, $mulai_parsed, $akhir_parsed, $alamat);
                    
                    if (!$insert_stmt->execute()) {
                        $import_log[] = "Baris $row_num: ❌ Error insert - " . $insert_stmt->error;
                        $skipped++;
                        continue;
                    }
                    
                    $import_log[] = "Baris $row_num: ✅ '$nama' ($negara_kode-$provinsi_kode-$kode) berhasil ditambahkan (kode otomatis)";
                    $imported++;
                    $insert_stmt->close();
                }
                
                fclose($handle);
                $success = "Import selesai! $imported data berhasil disimpan, $skipped data di-skip.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data - Sistem Beladiri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { margin-bottom: 10px; color: #333; }
        
        /* Tab Styles */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: #667eea;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="file"] {
            padding: 10px;
            border: 2px dashed #667eea;
            border-radius: 5px;
            width: 100%;
        }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box h4 { color: #667eea; margin-bottom: 10px; }
        .info-box p { font-size: 13px; color: #333; margin-bottom: 8px; font-family: monospace; overflow-wrap: anywhere; word-break: break-word; white-space: normal; }
        
        .template-link {
            display: inline-block;
            padding: 6px 12px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .template-link:hover {
            background: #218838;
        }

        .template-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
        }
        
        .template-table th, .template-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .template-table th {
            background: #f0f7ff;
            font-weight: 600;
            font-family: monospace;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .log-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            font-size: 12px;
            font-family: 'Courier New', monospace;
        }
        
        .log-item {
            margin-bottom: 6px;
            color: #333;
        }
        
        .tab-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .required-note {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php renderNavbar('⬆️ Import Data'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Data dari CSV</h1>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab <?php echo $active_tab == 'negara' ? 'active' : ''; ?>" onclick="location.href='?tab=negara'">🌍 Negara</button>
                <button class="tab <?php echo $active_tab == 'provinsi' ? 'active' : ''; ?>" onclick="location.href='?tab=provinsi'">🏛️ Provinsi</button>
                <button class="tab <?php echo $active_tab == 'kota' ? 'active' : ''; ?>" onclick="location.href='?tab=kota'">🏙️ Kota</button>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
                <?php if (count($import_log) > 0): ?>
                <div class="log-box">
                    <strong>📋 Detail Import:</strong><br>
                    <?php foreach ($import_log as $log): ?>
                        <div class="log-item"><?php echo htmlspecialchars($log); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Tab Content: Negara -->
            <div class="tab-content <?php echo $active_tab == 'negara' ? 'active' : ''; ?>" id="tab-negara">
                <div class="tab-header">
                    <h3>Import Negara</h3>
                    <a href="?tab=negara&download=negara" class="template-link">📥 Download Template</a>
                </div>
                
                <div class="info-box">
                    <h4>📋 Format File CSV</h4>
                    <p class="required-note">* Kode negara diisi MANUAL (2 karakter)</p>
                    <p><strong>Kolom yang diperlukan:</strong></p>
                    <ol style="margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333;">
                        <li style="margin-bottom: 6px;"><strong>Kode</strong> - 2 karakter (contoh: ID, MY, SG) ></li>
                        <li style="margin-bottom: 6px;"><strong>Nama</strong> - Nama negara</li>
                        <li style="margin-bottom: 6px;"><strong>Nama Ketua</strong> - Nama ketua negara</li>
                        <li style="margin-bottom: 6px;"><strong>SK Kepengurusan</strong></li>
                        <li style="margin-bottom: 6px;"><strong>Periode Mulai</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>Periode Akhir</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>Alamat Sekretariat</strong></li>
                    </ol>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file_negara">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                        <input type="file" id="csv_file_negara" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">⬆️ Upload & Import</button>
                        <a href="pengurus.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
            
            <!-- Tab Content: Provinsi -->
            <div class="tab-content <?php echo $active_tab == 'provinsi' ? 'active' : ''; ?>" id="tab-provinsi">
                <div class="tab-header">
                    <h3>Import Provinsi</h3>
                    <a href="?tab=provinsi&download=provinsi" class="template-link">📥 Download Template</a>
                </div>
                
                <div class="info-box">
                    <h4>📋 Format File CSV</h4>
                    <p class="required-note">* Kode provinsi dibuat OTOMATIS oleh sistem (001, 002, 003...)</p>
                    <p><strong>Kolom yang diperlukan:</strong></p>
                    <ol style="margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333;">
                        <li style="margin-bottom: 6px;"><strong>Negara Kode</strong> - Kode negara induk (contoh: ID, MY)</li>
                        <li style="margin-bottom: 6px;"><strong>Nama</strong> - Nama provinsi</li>
                        <li style="margin-bottom: 6px;"><strong>Nama Ketua</strong> - Nama ketua provinsi</li>
                        <li style="margin-bottom: 6px;"><strong>SK Kepengurusan</strong></li>
                        <li style="margin-bottom: 6px;"><strong>Periode Mulai</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>Periode Akhir</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>Alamat Sekretariat</strong></li>
                    </ol>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;"><strong>Catatan: <span style="color: #dc3545;">Pastikan data negara sudah ada sebelum import provinsi.</span></strong></p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file_provinsi">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                        <input type="file" id="csv_file_provinsi" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">⬆️ Upload & Import</button>
                        <a href="pengurus.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
            
            <!-- Tab Content: Kota -->
            <div class="tab-content <?php echo $active_tab == 'kota' ? 'active' : ''; ?>" id="tab-kota">
                <div class="tab-header">
                    <h3>Import Kota</h3>
                    <a href="?tab=kota&download=kota" class="template-link">📥 Download Template</a>
                </div>
                
                <div class="info-box">
                    <h4>📋 Format File CSV</h4>
                    <p class="required-note">* Kode kota dibuat OTOMATIS oleh sistem (001, 002, 003...)</p>
                    <p><strong>Kolom yang diperlukan:</strong></p>
                    <ol style="margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333;">
                        <li style="margin-bottom: 6px;"><strong>Negara Kode</strong> - Kode negara induk (contoh: ID, MY)</li>
                        <li style="margin-bottom: 6px;"><strong>Provinsi Kode</strong> - Kode provinsi (contoh: 001, 002)</li>
                        <li style="margin-bottom: 6px;"><strong>Nama</strong> - Nama kota/kabupaten</li>
                        <li style="margin-bottom: 6px;"><strong>Nama Ketua</strong> - Nama ketua kota</li>
                        <li style="margin-bottom: 6px;"><strong>SK Kepengurusan</strong></li>
                        <li style="margin-bottom: 6px;"><strong>Periode Mulai</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>Periode Akhir</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>Alamat Sekretariat</strong></li>
                    </ol>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;"><strong>Catatan: <span style="color: #dc3545;">Pastikan data negara dan provinsi sudah ada sebelum import kota.</span></strong></p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file_kota">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                        <input type="file" id="csv_file_kota" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">⬆️ Upload & Import</button>
                        <a href="pengurus.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
