<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

if ($_SESSION['role'] != 'admin') {
    die("Akses ditolak!");
}

include '../../config/database.php';

$error = '';
$success = '';

// Helper function untuk sanitasi nama
function sanitize_name($name) {
    $name = preg_replace("/[^a-z0-9 -]/i", "", $name);
    $name = str_replace(" ", "_", $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_anggota = $conn->real_escape_string($_POST['no_anggota']);
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $tempat_lahir = $conn->real_escape_string($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $ranting_awal_id = !empty($_POST['ranting_awal_id']) ? (int)$_POST['ranting_awal_id'] : NULL;
    $ranting_saat_ini_id = !empty($_POST['ranting_saat_ini_id']) ? (int)$_POST['ranting_saat_ini_id'] : NULL;
    $tingkat_id = !empty($_POST['tingkat_id']) ? (int)$_POST['tingkat_id'] : NULL;
    $jenis_anggota = $_POST['jenis_anggota'];
    
    // Handle foto upload - SIMPAN KE FOLDER DENGAN FORMAT NoAnggota_Nama.ext
    $foto_path = NULL;
    
    if (isset($_FILES['foto']) && $_FILES['foto']['size'] > 0) {
        $file = $_FILES['foto'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi
        if (!in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $error = "Format foto harus JPG atau PNG!";
        } elseif ($file['size'] > 5242880) { // 5MB
            $error = "Ukuran foto maksimal 5MB!";
        } else {
            // Buat folder jika belum ada
            $upload_dir = '../../uploads/foto_anggota/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Format nama file: NoAnggota_NamaLengkap.ext
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
        // Cek no anggota sudah ada
        $check = $conn->query("SELECT id FROM anggota WHERE no_anggota = '$no_anggota'");
        if ($check->num_rows > 0) {
            $error = "No Anggota sudah terdaftar!";
        } else {
            $sql = "INSERT INTO anggota (
                no_anggota, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin,
                ranting_awal_id, ranting_saat_ini_id, tingkat_id, jenis_anggota,
                nama_foto
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // Total 10 parameter: string(6) + integer(3) + string(1)
                $stmt->bind_param("sssssiiiis", 
                    $no_anggota,           // s
                    $nama_lengkap,         // s
                    $tempat_lahir,         // s
                    $tanggal_lahir,        // s
                    $jenis_kelamin,        // s
                    $ranting_awal_id,      // i
                    $ranting_saat_ini_id,  // i
                    $tingkat_id,           // i
                    $jenis_anggota,        // s
                    $foto_path             // s
                );
                
                if ($stmt->execute()) {
                    $success = "Anggota berhasil ditambahkan!";
                    header("refresh:2;url=anggota.php");
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error prepare: " . $conn->error;
            }
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
    <title>Tambah Anggota - Sistem Beladiri</title>
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
            margin-bottom: 10px;
            font-size: 26px;
        }
        
        .form-subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
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
            transition: all 0.3s;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .required {
            color: #dc3545;
            font-weight: 700;
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
            font-style: italic;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
        }
        
        .radio-group {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .radio-option label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 0;
        }
        
        .conditional-field {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 3px solid #667eea;
            border-radius: 4px;
        }
        
        .conditional-field.show {
            display: block;
        }
        
        .conditional-field label {
            color: #667eea;
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
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #eee;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h2>➕ Tambah Anggota Baru</h2>
        <a href="anggota.php">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h1>Formulir Pendaftaran Anggota Baru</h1>
            <p class="form-subtitle">Silahkan isi semua kolom yang bertanda bintang merah (*)</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Bagian 1: Data Pribadi -->
                <h3>📋 Data Pribadi</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>No Anggota <span class="required">*</span></label>
                        <input type="text" name="no_anggota" required placeholder="Contoh: AGT-2024-001">
                        <div class="form-hint">Format unik untuk setiap anggota</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" required placeholder="Masukkan nama lengkap">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" required placeholder="Contoh: Jakarta">
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Kelamin <span class="required">*</span></label>
                        <select name="jenis_kelamin" required>
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Foto Profil</label>
                        <input type="file" name="foto" accept="image/*">
                        <div class="form-hint">Format: JPG, PNG (Ukuran maksimal 5MB) | Nama file akan menjadi: NoAnggota_NamaLengkap.jpg</div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Bagian 2: Data Organisasi -->
                <h3>🏢 Data Organisasi</h3>
                
                <div class="form-group">
                    <label>Unit/Ranting Awal Masuk <span class="required">*</span></label>
                    
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="ranting_database" name="ranting_awal_pilihan" value="database" checked onchange="toggleRantingAwal()">
                            <label for="ranting_database">Pilih dari Database</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="ranting_manual" name="ranting_awal_pilihan" value="manual" onchange="toggleRantingAwal()">
                            <label for="ranting_manual">Input Manual</label>
                        </div>
                    </div>
                    
                    <div id="ranting_awal_select" class="form-group">
                        <select name="ranting_awal_id">
                            <option value="">-- Pilih Unit/Ranting --</option>
                            <?php 
                            $ranting_result->data_seek(0);
                            while ($row = $ranting_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_ranting']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">Pilih Unit/Ranting yang tersedia di database</div>
                    </div>
                    
                    <div id="ranting_awal_manual" class="conditional-field">
                        <input type="text" name="ranting_awal_manual" placeholder="Masukkan nama Unit/Ranting">
                        <div class="form-hint">Masukkan nama Unit/Ranting secara manual</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Unit/Ranting Saat Ini <span class="required">*</span></label>
                        <select name="ranting_saat_ini_id" required>
                            <option value="">-- Pilih Unit/Ranting Saat Ini --</option>
                            <?php 
                            $ranting_result->data_seek(0);
                            while ($row = $ranting_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_ranting']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">Unit/Ranting dimana anggota saat ini berlatih</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tingkat <span class="required">*</span></label>
                        <select name="tingkat_id" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <?php while ($row = $tingkatan_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['nama_tingkat']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">Pilih dari 13 tingkatan resmi</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Anggota <span class="required">*</span></label>
                        <select name="jenis_anggota" required>
                            <option value="">-- Pilih Jenis Anggota --</option>
                            <option value="murid">Murid</option>
                            <option value="pelatih">Pelatih</option>
                            <option value="pelatih_unit">Pelatih Unit/Ranting</option>
                        </select>
                        <div class="form-hint">Tentukan status anggota</div>
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
                    <button type="submit" class="btn btn-primary">💾 Simpan Data Anggota</button>
                    <a href="anggota.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleRantingAwal() {
            const databaseOption = document.getElementById('ranting_database');
            const selectField = document.getElementById('ranting_awal_select');
            const manualField = document.getElementById('ranting_awal_manual');
            
            if (databaseOption.checked) {
                selectField.style.display = 'block';
                manualField.classList.remove('show');
                document.querySelector('input[name="ranting_awal_manual"]').value = '';
            } else {
                selectField.style.display = 'none';
                manualField.classList.add('show');
                document.querySelector('select[name="ranting_awal_id"]').value = '';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
        const uktInput = document.querySelector('input[name="ukt_terakhir"]');
        
        if (uktInput) {
            uktInput.addEventListener('blur', function() {
                if (this.value.trim() === '') return; // Skip jika kosong
                
                const input = this.value.trim();
                let parsedDate = null;
                
                // Format dd/mm/yyyy
                if (/^\d{2}\/\d{2}\/\d{4}$/.test(input)) {
                    const parts = input.split('/');
                    const day = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10);
                    const year = parseInt(parts[2], 10);
                    
                    if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
                        parsedDate = input;
                    }
                }
                // Format yyyy (tahun saja)
                else if (/^\d{4}$/.test(input)) {
                    const year = input;
                    parsedDate = '02/' + '07/' + year;
                    this.value = parsedDate;
                }
                // Format yyyy-mm-dd
                else if (/^\d{4}-\d{2}-\d{2}$/.test(input)) {
                    const date = new Date(input);
                    parsedDate = String(date.getDate()).padStart(2, '0') + '/' + 
                                String(date.getMonth() + 1).padStart(2, '0') + '/' + 
                                date.getFullYear();
                    this.value = parsedDate;
                }
                else {
                    this.value = '';
                    alert('Format tidak valid! Gunakan: dd/mm/yyyy atau yyyy');
                }
            });
        }
    });
    </script>
</body>
</html>