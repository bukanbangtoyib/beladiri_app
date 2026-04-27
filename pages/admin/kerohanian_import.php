<?php
session_start();

// Allow admin, negara, pengprov, pengkot to import
$allowed_roles = ['superadmin','admin', 'negara', 'pengprov', 'pengkot'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';

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
if (isset($_GET['download']) && $_GET['download'] === 'kerohanian') {
    $filename = "kerohanian_template.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Header
    fputcsv($output, ['anggota_id', 'tanggal_pembukaan', 'lokasi', 'pembuka_nama', 'penyelenggara', 'tingkat_pembuka_id', 'tingkat_id'], ';');
    // Examples
    fputcsv($output, ['ID001001.002-2017003', '2024-08-15', 'Gedung Olahraga Surabaya', 'Bapak Ahmad', 'Ranting Tenggilis', '3', '5'], ';');
    
    fclose($output);
    exit();
}

// Helper: parse tanggal
function parse_date_kerohanian($date_str) {
    if (empty($date_str)) return null;
    $date_str = trim($date_str);
    // Format dd/mm/yyyy atau dd-mm-yyyy
    if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $date_str, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    // Format YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_str)) {
        return $date_str;
    }
    return null;
}

// Helper: log import
function log_import_kerohanian($row_num, $message, $type = 'info') {
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
            $anggota_id_col        = null;
            $tanggal_col           = null;
            $lokasi_col            = null;
            $pembuka_nama_col      = null;
            $penyelenggara_col     = null;
            $tingkat_pembuka_col   = null;
            $tingkat_col           = null;

            foreach ($header as $idx => $col) {
                if ($col === 'anggota_id')           $anggota_id_col = $idx;
                if (strpos($col, 'tanggal') !== false) $tanggal_col = $idx;
                if (strpos($col, 'lokasi') !== false) $lokasi_col = $idx;
                if ($col === 'pembuka_nama' || (strpos($col, 'pembuka') !== false && strpos($col, 'tingkat') === false)) $pembuka_nama_col = $idx;
                if (strpos($col, 'penyelenggara') !== false) $penyelenggara_col = $idx;
                if ($col === 'tingkat_pembuka_id' || (strpos($col, 'tingkat_pembuka') !== false)) $tingkat_pembuka_col = $idx;
                if ($col === 'tingkat_id' && strpos($col, 'pembuka') === false) $tingkat_col = $idx;
            }

            // Validasi kolom wajib
            $missing = [];
            if ($anggota_id_col === null)   $missing[] = 'anggota_id';
            if ($tanggal_col === null)       $missing[] = 'tanggal_pembukaan';
            if ($lokasi_col === null)        $missing[] = 'lokasi';
            if ($pembuka_nama_col === null)  $missing[] = 'pembuka_nama';

            if (!empty($missing)) {
                $error = "CSV harus memiliki kolom: " . implode(', ', $missing) . ". Pastikan pembatas adalah titik koma (;)";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;

                // Prepared statement check duplikat
                $check_stmt = $conn->prepare("SELECT id FROM kerohanian WHERE anggota_id = ?");

                // Prepared statement insert
                $insert_stmt = $conn->prepare(
                    "INSERT INTO kerohanian (anggota_id, tanggal_pembukaan, lokasi, pembuka_nama, penyelenggara, tingkat_pembuka_id, tingkat_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                while ($row = fgetcsv($handle, 0, ';')) {
                    $row_num++;
                    if (empty($row[0])) continue;

                    // Ambil data
                    $anggota_raw     = trim($row[$anggota_id_col] ?? '');
                    $tanggal_raw     = trim($row[$tanggal_col] ?? '');
                    $lokasi          = trim($row[$lokasi_col] ?? '');
                    $pembuka_nama    = trim($row[$pembuka_nama_col] ?? '');
                    $penyelenggara   = isset($penyelenggara_col) ? trim($row[$penyelenggara_col] ?? '') : '';
                    $tingkat_pembuka_id = isset($tingkat_pembuka_col) && !empty($row[$tingkat_pembuka_col]) ? (int)$row[$tingkat_pembuka_col] : null;
                    $tingkat_id      = isset($tingkat_col) && !empty($row[$tingkat_col]) ? (int)$row[$tingkat_col] : null;

                    // Validasi data wajib
                    if (empty($anggota_raw) || empty($tanggal_raw) || empty($lokasi) || empty($pembuka_nama)) {
                        log_import_kerohanian($row_num, "Data tidak lengkap (anggota_id, tanggal_pembukaan, lokasi, atau pembuka_nama kosong) - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }

                    // Parse tanggal
                    $tanggal = parse_date_kerohanian($tanggal_raw);
                    if (!$tanggal) {
                        log_import_kerohanian($row_num, "Format tanggal '$tanggal_raw' tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY) - dilewati", 'error');
                        $skipped++;
                        continue;
                    }

                    // Cari anggota: bisa berupa integer ID atau no_anggota (string)
                    $anggota_id = null;
                    $nama_anggota = '';
                    if (ctype_digit($anggota_raw)) {
                        // Format angka murni → cari berdasarkan id
                        $anggota_esc = (int)$anggota_raw;
                        $cek_anggota = $conn->query("SELECT id, nama_lengkap FROM anggota WHERE id = $anggota_esc");
                    } else {
                        // Format no_anggota (mis: ID001001.002-2017003) → cari berdasarkan no_anggota
                        $anggota_esc = $conn->real_escape_string($anggota_raw);
                        $cek_anggota = $conn->query("SELECT id, nama_lengkap FROM anggota WHERE no_anggota = '$anggota_esc'");
                    }

                    if (!$cek_anggota || $cek_anggota->num_rows == 0) {
                        log_import_kerohanian($row_num, "Anggota '$anggota_raw' tidak ditemukan di database - dilewati", 'error');
                        $skipped++;
                        continue;
                    }
                    $anggota_data = $cek_anggota->fetch_assoc();
                    $anggota_id   = (int)$anggota_data['id'];
                    $nama_anggota = $anggota_data['nama_lengkap'];

                    // Cek duplikat: anggota sudah punya data kerohanian
                    $check_stmt->bind_param("i", $anggota_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($check_result->num_rows > 0) {
                        log_import_kerohanian($row_num, "Anggota '$nama_anggota' (ID: $anggota_id) sudah memiliki data kerohanian - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }

                    // Insert data
                    $insert_stmt->bind_param("issssii",
                        $anggota_id,
                        $tanggal,
                        $lokasi,
                        $pembuka_nama,
                        $penyelenggara,
                        $tingkat_pembuka_id,
                        $tingkat_id
                    );

                    if ($insert_stmt->execute()) {
                        // Update status kerohanian di tabel anggota jika ada kolom tersebut
                        $conn->query("UPDATE anggota SET status_kerohanian = 'sudah', tanggal_pembukaan_kerohanian = '$tanggal' WHERE id = $anggota_id");
                        log_import_kerohanian($row_num, "Anggota '$nama_anggota' (ID: $anggota_id) berhasil ditambahkan", 'success');
                        $imported++;
                    } else {
                        log_import_kerohanian($row_num, "Error insert anggota ID $anggota_id - " . $insert_stmt->error, 'error');
                        $skipped++;
                    }
                }

                fclose($handle);
                $check_stmt->close();
                $insert_stmt->close();

                $success = "Import selesai! $imported data berhasil ditambahkan, $skipped dilewati.";
            }
        }
    }
}

// Ambil daftar tingkatan untuk referensi
$tingkatan_list = $conn->query("SELECT id, nama_tingkat FROM tingkatan ORDER BY urutan");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Kerohanian - Sistem Beladiri</title>
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
        .info-box p { font-size: 13px; color: #333; margin-bottom: 8px; }
        .info-box ol { margin-left: 20px; margin-top: 8px; font-size: 13px; color: #333; }
        .info-box ol li { margin-bottom: 6px; }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
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
        .template-link:hover { background: #218838; }
        .ref-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
        }
        .ref-table th, .ref-table td {
            border: 1px solid #ddd;
            padding: 6px 10px;
            text-align: left;
        }
        .ref-table th { background: #e8f0fe; font-weight: 600; }
        .ref-table tr:nth-child(even) { background: #f9f9f9; }
        .note-box {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            margin-top: 15px;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
        }
    </style>
</head>
<body>
    <?php renderNavbar('Import Kerohanian'); ?>

    <div style="display: flex; justify-content: center;">
        <div class="container" style="width: 100%;">
            <div class="form-container">
                <h1>Import Kerohanian dari CSV</h1>
                <p style="color: #666; margin-bottom: 20px;">Upload file CSV untuk menambahkan data pembukaan kerohanian secara massal.</p>

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
                    <p><strong>Kolom yang diperlukan dalam file CSV:</strong></p>
                    <ol>
                        <li><strong>anggota_id</strong> <span style="color:#dc3545;">*</span> — ID anggota (angka, mis: <code>5</code>) <strong>atau</strong> No. Anggota (mis: <code>ID001001.002-2017003</code>)</li>
                        <li><strong>tanggal_pembukaan</strong> <span style="color:#dc3545;">*</span> — Format: YYYY-MM-DD atau DD/MM/YYYY</li>
                        <li><strong>lokasi</strong> <span style="color:#dc3545;">*</span> — Tempat pelaksanaan pembukaan</li>
                        <li><strong>pembuka_nama</strong> <span style="color:#dc3545;">*</span> — Nama pembuka kerohanian</li>
                        <li><strong>penyelenggara</strong> — Nama penyelenggara</li>
                        <li><strong>tingkat_pembuka_id</strong> — ID tingkat pembuka (lihat tabel referensi)</li>
                        <li><strong>tingkat_id</strong> — ID tingkat anggota saat pembukaan (lihat tabel referensi)</li>
                    </ol>

                    <p style="margin-top: 12px; font-size: 12px; color: #666;">
                        <span style="color: #dc3545;">*</span> = Kolom wajib diisi
                    </p>

                    <div class="note-box">
                        ⚠️ <strong>Catatan:</strong> Anggota yang sudah memiliki data kerohanian akan dilewati (tidak ditimpa).
                        Gunakan fitur Edit jika ingin mengubah data yang sudah ada.
                    </div>
                </div>

                <?php if ($tingkatan_list && $tingkatan_list->num_rows > 0): ?>
                <div class="info-box" style="margin-bottom: 20px;">
                    <h4>📊 Referensi ID Tingkatan</h4>
                    <p style="font-size: 12px; color: #555; margin-bottom: 8px;">Gunakan ID berikut untuk kolom <strong>tingkat_pembuka_id</strong> dan <strong>tingkat_id</strong>:</p>
                    <table class="ref-table">
                        <thead>
                            <tr><th>ID</th><th>Nama Tingkat</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $tingkatan_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $t['id']; ?></td>
                                <td><?php echo htmlspecialchars($t['nama_tingkat']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="tab-header">
                    <a href="?download=kerohanian" class="template-link">📥 Download Template</a>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">⬆️ Upload &amp; Import</button>
                        <a href="kerohanian.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>