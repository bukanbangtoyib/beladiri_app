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

$id = (int)$_GET['id'];
$error = '';
$success = '';

$result = $conn->query("SELECT * FROM anggota WHERE id = $id");
if ($result->num_rows == 0) {
    die("Anggota tidak ditemukan!");
}
$anggota = $result->fetch_assoc();

// Helper function untuk sanitasi nama
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $tempat_lahir = $conn->real_escape_string($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $ranting_saat_ini_id = $_POST['ranting_saat_ini_id'] ?: NULL;
    $tingkat_id = $_POST['tingkat_id'] ?: NULL;
    $jenis_anggota = $_POST['jenis_anggota'];
    
    // Handle foto upload / penggantian
    $foto_path = $anggota['nama_foto']; // default: foto lama
    
    if (isset($_FILES['foto']) && $_FILES['foto']['size'] > 0) {
        $file = $_FILES['foto'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi
        if (!in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
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
            
            // Upload foto baru dengan format: NoAnggota_NamaLengkap.ext
            // Contoh: AGT-2024-001_Budi_Santoso.jpg
            $nama_clean = sanitize_name($nama_lengkap);
            $file_name = $anggota['no_anggota'] . '_' . $nama_clean . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $foto_path = $file_name;
            } else {
                $error = "Gagal upload foto!";
            }
        }
    }
    
    if (!$error) {
        $sql = "UPDATE anggota SET 
                nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, 
                jenis_kelamin = ?, ranting_saat_ini_id = ?, tingkat_id = ?, 
                jenis_anggota = ?, nama_foto = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Total 9 parameter: 6 string + 2 integer + 1 string
            $stmt->bind_param(
                "sssssiiis",
                $nama_lengkap, 
                $tempat_lahir, 
                $tanggal_lahir, 
                $jenis_kelamin,
                $ranting_saat_ini_id, 
                $tingkat_id, 
                $jenis_anggota,
                $foto_path,
                $id
            );
            
            if ($stmt->execute()) {
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

$ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting ORDER BY nama_ranting");
$tingkatan_result = $conn->query("SELECT id, nama_tingkat FROM tingkatan ORDER BY urutan");
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
            font-family: 'Segoe UI', sans-serif;
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
            margin-bottom: 30px;
            font-size: 26px;
        }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="date"],
        input[type="file"],
        select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
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
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
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
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
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
    </style>
</head>
<body>
    <?php renderNavbar('✏️ Edit Anggota'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Edit Data Anggota</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Foto Section -->
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
                
                <!-- Data Pribadi -->
                <h3>📋 Data Pribadi</h3>
                
                <div class="form-group">
                    <label>No Anggota (tidak bisa diubah)</label>
                    <input type="text" value="<?php echo $anggota['no_anggota']; ?>" disabled>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($anggota['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" value="<?php echo htmlspecialchars($anggota['tempat_lahir']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" value="<?php echo $anggota['tanggal_lahir']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jenis Kelamin <span class="required">*</span></label>
                        <select name="jenis_kelamin" required>
                            <option value="L" <?php echo $anggota['jenis_kelamin'] == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="P" <?php echo $anggota['jenis_kelamin'] == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                </div>
                
                <hr>
                
                <!-- Data Organisasi -->
                <h3>🏢 Data Organisasi</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Unit/Ranting Saat Ini <span class="required">*</span></label>
                        <select name="ranting_saat_ini_id" required>
                            <option value="">-- Pilih --</option>
                            <?php while ($row = $ranting_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $anggota['ranting_saat_ini_id'] == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nama_ranting']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tingkat <span class="required">*</span></label>
                        <select name="tingkat_id" required>
                            <option value="">-- Pilih --</option>
                            <?php while ($row = $tingkatan_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $anggota['tingkat_id'] == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nama_tingkat']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Anggota <span class="required">*</span></label>
                        <select name="jenis_anggota" required>
                            <option value="murid" <?php echo $anggota['jenis_anggota'] == 'murid' ? 'selected' : ''; ?>>Murid</option>
                            <option value="pelatih" <?php echo $anggota['jenis_anggota'] == 'pelatih' ? 'selected' : ''; ?>>Pelatih</option>
                            <option value="pelatih_unit" <?php echo $anggota['jenis_anggota'] == 'pelatih_unit' ? 'selected' : ''; ?>>Pelatih Unit</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>UKT Terakhir</label>
                    <input type="text" name="ukt_terakhir" 
                        value="<?php echo isset($anggota) && !empty($anggota['ukt_terakhir']) ? date('d/m/Y', strtotime($anggota['ukt_terakhir'])) : ''; ?>"
                        placeholder="Format: dd/mm/yyyy atau yyyy">
                    <div class="form-hint">
                        ℹ️ Format: 
                        <br>• Tanggal lengkap: 15/07/2024 atau 2024-07-15
                        <br>• Tahun saja: 2024 (otomatis dikonversi ke 02/07/2024)
                        <br>• Kosongkan jika UKT belum pernah dilakukan
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="anggota_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>