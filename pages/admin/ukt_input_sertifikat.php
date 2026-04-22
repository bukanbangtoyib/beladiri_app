<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';
include '../../config/settings.php';
include 'ukt_helper.php';


// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn, 
    $_SESSION['user_id'], 
    $_SESSION['role'], 
    $_SESSION['pengurus_id'] ?? null, 
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$id = (int)$_GET['id']; // id dari ukt_peserta
$ukt_id = (int)$_GET['ukt_id']; // id dari ukt

$error = '';
$success = '';

// Ambil data peserta UKT
$peserta_sql = "SELECT up.*, a.nama_lengkap, a.no_anggota, u.tanggal_pelaksanaan, t2.nama_tingkat as tingkat_ke, r.nama_ranting
                FROM ukt_peserta up
                JOIN anggota a ON up.anggota_id = a.id
                JOIN ukt u ON up.ukt_id = u.id
                LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
                LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id
                WHERE up.id = $id AND up.ukt_id = $ukt_id";

$peserta_result = $conn->query($peserta_sql);
if ($peserta_result->num_rows == 0) {
    die("❌ Peserta UKT tidak ditemukan!");
}

$peserta = $peserta_result->fetch_assoc();

// Hanya yang lulus bisa upload sertifikat
if ($peserta['status'] != 'lulus') {
    die("❌ Hanya peserta yang LULUS yang bisa memiliki sertifikat!");
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['sertifikat']) && $_FILES['sertifikat']['size'] > 0) {
        $file = $_FILES['sertifikat'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi
        if ($file_ext != 'pdf') {
            $error = "❌ File harus berformat PDF!";
        } elseif ($file['size'] > 5242880) { // 5MB
            $error = "❌ Ukuran file maksimal 5MB!";
        } else {
            // Buat folder jika belum ada
            $upload_dir = '../../uploads/sertifikat_ukt/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Format nama file: Sert-UKT-DDMMYYYY-NamaLengkap-Ranting.pdf
            // Contoh: Sert-UKT-27012026-Budi_Santoso-Ranting_A.pdf
            $tanggal_ukt = date('dmY', strtotime($peserta['tanggal_pelaksanaan']));
            // Sanitasi nama lengkap - hanya alphanumeric, spasi, dan hyphen
            $nama_clean = preg_replace("/[^a-zA-Z0-9 -]/i", "", $peserta['nama_lengkap']);
            $nama_clean = preg_replace('/\s+/', '_', trim($nama_clean));
            $nama_clean = substr($nama_clean, 0, 50); // Batasi panjang nama
            
            // Sanitasi nama ranting
            $ranting_val = $peserta['nama_ranting'] ?? 'Tanpa_Ranting';
            $ranting_clean = preg_replace("/[^a-zA-Z0-9 -]/i", "", $ranting_val);
            $ranting_clean = preg_replace('/\s+/', '_', trim($ranting_clean));
            $ranting_clean = substr($ranting_clean, 0, 30);

            $file_name = 'Sert-UKT-' . $tanggal_ukt . '-' . $nama_clean . '-' . $ranting_clean . '.pdf';
            $file_path = $upload_dir . basename($file_name); // Pencegahan path traversal
            
            // Hapus file lama jika ada
            if (!empty($peserta['sertifikat_path'])) {
                $old_file = $upload_dir . $peserta['sertifikat_path'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Upload file baru
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Update database
                $update_sql = "UPDATE ukt_peserta SET sertifikat_path = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                
                if ($stmt) {
                    $stmt->bind_param("si", $file_name, $id);
                    
                    if ($stmt->execute()) {
                        $success = "✓ Sertifikat berhasil diupload!";
                        // Refresh data
                        $peserta_result = $conn->query($peserta_sql);
                        $peserta = $peserta_result->fetch_assoc();
                    } else {
                        $error = "❌ Error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "❌ Error prepare: " . $conn->error;
                }
            } else {
                $error = "❌ Gagal upload file!";
            }
        }
    } else {
        $error = "❌ Pilih file terlebih dahulu!";
    }
}

// Cek apakah file sertifikat ada
$sertifikat_exists = false;
$sertifikat_path = null;

if (!empty($peserta['sertifikat_path'])) {
    $full_path = '../../uploads/sertifikat_ukt/' . $peserta['sertifikat_path'];
    if (file_exists($full_path)) {
        $sertifikat_exists = true;
        $sertifikat_path = $full_path;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Sertifikat UKT - Sistem Beladiri</title>
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
            max-width: 800px;
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
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 20px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .label {
            color: #666;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .value {
            color: #333;
            font-size: 15px;
            font-weight: 500;
        }
        
        .value.highlight {
            color: #667eea;
            font-weight: 700;
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
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="file"] {
            width: 100%;
            padding: 11px 14px;
            border: 2px dashed #667eea;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        input[type="file"]:focus {
            outline: none;
            border-color: #5568d3;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
            font-style: italic;
        }
        
        .existing-certificate {
            background: #f0f7ff;
            border: 1px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .existing-certificate h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .certificate-info {
            color: #333;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .file-name {
            background: white;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 3px solid #667eea;
            font-family: monospace;
            font-size: 13px;
            color: #667eea;
            word-break: break-all;
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
        
        .btn-download {
            background: #28a745;
            color: white;
            padding: 10px 24px;
            font-size: 13px;
        }
        
        .btn-download:hover {
            background: #218838;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        hr {
            margin: 40px 0;
            border: none;
            border-top: 2px solid #f0f0f0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-lulus {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <?php renderNavbar('📜 Upload Sertifikat UKT'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Upload Sertifikat Kelulusan UKT</h1>
            <p class="subtitle">Unggah sertifikat PDF untuk peserta yang telah lulus UKT</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Info Peserta -->
            <div class="info-card">
                <div class="info-row">
                    <div class="label">No Anggota</div>
                    <div class="value"><?php echo formatNoAnggotaDisplay($peserta['no_anggota'], $pengaturan_nomor); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Nama Anggota</div>
                    <div class="value highlight"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Tanggal UKT</div>
                    <div class="value"><?php echo date('d M Y', strtotime($peserta['tanggal_pelaksanaan'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Tingkat Kenaikan</div>
                    <div class="value"><?php echo $peserta['tingkat_ke'] ?? '-'; ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="status-badge status-lulus">✓ LULUS</span>
                    </div>
                </div>
            </div>
            
            <?php if ($sertifikat_exists): ?>
            <!-- Sertifikat Sudah Ada -->
            <div class="existing-certificate">
                <h3>📄 Sertifikat Saat Ini</h3>
                <p class="certificate-info">Sertifikat telah diupload sebelumnya. Anda dapat menggantinya dengan mengunggah file baru.</p>
                <div class="file-name"><?php echo htmlspecialchars($peserta['sertifikat_path']); ?></div>
                <a href="<?php echo $sertifikat_path; ?>" class="btn btn-download" target="_blank" download>⬇️ Download Sertifikat</a>
            </div>
            
            <hr>
            
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
                Atau unggah sertifikat baru untuk menggantikan yang lama:
            </p>
            <?php endif; ?>
            
            <!-- Form Upload -->
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="sertifikat">
                        <?php echo $sertifikat_exists ? 'Ganti Sertifikat' : 'Pilih File Sertifikat'; ?> (PDF) <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="file" id="sertifikat" name="sertifikat" accept=".pdf" required>
                    <div class="form-hint">
                        Format: PDF | Ukuran maksimal: 5MB<br>
                        Nama file akan otomatis menjadi: <strong>Sert-UKT-DDMMYYYY-NamaLengkap-Ranting.pdf</strong><br>
                        Contoh: <strong>Sert-UKT-27012026-Budi_Santoso-Ranting_A.pdf</strong>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Upload Sertifikat</button>
                    <a href="ukt_detail.php?id=<?php echo $ukt_id; ?>" class="btn btn-secondary">🔙 Kembali</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>