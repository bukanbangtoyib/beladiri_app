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

$result = $conn->query("SELECT * FROM ranting WHERE id = $id");
if ($result->num_rows == 0) {
    die("Unit/Ranting tidak ditemukan!");
}
$ranting = $result->fetch_assoc();

// Get kota name for SK naming
$kota_result = $conn->query("SELECT nama FROM kota WHERE id = " . $ranting['kota_id']);
$kota = $kota_result ? $kota_result->fetch_assoc() : null;
$pengurus_name = $kota && isset($kota['nama']) ? $kota['nama'] : 'unknown';

// Helper function untuk sanitasi nama
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "_", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
}

// Function untuk mendapatkan nomor revisi berikutnya
function get_next_revision_number($upload_dir, $ranting_name, $kota_name) {
    $ranting_clean = sanitize_name($ranting_name);
    $kota_clean = sanitize_name($kota_name);
    $pattern = 'SK-' . $ranting_clean . '-' . $kota_clean . '-';
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
    $kota_id = $_POST['kota_id'];
    
    // Get kota name yang baru (jika berubah)
    $kota_check = $conn->query("SELECT nama FROM kota WHERE id = " . (int)$kota_id);
    $kota_data = $kota_check ? $kota_check->fetch_assoc() : null;
    $kota_name = $kota_data && isset($kota_data['nama']) ? $kota_data['nama'] : 'unknown';
    
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
            $next_revision = get_next_revision_number($upload_dir, $nama_ranting, $kota_name);
            
            // Format: SK-nama_ranting-nama_kota-XX.pdf
            $ranting_clean = sanitize_name($nama_ranting);
            $kota_clean = sanitize_name($kota_name);
            $file_name = 'SK-' . $ranting_clean . '-' . $kota_clean . '-' . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . '.pdf';
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
                nama_ranting = ?, jenis = ?, tanggal_sk_pembentukan = ?, no_sk_pembentukan = ?,
                alamat = ?, ketua_nama = ?, penanggung_jawab_teknik = ?,
                no_kontak = ?, kota_id = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssii", $nama_ranting, $jenis, $tanggal_sk, $no_sk_pembentukan,
                        $alamat, $ketua_nama, $penanggung_jawab,
                        $no_kontak, $kota_id, $id);
        
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

// Get negara list
$negara_result = $conn->query("SELECT id, nama, kode FROM negara ORDER BY nama");

// Get all provinces 
$pengurus_provinsi_result = $conn->query("
    SELECT p.id, p.nama, p.negara_id, p.kode 
    FROM provinsi p 
    ORDER BY p.nama
");

// Get current ranting's related data
$current_kota_id = $ranting['kota_id'] ?? 0;
$current_provinsi_id = 0;
$current_negara_id = 0;

if ($current_kota_id) {
    $kota_info = $conn->query("SELECT provinsi_id FROM kota WHERE id = $current_kota_id")->fetch_assoc();
    if ($kota_info && isset($kota_info['provinsi_id'])) {
        $current_provinsi_id = $kota_info['provinsi_id'];
        $provinsi_info = $conn->query("SELECT negara_id FROM provinsi WHERE id = $current_provinsi_id")->fetch_assoc();
        if ($provinsi_info && isset($provinsi_info['negara_id'])) {
            $current_negara_id = $provinsi_info['negara_id'];
        }
    }
}

// Get current kota data for pre-selection
$current_kota_result = $conn->query("
    SELECT pk.id, pk.nama as kota_kode, 
           prov.id as prov_id, prov.nama as prov_nama, prov.kode as prov_kode, prov.negara_id,
           n.id as negara_id, n.nama as negara_nama, n.kode as negara_kode
    FROM kota pk 
    LEFT JOIN provinsi prov ON pk.provinsi_id = prov.id
    LEFT JOIN negara n ON prov.negara_id = n.id
    WHERE pk.id = $current_kota_id
");
$current_data = $current_kota_result->fetch_assoc();

// Get cities for current province for pre-selection
$kota_list = [];
if ($current_provinsi_id) {
    $kota_result = $conn->query("
        SELECT id, nama, kode 
        FROM kota 
        WHERE provinsi_id = $current_provinsi_id
        ORDER BY nama
    ");
    while ($row = $kota_result->fetch_assoc()) {
        $kota_list[] = $row;
    }
}

$kota_result = $conn->query("SELECT id, nama FROM kota ORDER BY nama");
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
        
        select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php renderNavbar('✏️ Edit Unit/Ranting'); ?>
    
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
                        <label>Negara <span class="required">*</span></label>
                        <select name="id_negara" id="id_negara" onchange="updateProvinsi()" required>
                            <option value="">-- Pilih Negara --</option>
                            <?php $negara_result = $conn->query("SELECT id, nama, kode FROM negara ORDER BY nama"); ?>
                            <?php while ($negara = $negara_result->fetch_assoc()): ?>
                                <option value="<?php echo $negara['id']; ?>" data-kode="<?php echo $negara['kode']; ?>" <?php echo ($current_data && $current_data['negara_id'] == $negara['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($negara['nama']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Negara</label>
                        <input type="text" id="kode_negara_display" value="<?php echo $current_data ? htmlspecialchars($current_data['negara_kode']) : ''; ?>" readonly placeholder="-">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Pengurus Provinsi <span class="required">*</span></label>
                        <select name="pengurus_provinsi_id" id="pengurus_provinsi_id" onchange="updatePengKot()" required>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php $pengurus_provinsi_result = $conn->query("SELECT id, nama, kode, negara_id FROM provinsi ORDER BY nama"); ?>
                            <?php while ($prov = $pengurus_provinsi_result->fetch_assoc()): ?>
                                <option value="<?php echo $prov['id']; ?>" data-id_negara="<?php echo $prov['negara_id']; ?>" data-kode="<?php echo $prov['kode']; ?>" <?php echo ($current_data && $current_data['prov_id'] == $prov['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($prov['nama']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode</label>
                        <input type="text" id="kode_provinsi_display" value="<?php echo $current_data ? htmlspecialchars($current_data['prov_kode']) : ''; ?>" readonly placeholder="-">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Pengurus Kota <span class="required">*</span></label>
                        <select name="kota_id" id="kota_id" required>
                            <option value="">-- Pilih Kota --</option>
                            <?php if ($current_data): ?>
                                <?php foreach ($kota_list as $kota): ?>
                                    <option value="<?php echo $kota['id']; ?>" data-kode="<?php echo $kota['kode']; ?>" <?php echo ($current_data && $current_data['id'] == $kota['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($kota['nama']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode</label>
                        <input type="text" id="kode_kota_display" value="<?php echo $current_data ? htmlspecialchars($current_data['kota_kode']) : ''; ?>" readonly placeholder="-">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>No Kontak <span class="required">*</span></label>
                        <input type="tel" name="no_kontak" value="<?php echo htmlspecialchars($ranting['no_kontak'] ?? ''); ?>" required>
                    </div>
                </div>                
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Alamat <span class="required">*</span></label>
                        <textarea name="alamat" required><?php echo htmlspecialchars($ranting['alamat']); ?></textarea>
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
                        <label>Tanggal SK Pembentukan <span class="required">*</span></label>
                        <input type="date" name="tanggal_sk" value="<?php echo $ranting['tanggal_sk_pembentukan']; ?>" required>
                    </div>                            
                
                    <div class="form-group">
                        <label>No SK Pembentukan</label>
                        <input type="text" name="no_sk_pembentukan" 
                            value="<?php echo htmlspecialchars($ranting['no_sk_pembentukan'] ?? ''); ?>"
                            placeholder="Contoh: 001/SK/KOTA/2024">
                        <div class="form-hint">Nomor Surat Keputusan pembentukan unit/ranting</div>
                    </div>
                </div>

                <hr>
                
                <h3>📄 SK Pembentukan</h3>
                
                <div class="info-box">
                    <strong>ℹ️ Format Nama File SK:</strong><br>
                    <span class="code">SK-{nama_ranting}-{nama_kota}-XX.pdf</span><br><br>
                    Contoh: <span class="code">SK-SMP_1-Surabaya-01.pdf</span><br><br>
                    Setiap upload file baru akan otomatis menambah nomor revisi (01 → 02 → 03, dst).
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Upload SK Pembentukan (PDF)</label>
                        <input type="file" name="sk_pembentukan" accept=".pdf">
                        <div class="form-hint">Format: PDF | Ukuran maksimal: 5MB | Kosongkan jika tidak ingin mengubah SK</div>
                    </div>
                </div>
                
                                                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="ranting_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Initialize on page load - filter provinces based on selected negara
        document.addEventListener('DOMContentLoaded', function() {
            const negaraSelect = document.getElementById('id_negara');
            const negaraId = negaraSelect.value;
            
            if (negaraId) {
                // Filter provinces based on selected negara
                const provinsiSelect = document.getElementById('pengurus_provinsi_id');
                Array.from(provinsiSelect.options).forEach(option => {
                    if (option.value === '') {
                        option.style.display = 'block';
                    } else if (option.dataset.id_negara === negaraId) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            } else {
                // Hide all provinces if no negara selected
                const provinsiSelect = document.getElementById('pengurus_provinsi_id');
                Array.from(provinsiSelect.options).forEach(option => {
                    option.style.display = 'none';
                });
            }
        });
        
        // Fungsi untuk update dropdown Provinsi berdasarkan Negara
        function updateProvinsi() {
            const negaraSelect = document.getElementById('id_negara');
            const provinsiSelect = document.getElementById('pengurus_provinsi_id');
            const kotaSelect = document.getElementById('kota_id');
            const kodeNegaraDisplay = document.getElementById('kode_negara_display');
            
            const negaraId = negaraSelect.value;
            const negaraOption = negaraSelect.options[negaraSelect.selectedIndex];
            
            // Get kode from data-kode attribute
            const kodeNegara = negaraOption.dataset.kode || '';
            kodeNegaraDisplay.value = kodeNegara;
            
            // Reset province and city dropdowns
            provinsiSelect.value = '';
            kotaSelect.value = '';
            kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
            document.getElementById('kode_provinsi_display').value = '';
            document.getElementById('kode_kota_display').value = '';
            
            if (negaraId === '') {
                // Show all provinces but disabled
                Array.from(provinsiSelect.options).forEach(option => {
                    option.style.display = 'none';
                });
                return;
            }
            
            // Show only provinces matching the selected country
            Array.from(provinsiSelect.options).forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                } else if (option.dataset.id_negara === negaraId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Select first matching province if available
            const firstMatching = Array.from(provinsiSelect.options).find(opt => opt.dataset.id_negara === negaraId);
            if (firstMatching) {
                provinsiSelect.value = firstMatching.value;
                updatePengKot();
            }
        }
        
        // Fungsi untuk update dropdown Kota dan tampilkan kode
        function updatePengKot() {
            const pengprovSelect = document.getElementById('pengurus_provinsi_id');
            const pengkotSelect = document.getElementById('kota_id');
            
            const pengprovId = pengprovSelect.value;
            const pengprovOption = pengprovSelect.options[pengprovSelect.selectedIndex];
            
            // Show province kode
            document.getElementById('kode_provinsi_display').value = '';
            
            // Reset city dropdown
            pengkotSelect.value = '';
            pengkotSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
            document.getElementById('kode_kota_display').value = '';
            
            if (pengprovId === '') {
                return;
            }
            
            // Get province kode from option data-kode
            const provKode = pengprovOption.dataset.kode || '';
            document.getElementById('kode_provinsi_display').value = provKode;
            
            // Fetch pengkot via AJAX
            fetch('../../api/get_kota.php?provinsi_id=' + pengprovId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Pilih Kota --</option>';
                        data.data.forEach(pengkot => {
                            const kode = pengkot.kode || '';
                            html += '<option value="' + pengkot.id + '" data-kode="' + kode + '">' + pengkot.nama_pengurus + '</option>';
                        });
                        pengkotSelect.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal memuat data Pengurus Kota');
                });
        }
        
        // Add event listener to kota dropdown to show kode
        document.getElementById('kota_id').addEventListener('change', function() {
            const kotaOption = this.options[this.selectedIndex];
            const kotaKode = kotaOption.dataset.kode || '';
            document.getElementById('kode_kota_display').value = kotaKode;
        });
    </script>
</body>
</html>