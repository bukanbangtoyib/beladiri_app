<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include 'ukt_helper.php'; // Include helper functions
include '../../helpers/navbar.php';
include '../../auth/PermissionManager.php';
include '../../config/settings.php';

// Initialize permission manager
$permission_manager = new PermissionManager($conn, $_SESSION['user_id'], $_SESSION['role'], $_SESSION['pengurus_id'] ?? null, $_SESSION['ranting_id'] ?? null, $_SESSION['no_anggota'] ?? null);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// For pengkot role on UKT pages, use custom permission check instead of general permission
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'pengkot' || $user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    // Continue to UKT-specific permission check later
} else {
    if (!$permission_manager->can('anggota_read')) {
        die("❌ Akses ditolak!");
    }
}

include '../../config/settings.php';

$ukt_id = (int)($_GET['ukt_id'] ?? 0);

// Cek UKT ada
$ukt_check = $conn->query("SELECT * FROM ukt WHERE id = $ukt_id");
if (!$ukt_check || $ukt_check->num_rows == 0) {
    die("UKT tidak ditemukan!");
}
$ukt = $ukt_check->fetch_assoc();

// Check if user can manage this UKT - special handling for pengkot
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

$can_manage = false;

if ($user_role === 'pengkot') {
    // Pengkot can only manage their own city UKT
    $can_manage = ($ukt['jenis_penyelenggara'] === 'kota' && (int)$ukt['penyelenggara_id'] === (int)$user_pengurus_id);
} elseif ($user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    $can_manage = $permission_manager->canManageUKT('ukt_update', $ukt['jenis_penyelenggara'], $ukt['penyelenggara_id']);
}

if (!$can_manage) {
    die("❌ Akses ditolak! Anda tidak memiliki izin untuk import nilai UKT ini.");
}
$error = '';
$success = '';
$import_log = [];

// Handle download template
if (isset($_GET['download']) && $_GET['download'] === 'template') {
    $filename = "nilai_ukt_template.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No Anggota', 'Nilai A', 'Nilai B', 'Nilai C', 'Nilai D', 'Nilai E', 'Nilai F', 'Nilai G', 'Nilai H', 'Nilai I', 'Nilai J'], ';');
    fclose($output);
    exit();
}

// Cek UKT ada
$ukt_check = $conn->query("SELECT * FROM ukt WHERE id = $ukt_id");
if ($ukt_check->num_rows == 0) {
    die("UKT tidak ditemukan!");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya file CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Baca header
        $header = fgetcsv($handle, 0, ';');
        
        if ($header === false || count($header) < 11) {
            $error = "Format CSV tidak valid! Harus memiliki minimal 11 kolom (No Anggota + Nilai A-J). Pastikan pembatas adalah titik koma (;)";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $no_anggota_col = null;
            $nilai_cols = [];
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'no') !== false && strpos($col, 'anggota') !== false) {
                    $no_anggota_col = $idx;
                }
            }
            
            // Cari kolom nilai (setelah kolom no anggota)
            if ($no_anggota_col !== null) {
                for ($i = $no_anggota_col + 1; $i < count($header); $i++) {
                    if ($i - $no_anggota_col <= 10) { // Max 10 nilai (A-J)
                        $nilai_cols[$i - $no_anggota_col - 1] = $i; // Index 0-9 untuk A-J
                    }
                }
            }
            
            if ($no_anggota_col === null || count($nilai_cols) < 10) {
                $error = "CSV harus memiliki kolom 'No Anggota' dan minimal 10 kolom nilai (A-J). Pastikan pembatas adalah titik koma (;)";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                while ($row = fgetcsv($handle, 0, ';')) {
                    $row_num++;
                    
                    if (empty($row[0])) {
                        continue;
                    }
                    
                    // Ambil no_anggota
                    $no_anggota = trim($row[$no_anggota_col] ?? '');
                    
                    if (empty($no_anggota)) {
                        $import_log[] = "Baris $row_num: ⚠️ No Anggota kosong - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Cari peserta di UKT ini
                    $peserta_stmt = $conn->prepare("
                        SELECT up.id, up.anggota_id FROM ukt_peserta up
                        JOIN anggota a ON up.anggota_id = a.id
                        WHERE up.ukt_id = ? AND a.no_anggota = ?
                    ");
                    $peserta_stmt->bind_param("is", $ukt_id, $no_anggota);
                    $peserta_stmt->execute();
                    $peserta_result = $peserta_stmt->get_result();
                    
                    if ($peserta_result->num_rows == 0) {
                        // AUTO-REGISTER: Cari anggota di sistem (tabel anggota)
                        $anggota_stmt = $conn->prepare("SELECT id, tingkat_id FROM anggota WHERE no_anggota = ? LIMIT 1");
                        $anggota_stmt->bind_param("s", $no_anggota);
                        $anggota_stmt->execute();
                        $anggota_res = $anggota_stmt->get_result();
                        
                        if ($anggota_res->num_rows > 0) {
                            $anggota_data = $anggota_res->fetch_assoc();
                            $anggota_id = $anggota_data['id'];
                            $tingkat_dari_id = $anggota_data['tingkat_id'];
                            
                            // Hitung tingkat_ke
                            $tingkat_ke_id = null;
                            if ($tingkat_dari_id) {
                                $next_t = $conn->query("SELECT id FROM tingkatan WHERE urutan = (SELECT urutan + 1 FROM tingkatan WHERE id = $tingkat_dari_id) LIMIT 1");
                                if ($next_t->num_rows > 0) {
                                    $tingkat_ke_id = $next_t->fetch_assoc()['id'];
                                }
                            }
                            
                            // Daftar sebagai peserta baru
                            $ins_peserta = $conn->prepare("INSERT INTO ukt_peserta (ukt_id, anggota_id, tingkat_dari_id, tingkat_ke_id, status) VALUES (?, ?, ?, ?, 'peserta')");
                            $ins_peserta->bind_param("iiii", $ukt_id, $anggota_id, $tingkat_dari_id, $tingkat_ke_id);
                            
                            if ($ins_peserta->execute()) {
                                $peserta_id = $conn->insert_id;
                                $import_log[] = "Baris $row_num: 🆕 Anggota '" . formatNoAnggotaDisplay($no_anggota, $pengaturan_nomor) . "' berhasil didaftarkan sebagai peserta baru";
                            } else {
                                $import_log[] = "Baris $row_num: ❌ Gagal mendaftarkan anggota '$no_anggota' - " . $ins_peserta->error;
                                $skipped++;
                                continue;
                            }
                        } else {
                            $import_log[] = "Baris $row_num: ❌ No Anggota '" . formatNoAnggotaDisplay($no_anggota, $pengaturan_nomor) . "' tidak terdaftar di sistem";
                            $skipped++;
                            continue;
                        }
                    } else {
                        $peserta_data = $peserta_result->fetch_assoc();
                        $peserta_id = $peserta_data['id'];
                        $anggota_id = $peserta_data['anggota_id'];
                    }
                    
                    // Ambil nilai A-J
                    $letters = ['a','b','c','d','e','f','g','h','i','j'];
                    $vals = [];
                    $sum = 0;
                    $count = 0;
                    
                    foreach ($letters as $idx => $letter) {
                        $col_idx = $nilai_cols[$idx] ?? null;
                        if ($col_idx !== null && isset($row[$col_idx])) {
                            $v = trim($row[$col_idx]);
                            if ($v !== '') {
                                $v = (float)$v;
                                $vals[$letter] = $v;
                                $sum += $v;
                                $count++;
                            } else {
                                $vals[$letter] = null;
                            }
                        } else {
                            $vals[$letter] = null;
                        }
                    }
                    
                    // Hitung rata-rata hanya dari nilai yang ada
                    $avg = $count > 0 ? ($sum / $count) : null;
                    $status = 'peserta';
                    
                    if ($avg !== null) {
                        $status = $avg >= 60 ? 'lulus' : 'tidak_lulus';
                    }
                    
                    // Update peserta
                    $update_sql = "UPDATE ukt_peserta SET nilai_a = ?, nilai_b = ?, nilai_c = ?, nilai_d = ?, 
                                    nilai_e = ?, nilai_f = ?, nilai_g = ?, nilai_h = ?, nilai_i = ?, nilai_j = ?, 
                                    rata_rata = ?, status = ? 
                                    WHERE id = ?";
                    
                    $update_stmt = $conn->prepare($update_sql);
                    $avg_for_db = $avg;
                    
                    $update_stmt->bind_param("ddddddddddsii",
                        $vals['a'], $vals['b'], $vals['c'], $vals['d'], $vals['e'],
                        $vals['f'], $vals['g'], $vals['h'], $vals['i'], $vals['j'],
                        $avg_for_db, $status, $peserta_id
                    );
                    
                    if (!$update_stmt->execute()) {
                        $import_log[] = "Baris $row_num: ❌ Error update - " . $update_stmt->error;
                        $skipped++;
                        continue;
                    }
                    
                    // Jika lulus, naikkan tingkat dan update ukt_terakhir
                    if ($status == 'lulus') {
                        $today = date('Y-m-d');
                        
                        // Update ukt_terakhir ke tanggal hari ini
                        $conn->query("UPDATE anggota SET ukt_terakhir = '$today' WHERE id = $anggota_id");
                        
                        // Naikkan tingkat
                        $anggota_data = $conn->query("SELECT tingkat_id FROM anggota WHERE id = $anggota_id")->fetch_assoc();
                        $current_tingkat = $anggota_data['tingkat_id'];
                        
                        if (!empty($current_tingkat)) {
                            $next_query = $conn->query("
                                SELECT t2.id FROM tingkatan t1
                                JOIN tingkatan t2 ON t2.urutan = t1.urutan + 1
                                WHERE t1.id = $current_tingkat
                                LIMIT 1
                            ");
                            
                            if ($next_query->num_rows > 0) {
                                $next_data = $next_query->fetch_assoc();
                                $conn->query("UPDATE anggota SET tingkat_id = " . $next_data['id'] . " WHERE id = $anggota_id");
                            }
                        }
                    }
                    
                    $import_log[] = "Baris $row_num: ✓ Anggota '" . formatNoAnggotaDisplay($no_anggota, $pengaturan_nomor) . "' - Rata-rata: " . 
                                   ($avg !== null ? round($avg, 2) : '-') . " - Status: " . ucfirst($status);
                    $imported++;
                    $update_stmt->close();
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
    <title>Import Nilai UKT - Sistem Beladiri</title>
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
        
        h1 { margin-bottom: 15px; color: #333; }
        
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
        .info-box p { font-size: 13px; color: #333; margin-bottom: 8px; }
        
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
        
        .log-item {
            margin-bottom: 6px;
            color: #333;
            padding: 4px 0;
        }

        .tab-header {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .template-link {
            display: inline-block;
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .template-link:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <?php renderNavbar('📥 Import Nilai UKT'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Nilai UKT dari CSV</h1>
            
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
                <p><strong>CSV harus memiliki kolom:</strong></p>
                <ol style="margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333;">
                    <li style="margin-bottom: 4px;"><strong>No Anggota</strong> - Identitas unik anggota</li>
                    <li style="margin-bottom: 4px;"><strong>Nilai A s/d Nilai J</strong> - Nilai materi untuk tiap kolom</li>
                </ol>
                
                <p style="margin-top: 15px; color: #666; font-size: 12px;">
                    💡 <strong>Catatan:</strong><br>
                    • Rata-rata dihitung otomatis dari nilai yang terisi.<br>
                    • Status LULUS otomatis jika rata-rata ≥ 60.<br>
                    • Anggota yang lulus otomatis naik tingkat.<br>
                    • <strong>Auto-Register:</strong> Anggota baru di CSV otomatis didaftarkan ke UKT ini.
                </p>
            </div>

            <div class="tab-header">
                <a href="?ukt_id=<?php echo $ukt_id; ?>&download=template" class="template-link">📥 Download Template</a>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">📥 Upload & Import</button>
                    <a href="ukt_input_nilai.php?id=<?php echo $ukt_id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>