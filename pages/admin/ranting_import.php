<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin','admin'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';
include '../../helpers/import_helper.php';

$permission_manager = new PermissionManager(
    $conn, 
    $_SESSION['user_id'], 
    $_SESSION['role'], 
    $_SESSION['pengurus_id'] ?? null, 
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

$GLOBALS['permission_manager'] = $permission_manager;

if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$error = '';
$success = '';
$import_log = [];

// Handle download template
if (isset($_GET['download']) && $_GET['download'] === 'ranting') {
    $filename = "ranting_template.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Header
    fputcsv($output, ['negara_kode', 'provinsi_kode', 'kota_kode', 'nama_ranting', 'jenis', 'tanggal_sk', 'no_sk', 'alamat', 'ketua_nama', 'pj_teknik', 'no_kontak'], ';');
    // Examples
    fputcsv($output, ['ID', '001', '001', 'Gubeng', 'ranting', '15/06/2015', '002/SK/2015', 'Jl. Gubeng No. 10, Surabaya', 'Firman', 'Firman', '089654789632'], ';');
    
    fclose($output);
    exit();
}

// Helper function untuk mencatat log import
function log_import($row_num, $message, $type = 'info') {
    $icon = $type === 'success' ? '✅' : ($type === 'error' ? '❌' : '⚠️');
    $GLOBALS['import_log'][] = "Baris $row_num: $icon $message";
    return $type;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya format CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle, 0, ';');
        
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
            $nama_ranting_col = null;
            $jenis_col = null;
            $tanggal_sk_col = null;
            $no_sk_col = null;
            $alamat_col = null;
            $ketua_col = null;
            $pj_teknik_col = null;
            $kontak_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'negara') !== false) $negara_kode_col = $idx;
                if (strpos($col, 'provinsi') !== false) $provinsi_kode_col = $idx;
                if (strpos($col, 'kota') !== false ) $kota_kode_col = $idx;
                if (strpos($col, 'nama_ranting') !== false || (strpos($col, 'nama') !== false && strpos($col, 'ketua') === false && strpos($col, 'pj_teknik') === false)) $nama_ranting_col = $idx;
                if (strpos($col, 'jenis') !== false) $jenis_col = $idx;
                if (strpos($col, 'tanggal') !== false && strpos($col, 'sk') !== false) $tanggal_sk_col = $idx;
                if (strpos($col, 'no_sk') !== false) $no_sk_col = $idx;
                if (strpos($col, 'alamat') !== false) $alamat_col = $idx;
                if (strpos($col, 'ketua') !== false) $ketua_col = $idx;
                if (strpos($col, 'teknik') !== false || strpos($col, 'pj_teknik') !== false) $pj_teknik_col = $idx;
                if (strpos($col, 'kontak') !== false || strpos($col, 'no_hp') !== false || strpos($col, 'telepon') !== false) $kontak_col = $idx;
            }
            
            // Validasi kolom wajib
            if ($negara_kode_col === null || $provinsi_kode_col === null || $nama_ranting_col === null || $jenis_col === null) {
                $error = "CSV harus memiliki kolom: negara_kode, provinsi_kode, nama_ranting, jenis. Pastikan pembatas adalah titik koma (;)";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                // Prepared statements untuk check duplikat
                $check_nama_stmt = $conn->prepare("SELECT id FROM ranting WHERE nama_ranting = ?");
                $check_sk_stmt = $conn->prepare("SELECT id FROM ranting WHERE no_sk_pembentukan = ?");
                
                // Prepared statement untuk insert
                $insert_stmt = $conn->prepare("INSERT INTO ranting (kode, nama_ranting, jenis, tanggal_sk_pembentukan, no_sk_pembentukan, 
                                alamat, ketua_nama, penanggung_jawab_teknik, no_kontak, kota_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                while ($row = fgetcsv($handle, 0, ';')) {
                    $row_num++;
                    
                    if (empty($row[0])) continue;
                    
                    // Ambil data dari CSV
                    $negara_kode = isset($negara_kode_col) ? strtoupper(trim($row[$negara_kode_col] ?? '')) : '';
                    $provinsi_kode = isset($provinsi_kode_col) ? str_pad(trim($row[$provinsi_kode_col] ?? ''), 3, '0', STR_PAD_LEFT) : '';
                    $kota_kode = isset($kota_kode_col) ? str_pad(trim($row[$kota_kode_col] ?? ''), 3, '0', STR_PAD_LEFT) : '';
                    $nama_ranting = isset($nama_ranting_col) ? trim($row[$nama_ranting_col] ?? '') : '';
                    $jenis = isset($jenis_col) ? strtolower(trim($row[$jenis_col] ?? '')) : '';
                    $tanggal_sk = isset($tanggal_sk_col) ? trim($row[$tanggal_sk_col] ?? '') : '';
                    $no_sk = isset($no_sk_col) ? trim($row[$no_sk_col] ?? '') : '';
                    $alamat = isset($alamat_col) ? trim($row[$alamat_col] ?? '') : '';
                    $ketua = isset($ketua_col) ? ucwords(strtolower(trim($row[$ketua_col] ?? ''))) : '';
                    $pj_teknik = isset($pj_teknik_col) ? ucwords(strtolower(trim($row[$pj_teknik_col] ?? ''))) : '';
                    $kontak = isset($kontak_col) ? trim($row[$kontak_col] ?? '') : '';
                    
                    // Validasi data tidak lengkap
                    if (empty($negara_kode) || empty($provinsi_kode) || empty($nama_ranting) || empty($jenis)) {
                        log_import($row_num, "Data tidak lengkap (negara_kode, provinsi_kode, nama_ranting, atau jenis kosong) - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                    
                    // Cari kota_id dari negara_kode dan provinsi_kode
                    $kota_result = null;
                    if (!empty($kota_kode)) {
                        // Jika ada kota_kode, cari berdasarkan negara_kode, provinsi_kode, dan kota_kode
                        $kota_result = $conn->query("
                            SELECT k.id, k.kode as kota_kode FROM kota k
                            JOIN provinsi p ON k.provinsi_id = p.id
                            JOIN negara n ON p.negara_id = n.id
                            WHERE n.kode = '$negara_kode' AND p.kode = '$provinsi_kode' AND k.kode = '$kota_kode'
                        ");
                    } else {
                        // Jika tidak ada kota_kode, ambil kota pertama dari provinsi tersebut
                        $kota_result = $conn->query("
                            SELECT k.id, k.kode as kota_kode FROM kota k
                            JOIN provinsi p ON k.provinsi_id = p.id
                            JOIN negara n ON p.negara_id = n.id
                            WHERE n.kode = '$negara_kode' AND p.kode = '$provinsi_kode'
                            ORDER BY k.urutan ASC
                            LIMIT 1
                        ");
                    }
                    
                    if (!$kota_result || $kota_result->num_rows == 0) {
                        log_import($row_num, "Kota tidak ditemukan (negara: $negara_kode, provinsi: $provinsi_kode) - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                    $kota = $kota_result->fetch_assoc();
                    $kota_id = $kota['id'];

                    // Generate kode ranting: 3 digit sequence per kota saja
                    $count_ranting = $conn->query("SELECT COUNT(*) as cnt FROM ranting WHERE kota_id = $kota_id")->fetch_assoc();
                    $sequence = (int)$count_ranting['cnt'] + 1;
                    $kode_ranting = str_pad($sequence, 3, '0', STR_PAD_LEFT);
                    
                    // Parse tanggal
                    $tanggal_sk_parsed = parse_import_date($tanggal_sk);
                    
                    // Check duplikat nama
                    $check_nama_stmt->bind_param("s", $nama_ranting);
                    $check_nama_stmt->execute();
                    $check_nama_result = $check_nama_stmt->get_result();
                    if ($check_nama_result->num_rows > 0) {
                        log_import($row_num, "Nama ranting '$nama_ranting' sudah ada - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                    
                    // Check SK jika diisi
                    if (!empty($no_sk)) {
                        $check_sk_stmt->bind_param("s", $no_sk);
                        $check_sk_stmt->execute();
                        $check_sk_result = $check_sk_stmt->get_result();
                        if ($check_sk_result->num_rows > 0) {
                            log_import($row_num, "No SK '$no_sk' sudah digunakan - dilewati", 'warning');
                            $skipped++;
                            continue;
                        }
                    }
                    
                    // Insert data
                    $insert_stmt->bind_param("sssssssssi",
                        $kode_ranting, $nama_ranting, $jenis, $tanggal_sk_parsed, $no_sk,
                        $alamat, $ketua, $pj_teknik, $kontak, $kota_id
                    );
                    
                    if ($insert_stmt->execute()) {
                        log_import($row_num, "'$nama_ranting' berhasil ditambahkan (kode: $kode_ranting, kota_id: $kota_id)", 'success');
                        $imported++;
                        $new_id = $conn->insert_id;
                        include_once '../../helpers/user_auto_creation.php';
                        createOrUpdateUser($conn, [
                            'username' => $nama_ranting,
                            'password' => formatPwd($nama_ranting) . '1955',
                            'nama_lengkap' => "Pengurus Unit/Ranting $nama_ranting",
                            'role' => $jenis,
                            'ranting_id' => $new_id
                        ]);
                    } else {
                        log_import($row_num, "Error insert - " . $insert_stmt->error, 'error');
                        $skipped++;
                    }
                }
                
                fclose($handle);
                $check_nama_stmt->close();
                $check_sk_stmt->close();
                $insert_stmt->close();
                
                $success = "Import selesai! $imported ranting berhasil ditambahkan, $skipped dilewati.";
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
    <title>Import Unit/Ranting - Sistem Beladiri</title>
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
        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        h1 { margin-bottom: 10px; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
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
        .log-item { margin-bottom: 6px; color: #333; }
        
        .tab-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
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
    </style>
</head>
<body>
    <?php renderNavbar('Import Unit/Ranting'); ?>
    
    <div style="display: flex; justify-content: center;">
        <div class="container" style="width: 100%;">
            <div class="form-container">
                <h1>Import Unit/Ranting dari CSV</h1>
                
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
                    <p class="required-note">* Kode ranting dibuat OTOMATIS oleh sistem (001, 002, 003...)</p>
                    <p><strong>Kolom yang diperlukan:</strong></p>
                    <ol style="margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333;">
                        <li style="margin-bottom: 6px;"><strong>Negara Kode</strong> - Kode negara induk (contoh: ID, MY)</li>
                        <li style="margin-bottom: 6px;"><strong>Provinsi Kode</strong> - Kode provinsi (contoh: 001, 002)</li>
                        <li style="margin-bottom: 6px;"><strong>Kota Kode</strong> - Kode kota (contoh: 001, 002)</li>
                        <li style="margin-bottom: 6px;"><strong>Nama</strong> - Nama unit/ranting</li>
                        <li style="margin-bottom: 6px;"><strong>Jenis</strong> - ukm, unit, atau ranting</li>
                        <li style="margin-bottom: 6px;"><strong>Tanggal SK</strong> - dd/mm/yyyy</li>
                        <li style="margin-bottom: 6px;"><strong>No SK</strong> - Nomor SK pembentukan</li>
                        <li style="margin-bottom: 6px;"><strong>Alamat Sekretariat</strong></li>
                        <li style="margin-bottom: 6px;"><strong>Nama Ketua</strong> - Nama ketua kota</li>
                        <li style="margin-bottom: 6px;"><strong>Penanggung Jawab Teknik</strong> - PJT unit/ranting</li>
                        <li style="margin-bottom: 6px;"><strong>Kontak</strong> - Nomor kontak ranting</li>
                    </ol>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;"><strong>Catatan: <span style="color: #dc3545;">Pastikan data negara, provinsi, dan kota sudah ada sebelum import unit/ranting.</span></strong></p>                              
                </div>
                
                <div class="tab-header" style="justify-content: flex-end;">
                    <a href="?download=ranting" class="template-link" style="background: #28a745; margin-left: 0;">📥 Download Template</a>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">⬆️ Upload & Import</button>
                        <a href="ranting.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
