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

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$error = '';
$success = '';
$import_log = [];

// Handle download template
if (isset($_GET['download'])) {
    $template_file = '../../templates/csv/anggota_template.csv';
    
    if (file_exists($template_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="anggota_template.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($template_file));
        readfile($template_file);
        exit();
    }
}

// Helper function untuk parse tanggal
function parse_date_anggota($date_str) {
    if (empty($date_str)) {
        return null;
    }
    
    $date_str = trim($date_str);
    
    // Format dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_str, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    
    // Format YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_str, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    
    return null;
}

// Helper function untuk mencatat log import
function log_import_anggota($row_num, $message, $type = 'info') {
    $icon = $type === 'success' ? '✅' : ($type === 'error' ? '❌' : '⚠️');
    $GLOBALS['import_log'][] = "Baris $row_num: $icon $message";
    return $type;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (strtolower($file_ext) != 'csv') {
        $error = "Hanya format CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        
        if ($header === false) {
            $error = "File CSV kosong!";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $negara_kode_col = null;
            $provinsi_kode_col = null;
            $kota_kode_col = null;
            $ranting_kode_col = null;
            $nama_lengkap_col = null;
            $tempat_lahir_col = null;
            $tanggal_lahir_col = null;
            $jenis_kelamin_col = null;
            $no_handphone_col = null;
            $jenis_anggota_col = null;
            $tingkat_id_col = null;
            $tahun_bergabung_col = null;
            $ranting_awal_manual_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'negara') !== false) $negara_kode_col = $idx;
                if (strpos($col, 'provinsi') !== false) $provinsi_kode_col = $idx;
                if (strpos($col, 'kota') !== false) $kota_kode_col = $idx;
                if (strpos($col, 'ranting_kode') !== false) $ranting_kode_col = $idx;
                if (strpos($col, 'nama_lengkap') !== false || (strpos($col, 'nama') !== false && strpos($col, 'ranting') === false)) $nama_lengkap_col = $idx;
                if (strpos($col, 'tempat_lahir') !== false) $tempat_lahir_col = $idx;
                if (strpos($col, 'tanggal_lahir') !== false) $tanggal_lahir_col = $idx;
                if (strpos($col, 'jenis_kelamin') !== false) $jenis_kelamin_col = $idx;
                if (strpos($col, 'no_handphone') !== false || strpos($col, 'handphone') !== false || strpos($col, 'hp') !== false) $no_handphone_col = $idx;
                if (strpos($col, 'jenis_anggota') !== false) $jenis_anggota_col = $idx;
                if (strpos($col, 'tingkat') !== false || strpos($col, 'tingkat_id') !== false) $tingkat_id_col = $idx;
                if (strpos($col, 'tahun_bergabung') !== false) $tahun_bergabung_col = $idx;
                if (strpos($col, 'ranting_awal_manual') !== false) $ranting_awal_manual_col = $idx;
            }
            
            // Validasi kolom wajib
            if ($negara_kode_col === null || $provinsi_kode_col === null || $ranting_kode_col === null || $nama_lengkap_col === null || $jenis_kelamin_col === null) {
                $error = "CSV harus memiliki kolom: negara_kode, provinsi_kode, ranting_kode, nama_lengkap, jenis_kelamin";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                // Prepared statements
                $check_stmt = $conn->prepare("SELECT id FROM anggota WHERE no_anggota = ?");
                $insert_stmt = $conn->prepare("INSERT INTO anggota (no_anggota, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, ranting_awal_id, ranting_awal_manual, ranting_saat_ini_id, tingkat_id, jenis_anggota, tahun_bergabung, no_handphone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                while ($row = fgetcsv($handle)) {
                    $row_num++;
                    
                    if (empty($row[0])) continue;
                    
                    // Ambil data dari CSV
                    $negara_kode = isset($negara_kode_col) ? strtoupper(trim($row[$negara_kode_col] ?? '')) : '';
                    $provinsi_kode = isset($provinsi_kode_col) ? strtoupper(trim($row[$provinsi_kode_col] ?? '')) : '';
                    $kota_kode = isset($kota_kode_col) ? strtoupper(trim($row[$kota_kode_col] ?? '')) : '';
                    $ranting_kode = isset($ranting_kode_col) ? strtoupper(trim($row[$ranting_kode_col] ?? '')) : '';
                    $nama_lengkap = isset($nama_lengkap_col) ? trim($row[$nama_lengkap_col] ?? '') : '';
                    $tempat_lahir = isset($tempat_lahir_col) ? trim($row[$tempat_lahir_col] ?? '') : '';
                    $tanggal_lahir_raw = isset($tanggal_lahir_col) ? trim($row[$tanggal_lahir_col] ?? '') : '';
                    $jenis_kelamin = isset($jenis_kelamin_col) ? strtoupper(trim($row[$jenis_kelamin_col] ?? '')) : '';
                    $no_handphone = isset($no_handphone_col) ? trim($row[$no_handphone_col] ?? '') : '';
                    $jenis_anggota = isset($jenis_anggota_col) ? trim($row[$jenis_anggota_col] ?? '') : '';
                    $tingkat_id = isset($tingkat_id_col) ? (int)($row[$tingkat_id_col] ?? 0) : 0;
                    $tahun_bergabung = isset($tahun_bergabung_col) ? (int)($row[$tahun_bergabung_col] ?? date('Y')) : date('Y');
                    $ranting_awal_manual = isset($ranting_awal_manual_col) ? trim($row[$ranting_awal_manual_col] ?? '') : '';
                    
                    // Validasi data tidak lengkap
                    if (empty($negara_kode) || empty($provinsi_kode) || empty($kota_kode) || empty($ranting_kode) || empty($nama_lengkap) || empty($jenis_kelamin)) {
                        log_import_anggota($row_num, "Data tidak lengkap - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                    
                    // Parse tanggal lahir
                    $tanggal_lahir = parse_date_anggota($tanggal_lahir_raw);
                    
                    // Cari ranting berdasarkan kode (negara, provinsi, kota, ranting)
                    $ranting_result = $conn->query("
                        SELECT r.id, r.kode as ranting_kode FROM ranting r
                        JOIN kota k ON r.kota_id = k.id
                        JOIN provinsi p ON k.provinsi_id = p.id
                        JOIN negara n ON p.negara_id = n.id
                        WHERE n.kode = '$negara_kode' AND p.kode = '$provinsi_kode' AND k.kode = '$kota_kode' AND r.kode = '$ranting_kode'
                    ");
                    
                    if (!$ranting_result || $ranting_result->num_rows == 0) {
                        log_import_anggota($row_num, "Ranting tidak ditemukan (kode: $ranting_kode) - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                    $ranting = $ranting_result->fetch_assoc();
                    $ranting_id = $ranting['id'];
                    
                    // Convert jenis_anggota name to ID
                    $jenis_anggota_id = null;
                    if (!empty($jenis_anggota)) {
                        $jenis_result = $conn->query("SELECT id FROM jenis_anggota WHERE nama_jenis = '" . $conn->real_escape_string($jenis_anggota) . "'");
                        if ($jenis_result && $jenis_result->num_rows > 0) {
                            $jenis_row = $jenis_result->fetch_assoc();
                            $jenis_anggota_id = $jenis_row['id'];
                        } else {
                            // Default to Murid (ID=1) if not found
                            $jenis_anggota_id = 1;
                        }
                    } else {
                        $jenis_anggota_id = 1;
                    }
                    
                    // Generate no_anggota: NNPPPKKK.RRR-YYYYXXX
                    // NN = negara 2 digit, PPP = provinsi 3 digit, KKK = kota 3 digit, RRR = ranting 3 digit, YYYY = tahun, XXX = urutan
                    $year = $tahun_bergabung ?: date('Y');
                    
                    // Pad kode to correct length
                    $negara_kode_pad = str_pad($negara_kode, 2, '0', STR_PAD_LEFT);
                    $provinsi_kode_pad = str_pad($provinsi_kode, 3, '0', STR_PAD_LEFT);
                    $kota_kode_pad = str_pad($kota_kode, 3, '0', STR_PAD_LEFT);
                    $ranting_kode_pad = str_pad($ranting_kode, 3, '0', STR_PAD_LEFT);
                    
                    // Get max sequence for this combination (negara+provinsi+kota+ranting) in this year
                    $kode_prefix = $negara_kode_pad . $provinsi_kode_pad . $kota_kode_pad;
                    $sql_max = "SELECT MAX(CAST(RIGHT(no_anggota, 3) AS UNSIGNED)) as max_urut 
                                FROM anggota 
                                WHERE no_anggota LIKE '$kode_prefix.$ranting_kode_pad-$year%'";
                    $max_result = $conn->query($sql_max);
                    $max_urut = 0;
                    if ($row_max = $max_result->fetch_assoc()) {
                        $max_urut = (int)($row_max['max_urut'] ?? 0);
                    }
                    $next_urut = $max_urut + 1;
                    $urut_kode = str_pad($next_urut, 3, '0', STR_PAD_LEFT);
                    
                    // Build no_anggota: NNPPPKKK.RRR-YYYYXXX
                    $no_anggota = $negara_kode_pad . $provinsi_kode_pad . $kota_kode_pad . '.' . $ranting_kode_pad . '-' . $year . $urut_kode;
                    
                    // Check duplikat
                    $check_stmt->bind_param("s", $no_anggota);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($check_result->num_rows > 0) {
                        log_import_anggota($row_num, "No Anggota sudah ada - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                    
                    // Insert data
                    // Set ranting_awal_id same as ranting_saat_ini_id for import
                    $ranting_awal_id = $ranting_id;
                    $insert_stmt->bind_param("sssssssiisss", 
                        $no_anggota,           // s
                        $nama_lengkap,         // s
                        $tempat_lahir,         // s
                        $tanggal_lahir,        // s
                        $jenis_kelamin,        // s
                        $ranting_awal_id,      // s (NULL - same as ranting_saat_ini_id)
                        $ranting_awal_manual,  // s
                        $ranting_id,           // i
                        $tingkat_id,           // i
                        $jenis_anggota_id,     // i (ID instead of name)
                        $year,                 // s
                        $no_handphone          // s
                    );
                    
                    if ($insert_stmt->execute()) {
                        log_import_anggota($row_num, "'$nama_lengkap' berhasil ditambahkan (No: $no_anggota)", 'success');
                        $imported++;
                    } else {
                        log_import_anggota($row_num, "Error insert - " . $insert_stmt->error, 'error');
                        $skipped++;
                    }
                }
                
                fclose($handle);
                $check_stmt->close();
                $insert_stmt->close();
                
                $success = "Import selesai! $imported anggota berhasil ditambahkan, $skipped dilewati.";
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
    <title>Import Anggota - Sistem Beladiri</title>
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
        .description { color: #666; margin-bottom: 30px; line-height: 1.6; }
        
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
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
        .description, .alert, .info-box { overflow-wrap: anywhere; word-break: break-word; white-space: normal; }
        
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
        .btn-secondary { background: #6c757d; color: white; }
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid; }
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        .log-box { background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-top: 20px; max-height: 400px; overflow-y: auto; font-size: 12px; font-family: 'Courier New', monospace; }
        .log-item { margin-bottom: 6px; color: #333; }
        .tab-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .template-link { display: inline-block; padding: 6px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; margin-left: 10px; }
        .template-link:hover { background: #218838; }
    </style>
</head>
<body>
    <?php renderNavbar('⬆️ Import Data Anggota'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Data Anggota</h1>
            <p class="description">Upload file CSV berisi data anggota baru.</p>
            
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
            
            <div class="info-box">
                <h4>📋 Format File CSV</h4>
                <p><strong>Kolom yang diperlukan:</strong></p>
                <ol style="margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333;">
                    <li style="margin-bottom: 6px;"><strong>negara_kode</strong> - Kode negara (contoh: ID, MY)</li>
                    <li style="margin-bottom: 6px;"><strong>provinsi_kode</strong> - Kode provinsi (contoh: 001, 002)</li>
                    <li style="margin-bottom: 6px;"><strong>kota_kode</strong> - Kode kota (contoh: 001, 002)</li>
                    <li style="margin-bottom: 6px;"><strong>ranting_kode</strong> - Kode ranting/unit (contoh: 001, 002)</li>
                    <li style="margin-bottom: 6px;"><strong>nama_lengkap</strong> - Nama lengkap anggota</li>
                    <li style="margin-bottom: 6px;"><strong>tempat_lahir</strong> - Tempat lahir</li>
                    <li style="margin-bottom: 6px;"><strong>tanggal_lahir</strong> - Tanggal lahir (dd/mm/yyyy)</li>
                    <li style="margin-bottom: 6px;"><strong>jenis_kelamin</strong> - L (Laki-laki) atau P (Perempuan)</li>
                    <li style="margin-bottom: 6px;"><strong>no_handphone</strong> - Nomor telepon (contoh: 08123456789)</li>
                    <li style="margin-bottom: 6px;"><strong>jenis_anggota</strong> - Jenis anggota (contoh: Murid, Pelatih, Pelatih Unit/Ranting)</li>
                    <li style="margin-bottom: 6px;"><strong>tingkat_id</strong> - ID tingkat (angka)</li>
                    <li style="margin-bottom: 6px;"><strong>tahun_bergabung</strong> - Tahun bergabung (contoh: 2024)</li>
                    <li style="margin-bottom: 6px;"><strong>ranting_awal_manual</strong> - Nama ranting awal (contoh: Ranting Surabaya Timur)</li>
                </ol>
                <p style="margin-top: 10px; font-size: 12px; color: #666;"><strong>Catatan: <span style="color: #dc3545;">Pastikan data negara, provinsi, kota, dan ranting sudah ada sebelum import anggota.</span></strong></p>
                <p style="margin-top: 5px; font-size: 12px; color: #666;"><strong>Format No. Anggota:</strong> NNPPPKKK.RRR-YYYYXXX (2 digit negara, 3 digit provinsi, 3 digit kota, 3 digit ranting, 4 digit tahun, 3 digit urutan)</p>
            </div>
            
            <div class="tab-header" style="justify-content: flex-end;">
                <a href="?download=anggota" class="template-link" style="background: #28a745; margin-left: 0;">📥 Download Template</a>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file_excel">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                    <input type="file" id="file_excel" name="file_excel" accept=".csv" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">⬆️ Upload</button>
                    <a href="anggota.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>