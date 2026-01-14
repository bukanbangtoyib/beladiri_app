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

// Download CSV template
if (isset($_GET['download_template']) && $_GET['download_template'] === 'ukt_peserta') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ukt_peserta_template.csv"');
    echo "no_anggota\n"; // header only
    exit();
}

$id = (int)$_GET['id'];
$error = '';
$success = '';

// Cek UKT ada
$ukt_check = $conn->query("SELECT * FROM ukt WHERE id = $id");
if ($ukt_check->num_rows == 0) {
    die("UKT tidak ditemukan!");
}
$ukt = $ukt_check->fetch_assoc();

// Proses form submit (single add atau bulk CSV)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- Bulk CSV upload ---
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors = [];
        $inserted = 0;
        $skip_header = isset($_POST['skip_header']) ? true : false;

        // Basic validation
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "Terjadi error saat mengunggah file CSV.";
        } else {
            $tmp = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($tmp, 'r')) !== false) {
                if ($skip_header) {
                    fgetcsv($handle); // discard header
                }

                // Prepared statements
                $stmt_check = $conn->prepare("SELECT id FROM ukt_peserta WHERE ukt_id = ? AND anggota_id = ?");
                $stmt_insert = $conn->prepare("INSERT INTO ukt_peserta (ukt_id, anggota_id, tingkat_dari_id, tingkat_ke_id, status) VALUES (?, ?, ?, ?, 'peserta')");

                $line = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $line++;
                    // Expect first column to be no_anggota (or anggota_id)
                    $raw = isset($row[0]) ? trim($row[0]) : '';
                    if ($raw === '') {
                        $errors[] = "Baris $line: kolom 'no_anggota' kosong; dilewati.";
                        continue;
                    }

                    // Try to find anggota by no_anggota first, fallback to ID if numeric
                    $no_anggota_esc = $conn->real_escape_string($raw);
                    $anggota_res = $conn->query("SELECT id, tingkat_id FROM anggota WHERE no_anggota = '$no_anggota_esc' LIMIT 1");
                    if ($anggota_res->num_rows == 0 && ctype_digit($raw)) {
                        $anggota_res = $conn->query("SELECT id, tingkat_id FROM anggota WHERE id = " . (int)$raw . " LIMIT 1");
                    }

                    if (!$anggota_res || $anggota_res->num_rows == 0) {
                        $errors[] = "Baris $line: anggota dengan identifier '$raw' tidak ditemukan; dilewati.";
                        continue;
                    }

                    $angg = $anggota_res->fetch_assoc();
                    $anggota_id = (int)$angg['id'];
                    $tingkat_dari_id = isset($angg['tingkat_id']) ? (int)$angg['tingkat_id'] : 0;

                    // Cek duplicate
                    $stmt_check->bind_param('ii', $id, $anggota_id);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) {
                        $errors[] = "Baris $line: anggota '$raw' sudah terdaftar di UKT ini; dilewati.";
                        continue;
                    }

                    // Hitung tingkat_ke
                    $tingkat_ke_id = null;
                    if ($tingkat_dari_id) {
                        $current_tingkat = $conn->query("SELECT urutan FROM tingkatan WHERE id = $tingkat_dari_id")->fetch_assoc();
                        if ($current_tingkat) {
                            $next_tingkat = $conn->query("SELECT id FROM tingkatan WHERE urutan = " . ($current_tingkat['urutan'] + 1) . " LIMIT 1");
                            if ($next_tingkat->num_rows > 0) {
                                $next_data = $next_tingkat->fetch_assoc();
                                $tingkat_ke_id = $next_data['id'];
                            }
                        }
                    }

                    // Insert
                    $stmt_insert->bind_param('iiii', $id, $anggota_id, $tingkat_dari_id, $tingkat_ke_id);
                    if ($stmt_insert->execute()) {
                        $inserted++;
                    } else {
                        $errors[] = "Baris $line: gagal menambahkan anggota '$raw' (" . $stmt_insert->error . ");";
                    }
                }

                fclose($handle);
                $success = "$inserted peserta berhasil ditambahkan dari file CSV.";
                if (count($errors) > 0) {
                    $error = implode('<br>', $errors);
                }
            } else {
                $error = "Tidak dapat membuka file CSV.";
            }
        }
    } else {
        // --- Single insert (existing behavior) ---
        $anggota_id = (int)$_POST['anggota_id'];
        // Ambil tingkat anggota langsung dari tabel anggota (kolom 'Tingkat' di form dihapus)
        $anggota_row = $conn->query("SELECT tingkat_id FROM anggota WHERE id = $anggota_id")->fetch_assoc();
        $tingkat_dari_id = isset($anggota_row['tingkat_id']) ? (int)$anggota_row['tingkat_id'] : 0;
        
        // Cek apakah anggota sudah terdaftar di UKT ini
        $check = $conn->query("SELECT id FROM ukt_peserta WHERE ukt_id = $id AND anggota_id = $anggota_id");
        if ($check->num_rows > 0) {
            $error = "Anggota sudah terdaftar di UKT ini!";
        } else {
            // Cari tingkat ke (next level)
            $current_tingkat = $conn->query("SELECT urutan FROM tingkatan WHERE id = $tingkat_dari_id")->fetch_assoc();
            $tingkat_ke_id = null;
            
            if ($current_tingkat) {
                $next_tingkat = $conn->query("SELECT id FROM tingkatan WHERE urutan = " . ($current_tingkat['urutan'] + 1) . " LIMIT 1");
                if ($next_tingkat->num_rows > 0) {
                    $next_data = $next_tingkat->fetch_assoc();
                    $tingkat_ke_id = $next_data['id'];
                }
            }
            
            $sql = "INSERT INTO ukt_peserta (ukt_id, anggota_id, tingkat_dari_id, tingkat_ke_id, status) 
                    VALUES (?, ?, ?, ?, 'peserta')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiii", $id, $anggota_id, $tingkat_dari_id, $tingkat_ke_id);
            
            if ($stmt->execute()) {
                $success = "Peserta berhasil ditambahkan!";
                // Clear form
                $_POST = array();
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }
}

// Ambil daftar anggota
$anggota_result = $conn->query("SELECT a.id, a.no_anggota, a.nama_lengkap, a.tingkat_id, t.nama_tingkat 
                                FROM anggota a 
                                LEFT JOIN tingkatan t ON a.tingkat_id = t.id
                                ORDER BY a.nama_lengkap");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Peserta UKT - Sistem Beladiri</title>
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
        
        h1 { color: #333; margin-bottom: 10px; }
        h2 { color: #666; font-size: 14px; margin-bottom: 25px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
        
        input[type="text"], select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 5px; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .info-box {
            background: #f0f7ff;
            padding: 15px;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            margin-bottom: 25px;
        }
        
        .info-box strong { color: #667eea; }
        
        .anggota-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 13px;
            display: none;
        }
        
        .anggota-info.show { display: block; }
    </style>
</head>
<body>
    <?php renderNavbar('➕ Tambah Peserta UKT'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Tambah Peserta UKT</h1>
            <h2>UKT: <?php echo date('d M Y', strtotime($ukt['tanggal_pelaksanaan'])); ?> - <?php echo htmlspecialchars($ukt['lokasi']); ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>ℹ️ Informasi:</strong> Pilih anggota. Tingkat target akan otomatis naik 1 level dari tingkat saat ini.
            </div>

            <div style="margin-bottom:20px;">
                <h3 style="margin-bottom:8px;">Tambah Data Masal (CSV)</h3>
                <p style="font-size:13px;color:#555;margin-top:0;">Unggah file CSV dengan kolom <code>no_anggota</code> (atau <code>anggota_id</code>). Unduh template: <a href="?download_template=ukt_peserta&amp;id=<?php echo $id; ?>">Download CSV Template</a></p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>File CSV <span class="required">*</span></label>
                        <input type="file" name="csv_file" accept=".csv" required>
                        <div class="form-hint">Setiap baris berisi <code>no_anggota</code> atau <code>anggota_id</code>. Baris pertama bisa berupa header.</div>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="skip_header" checked> File memiliki baris header (akan dilewati)</label>
                    </div>
                    <div class="button-group" style="margin-bottom:18px;">
                        <button type="submit" class="btn btn-primary">✓ Unggah CSV</button>
                        <a href="ukt_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Anggota <span class="required">*</span></label>
                    <select name="anggota_id" id="anggota_select" required onchange="updateTingkat()">
                        <option value="">-- Pilih Anggota --</option>
                        <?php while ($row = $anggota_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" data-tingkat-id="<?php echo $row['tingkat_id']; ?>" data-tingkat-nama="<?php echo htmlspecialchars($row['nama_tingkat']); ?>">
                                <?php echo $row['no_anggota']; ?> - <?php echo htmlspecialchars($row['nama_lengkap']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-hint">Pilih anggota yang akan mengikuti UKT</div>
                </div>
                
                <div class="anggota-info" id="anggota-info">
                    <div style="margin-bottom: 8px;">
                        <strong>Tingkat Saat Ini:</strong> <span id="tingkat-saat-ini">-</span>
                    </div>
                    <div>
                        <strong>Tingkat Target:</strong> <span id="tingkat-target">-</span>
                    </div>
                </div>
                
                <!-- Kolom 'Tingkat' dihapus; tingkat diambil otomatis dari data anggota -->
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">✓ Tambah Peserta</button>
                    <a href="ukt_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const anggotaSelect = document.getElementById('anggota_select');
        const anggotaInfo = document.getElementById('anggota-info');
        const tingkatSaatIni = document.getElementById('tingkat-saat-ini');
        const tingkatTarget = document.getElementById('tingkat-target');
        
        function updateTingkat() {
            const selectedOption = anggotaSelect.options[anggotaSelect.selectedIndex];
            const tingkatId = selectedOption.getAttribute('data-tingkat-id');
            const tingkatNama = selectedOption.getAttribute('data-tingkat-nama');
            
            if (tingkatId && tingkatId !== 'null') {
                // Tampilkan info
                anggotaInfo.classList.add('show');
                tingkatSaatIni.textContent = tingkatNama;
                
                // Load tingkat target (next level)
                fetch('get_next_tingkat.php?tingkat_id=' + tingkatId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.next_tingkat) {
                            tingkatTarget.textContent = data.next_tingkat.nama_tingkat;
                        } else {
                            tingkatTarget.textContent = 'Pendekar (Tingkat Tertinggi)';
                        }
                    });
            } else {
                anggotaInfo.classList.remove('show');
            }
        }
    </script>
</body>
</html>
