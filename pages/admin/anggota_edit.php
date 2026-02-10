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

// Helper function untuk sanitasi nama [LAMA - TETAP SAMA]
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_anggota = $conn->real_escape_string($_POST['no_anggota']); // [BARU - SEBELUMNYA DISABLED]
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $tempat_lahir = $conn->real_escape_string($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $ranting_saat_ini_id = $_POST['ranting_saat_ini_id'] ?: NULL;
    $tingkat_id = $_POST['tingkat_id'] ?: NULL;
    $jenis_anggota = $_POST['jenis_anggota'];
    $tahun_bergabung = !empty($_POST['tahun_bergabung']) ? (int)$_POST['tahun_bergabung'] : NULL; // [BARU]
    $no_handphone = $conn->real_escape_string($_POST['no_handphone'] ?? ''); // [BARU]
    $ukt_terakhir = $_POST['ukt_terakhir'] ?? '';
    
    // Validasi no_anggota jika berubah [BARU]
    if ($no_anggota != $anggota['no_anggota']) {
        $check = $conn->prepare("SELECT id FROM anggota WHERE no_anggota = ? AND id != ?");
        $check->bind_param("si", $no_anggota, $id);
        $check->execute();
        if ($check->num_rows > 0) {
            $error = "No Anggota sudah digunakan anggota lain!";
        }
    }
    
    // Handle foto upload / penggantian [LAMA - TETAP SAMA]
    $foto_path = $anggota['nama_foto']; // default: foto lama
    
    if (isset($_FILES['foto']) && $_FILES['foto']['size'] > 0) {
        $file = $_FILES['foto'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi
        $allowed_mimes = ['image/jpeg', 'image/png'];
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
        if (!in_array($mime, $allowed_mimes)) {
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
            $file_name = $no_anggota . '_' . $nama_clean . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $foto_path = $file_name;
            } else {
                $error = "Gagal upload foto!";
            }
        }
    }
    
    if (!$error) {
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        $sql = "UPDATE anggota SET 
                no_anggota = ?,
                nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, 
                jenis_kelamin = ?, ranting_saat_ini_id = ?, tingkat_id = ?, 
                jenis_anggota = ?, tahun_bergabung = ?, no_handphone = ?,
                ukt_terakhir = ?, nama_foto = ?, is_active = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Total 14 parameter
            $stmt->bind_param(
                "sssssisisissii",
                $no_anggota,
                $nama_lengkap, 
                $tempat_lahir, 
                $tanggal_lahir, 
                $jenis_kelamin,
                $ranting_saat_ini_id, 
                $tingkat_id, 
                $jenis_anggota,
                $tahun_bergabung,
                $no_handphone,
                $ukt_terakhir,
                $foto_path,
                $is_active,
                $id
            );
            
            if ($stmt->execute()) {
                // Update prestasi [BARU]
                if (!empty($_POST['prestasi_id'])) {
                    for ($i = 0; $i < count($_POST['prestasi_id']); $i++) {
                        if ($_POST['prestasi_id'][$i] != '') {
                            $pid = (int)$_POST['prestasi_id'][$i];
                            $event = $conn->real_escape_string($_POST['prestasi_event_name'][$i] ?? '');
                            $tgl = $_POST['prestasi_tanggal'][$i] ?? NULL;
                            $penyelenggara = $conn->real_escape_string($_POST['prestasi_penyelenggara'][$i] ?? '');
                            $kategori = $conn->real_escape_string($_POST['prestasi_kategori'][$i] ?? '');
                            $prestasi = $conn->real_escape_string($_POST['prestasi_prestasi_name'][$i] ?? '');
                            
                            if ($event) {
                                $conn->query("UPDATE prestasi SET 
                                            event_name = '$event',
                                            tanggal_pelaksanaan = '$tgl',
                                            penyelenggara = '$penyelenggara',
                                            kategori = '$kategori',
                                            prestasi = '$prestasi'
                                            WHERE id = $pid AND anggota_id = $id");
                            }
                        }
                    }
                }
                
                // Insert prestasi baru [BARU]
                if (!empty($_POST['new_prestasi_event'])) {
                    for ($i = 0; $i < count($_POST['new_prestasi_event']); $i++) {
                        if (!empty($_POST['new_prestasi_event'][$i])) {
                            $event = $conn->real_escape_string($_POST['new_prestasi_event'][$i]);
                            $tgl = $_POST['new_prestasi_tanggal'][$i] ?? NULL;
                            $penyelenggara = $conn->real_escape_string($_POST['new_prestasi_penyelenggara'][$i] ?? '');
                            $kategori = $conn->real_escape_string($_POST['new_prestasi_kategori'][$i] ?? '');
                            $prestasi = $conn->real_escape_string($_POST['new_prestasi_prestasi'][$i] ?? '');
                            
                            $conn->query("INSERT INTO prestasi (anggota_id, event_name, tanggal_pelaksanaan, penyelenggara, kategori, prestasi) 
                                        VALUES ($id, '$event', '$tgl', '$penyelenggara', '$kategori', '$prestasi')");
                        }
                    }
                }
                
                // Delete prestasi [BARU]
                if (!empty($_POST['delete_prestasi_ids'])) {
                    $ids = array_map('intval', explode(',', $_POST['delete_prestasi_ids']));
                    foreach ($ids as $pid) {
                        $conn->query("DELETE FROM prestasi WHERE id = $pid AND anggota_id = $id");
                    }
                }
                
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

// Ambil prestasi untuk ditampilkan [BARU]
$prestasi_result = $conn->query("SELECT * FROM prestasi WHERE anggota_id = $id ORDER BY tanggal_pelaksanaan DESC");
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
        input[type="number"],
        input[type="tel"],
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
        
        /* Prestasi Section [BARU] */
        .prestasi-item {
            background: #f8f9fa;
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
        
        .prestasi-item.marked-delete {
            opacity: 0.5;
            background: #fff5f5;
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
            
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <!-- Foto Section [LAMA - TETAP SAMA] -->
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
                    <label>No Anggota <span class="required">*</span> (DAPAT DIEDIT)</label>
                    <input type="text" name="no_anggota" value="<?php echo $anggota['no_anggota']; ?>" required>
                    <div class="form-hint">Dapat diubah jika diperlukan (harus unik)</div>
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

                <div class="form-row">
                    <div class="form-group">
                        <label>No. Handphone</label>
                        <input type="tel" name="no_handphone" value="<?php echo htmlspecialchars($anggota['no_handphone'] ?? ''); ?>" placeholder="Contoh: 08xxxxxxxxxx">
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
                            <?php $ranting_result->data_seek(0); while ($row = $ranting_result->fetch_assoc()): ?>
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
                            <?php $tingkatan_result->data_seek(0); while ($row = $tingkatan_result->fetch_assoc()): ?>
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
                    
                    <div class="form-group">
                        <label>Status Keanggotaan</label>
                        <select name="is_active">
                            <option value="1" <?php echo (isset($anggota['is_active']) && $anggota['is_active'] == 1) ? 'selected' : ''; ?>>✓ Aktif</option>
                            <option value="0" <?php echo (isset($anggota['is_active']) && $anggota['is_active'] == 0) ? 'selected' : (!isset($anggota['is_active']) ? 'selected' : ''); ?>>✗ Tidak Aktif</option>
                        </select>
                        <div class="form-hint">Aktif = anggota bisa login & terlihat di sistem</div>
                    </div>
                </div>
                
                <div class="form-row">                    
                    <div class="form-group">
                        <label>UKT Terakhir</label>
                        <input type="text" name="ukt_terakhir" 
                            value="<?php echo isset($anggota) && !empty($anggota['ukt_terakhir']) ? date('d/m/Y', strtotime($anggota['ukt_terakhir'])) : ''; ?>"
                            placeholder="Format: dd/mm/yyyy atau yyyy">
                        <div class="form-hint">Format: 15/07/2024 atau 2024</div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Prestasi Section [BARU] -->
                <h3>🏆 Prestasi yang Diraih</h3>
                
                <p class="form-hint" style="margin-bottom: 20px;">Kelola prestasi yang diraih anggota ini.</p>
                
                <div id="prestasiList">
                    <?php 
                    if ($prestasi_result && $prestasi_result->num_rows > 0):
                        while ($p = $prestasi_result->fetch_assoc()):
                    ?>
                    <div class="prestasi-item" data-prestasi-id="<?php echo $p['id']; ?>">
                        <input type="hidden" name="prestasi_id[]" value="<?php echo $p['id']; ?>">
                        
                        <div class="form-row" style="margin-bottom: 10px;">
                            <div class="form-group">
                                <label>Nama Event</label>
                                <input type="text" name="prestasi_event_name[]" value="<?php echo htmlspecialchars($p['event_name']); ?>" placeholder="Contoh: Kejuaraan Nasional">
                            </div>
                        </div>
                        
                        <div class="form-row" style="margin-bottom: 10px;">
                            <div class="form-group">
                                <label>Tanggal Pelaksanaan</label>
                                <input type="date" name="prestasi_tanggal[]" value="<?php echo $p['tanggal_pelaksanaan'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Penyelenggara</label>
                                <input type="text" name="prestasi_penyelenggara[]" value="<?php echo htmlspecialchars($p['penyelenggara'] ?? ''); ?>" placeholder="Contoh: KONI">
                            </div>
                        </div>
                        
                        <div class="form-row" style="margin-bottom: 10px;">
                            <div class="form-group">
                                <label>Kategori</label>
                                <input type="text" name="prestasi_kategori[]" value="<?php echo htmlspecialchars($p['kategori'] ?? ''); ?>" placeholder="Contoh: Putra -60kg">
                            </div>
                            
                            <div class="form-group">
                                <label>Prestasi</label>
                                <input type="text" name="prestasi_prestasi_name[]" value="<?php echo htmlspecialchars($p['prestasi'] ?? ''); ?>" placeholder="Contoh: Juara 1">
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-remove-prestasi" onclick="markPrestasi(this)">🗑️ Tandai Hapus</button>
                    </div>
                    <?php 
                        endwhile;
                    endif;
                    ?>
                </div>
                
                <!-- Template Prestasi Baru -->
                <div class="prestasi-item template" id="prestasiTemplate">
                    <div class="form-row" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Nama Event</label>
                            <input type="text" name="new_prestasi_event[]" placeholder="Contoh: Kejuaraan Nasional">
                        </div>
                    </div>
                    
                    <div class="form-row" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Tanggal Pelaksanaan</label>
                            <input type="date" name="new_prestasi_tanggal[]">
                        </div>
                        
                        <div class="form-group">
                            <label>Penyelenggara</label>
                            <input type="text" name="new_prestasi_penyelenggara[]" placeholder="Contoh: KONI">
                        </div>
                    </div>
                    
                    <div class="form-row" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Kategori</label>
                            <input type="text" name="new_prestasi_kategori[]" placeholder="Contoh: Putra -60kg">
                        </div>
                        
                        <div class="form-group">
                            <label>Prestasi</label>
                            <input type="text" name="new_prestasi_prestasi[]" placeholder="Contoh: Juara 1">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-remove-prestasi" onclick="this.parentElement.remove()">🗑️ Hapus</button>
                </div>
                
                <button type="button" class="btn btn-add-prestasi" onclick="addPrestasi()">+ Tambah Prestasi Baru</button>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="anggota_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
                
                <input type="hidden" id="deletePrestasiIds" name="delete_prestasi_ids" value="">
            </form>
        </div>
    </div>
    
    <script>
        let deletePrestasiIds = [];
        
        function markPrestasi(btn) {
            const item = btn.parentElement;
            const prestasiId = item.getAttribute('data-prestasi-id');
            
            if (item.classList.contains('marked-delete')) {
                // Batalkan hapus
                item.classList.remove('marked-delete');
                deletePrestasiIds = deletePrestasiIds.filter(x => x != prestasiId);
            } else {
                // Tandai untuk hapus
                item.classList.add('marked-delete');
                deletePrestasiIds.push(prestasiId);
            }
            
            document.getElementById('deletePrestasiIds').value = deletePrestasiIds.join(',');
        }
        
        function addPrestasi() {
            const template = document.getElementById('prestasiTemplate').cloneNode(true);
            template.classList.remove('template');
            template.removeAttribute('id');
            document.getElementById('prestasiList').appendChild(template);
        }
        
        document.getElementById('editForm').addEventListener('submit', function() {
            document.getElementById('deletePrestasiIds').value = deletePrestasiIds.join(',');
        });
    </script>
</body>
</html>