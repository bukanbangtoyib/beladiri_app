<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];
$error = '';
$success = '';

$result = $conn->query("SELECT * FROM ranting WHERE id = $id");
if ($result->num_rows == 0) {
    die("Unit/Ranting tidak ditemukan!");
}
$ranting = $result->fetch_assoc();

// Get pengurus name for SK naming
$pengurus_result = $conn->query("SELECT nama_pengurus FROM pengurus WHERE id = " . $ranting['pengurus_kota_id']);
$pengurus = $pengurus_result->fetch_assoc();
$pengurus_name = $pengurus ? $pengurus['nama_pengurus'] : 'unknown';

// Helper function untuk sanitasi nama
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "_", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
}

// Function untuk mendapatkan nomor revisi berikutnya
function get_next_revision_number($upload_dir, $ranting_name, $pengurus_name) {
    $ranting_clean = sanitize_name($ranting_name);
    $pengurus_clean = sanitize_name($pengurus_name);
    $pattern = 'SK-' . $ranting_clean . '-' . $pengurus_clean . '-';
    $max_revision = 0;
    
    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if (strpos($file, $pattern) === 0) {
                // Extract nomor revisi dari format: SK-ranting-pengurus-XX.ext
                if (preg_match('/-(\d{2})\.[^.]+$/', $file, $matches)) {
                    $revision = (int)$matches[1];
                    if ($revision > $max_revision) {
                        $max_revision = $revision;
                    }
                }
            }
        }
    }
    
    return $max_revision + 1;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_ranting = $conn->real_escape_string($_POST['nama_ranting']);
    $jenis = $_POST['jenis'];
    $tanggal_sk = $_POST['tanggal_sk'];
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $ketua_nama = $conn->real_escape_string($_POST['ketua_nama']);
    $penanggung_jawab = $conn->real_escape_string($_POST['penanggung_jawab']);
    $no_kontak = $_POST['no_kontak'];
    $pengurus_kota_id = $_POST['pengurus_kota_id'];
    
    // Get pengurus name yang baru (jika berubah)
    $pengurus_check = $conn->query("SELECT nama_pengurus FROM pengurus WHERE id = " . (int)$pengurus_kota_id);
    $pengurus_data = $pengurus_check->fetch_assoc();
    $pengurus_name = $pengurus_data ? $pengurus_data['nama_pengurus'] : 'unknown';
    
    // Handle SK upload
    if (isset($_FILES['sk_pembentukan']) && $_FILES['sk_pembentukan']['size'] > 0) {
        $file = $_FILES['sk_pembentukan'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi file
        if (strtolower($file_ext) != 'pdf') {
            $error = "Hanya file PDF yang diperbolehkan untuk SK!";
        } elseif ($file['size'] > 5242880) { // 5MB
            $error = "Ukuran file SK maksimal 5MB!";
        } else {
            // Simpan file dengan naming convention: SK-nama_ranting-nama_pengurus-XX.pdf
            $upload_dir = '../../uploads/sk_pembentukan/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Dapatkan nomor revisi berikutnya
            $next_revision = get_next_revision_number($upload_dir, $nama_ranting, $pengurus_name);
            
            // Format: SK-nama_ranting-nama_pengurus-XX.pdf
            $ranting_clean = sanitize_name($nama_ranting);
            $pengurus_clean = sanitize_name($pengurus_name);
            $file_name = 'SK-' . $ranting_clean . '-' . $pengurus_clean . '-' . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . '.pdf';
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $success = "SK pembentukan berhasil diupload! (Revisi " . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . ")";
            } else {
                $error = "Gagal upload file SK!";
            }
        }
    }
    
    if (!$error) {
        $sql = "UPDATE ranting SET 
                nama_ranting = ?, jenis = ?, tanggal_sk_pembentukan = ?,
                alamat = ?, ketua_nama = ?, penanggung_jawab_teknik = ?,
                no_kontak = ?, pengurus_kota_id = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssiii", $nama_ranting, $jenis, $tanggal_sk,
                         $alamat, $ketua_nama, $penanggung_jawab,
                         $no_kontak, $pengurus_kota_id, $id);
        
        if ($stmt->execute()) {
            if (!$success) {
                $success = "Data unit/ranting berhasil diupdate!";
            } else {
                $success .= " | Data unit/ranting juga berhasil diupdate!";
            }
            header("refresh:2;url=ranting_detail.php?id=$id");
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

$pengurus_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' ORDER BY nama_pengurus");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Unit/Ranting - Sistem Beladiri</title>
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
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 22px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea { resize: vertical; min-height: 100px; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-row.full { grid-template-columns: 1fr; }
        
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 6px; }
        
        hr { margin: 40px 0; border: none; border-top: 2px solid #f0f0f0; }
        h3 { color: #333; margin-bottom: 25px; padding-bottom: 12px; border-bottom: 2px solid #667eea; }
        
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 15px; margin-top: 35px; }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .info-box {
            background: #f0f7ff;
            padding: 15px;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .info-box strong { color: #667eea; }
        
        .code {
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>✏️ Edit Unit/Ranting</h2>
        <a href="ranting_detail.php?id=<?php echo $id; ?>" style="color: white;">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h1>Edit Data Unit/Ranting</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <h3>📋 Informasi Dasar</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Unit/Ranting <span class="required">*</span></label>
                        <input type="text" name="nama_ranting" value="<?php echo htmlspecialchars($ranting['nama_ranting']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jenis <span class="required">*</span></label>
                        <select name="jenis" required>
                            <option value="ukm" <?php echo $ranting['jenis'] == 'ukm' ? 'selected' : ''; ?>>UKM Perguruan Tinggi</option>
                            <option value="ranting" <?php echo $ranting['jenis'] == 'ranting' ? 'selected' : ''; ?>>Ranting</option>
                            <option value="unit" <?php echo $ranting['jenis'] == 'unit' ? 'selected' : ''; ?>>Unit</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal SK Pembentukan <span class="required">*</span></label>
                        <input type="date" name="tanggal_sk" value="<?php echo $ranting['tanggal_sk_pembentukan']; ?>" required>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Alamat <span class="required">*</span></label>
                        <textarea name="alamat" required><?php echo htmlspecialchars($ranting['alamat']); ?></textarea>
                    </div>
                </div>
                
                <hr>
                
                <h3>📄 SK Pembentukan</h3>
                
                <div class="info-box">
                    <strong>ℹ️ Format Nama File SK:</strong><br>
                    <span class="code">SK-{nama_ranting}-{nama_pengurus}-XX.pdf</span><br><br>
                    Contoh: <span class="code">SK-SMP_1-PengKot_Surabaya-01.pdf</span><br><br>
                    Setiap upload file baru akan otomatis menambah nomor revisi (01 → 02 → 03, dst).
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Upload SK Pembentukan (PDF)</label>
                        <input type="file" name="sk_pembentukan" accept=".pdf">
                        <div class="form-hint">Format: PDF | Ukuran maksimal: 5MB | Kosongkan jika tidak ingin mengubah SK</div>
                    </div>
                </div>
                
                <hr>
                
                <h3>👤 Struktur Organisasi</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Ketua <span class="required">*</span></label>
                        <input type="text" name="ketua_nama" value="<?php echo htmlspecialchars($ranting['ketua_nama'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Penanggung Jawab Teknik</label>
                        <input type="text" name="penanggung_jawab" value="<?php echo htmlspecialchars($ranting['penanggung_jawab_teknik'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>No Kontak <span class="required">*</span></label>
                        <input type="tel" name="no_kontak" value="<?php echo htmlspecialchars($ranting['no_kontak'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Pengurus Kota yang Menaungi <span class="required">*</span></label>
                        <select name="pengurus_kota_id" required>
                            <option value="">-- Pilih Pengurus Kota --</option>
                            <?php while ($row = $pengurus_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $ranting['pengurus_kota_id'] == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nama_pengurus']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="ranting_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>