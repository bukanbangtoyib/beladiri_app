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
$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'pusat';
if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

$error = '';
$success = '';

// Map jenis to table
$table_map = [
    'pusat' => ['table' => 'negara', 'label' => 'Negara', 'id_col' => 'id'],
    'provinsi' => ['table' => 'provinsi', 'label' => 'Provinsi', 'id_col' => 'id'],
    'kota' => ['table' => 'kota', 'label' => 'Kota/Kabupaten', 'id_col' => 'id']
];

$table_info = $table_map[$jenis];
$table_name = $table_info['table'];
$label_jenis = $table_info['label'];

// Get data from appropriate table based on jenis
$result = $conn->query("SELECT * FROM $table_name WHERE id = $id");
if ($result->num_rows == 0) {
    die("$label_jenis tidak ditemukan!");
}

$pengurus = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $kode = $conn->real_escape_string($_POST['kode']);
    $ketua_nama = $conn->real_escape_string($_POST['ketua_nama']);
    $sk_kepengurusan = $conn->real_escape_string($_POST['sk_kepengurusan']);
    $periode_mulai = $_POST['periode_mulai'];
    $periode_akhir = $_POST['periode_akhir'];
    $alamat = $conn->real_escape_string($_POST['alamat']);
    
    // Handle id_negara for provinces and id_provinsi for kota
    $id_negara = NULL;
    $id_provinsi = NULL;
    
    if ($jenis == 'provinsi' && isset($_POST['id_negara'])) {
        $id_negara = (int)$_POST['id_negara'];
    } elseif ($jenis == 'kota') {
        $id_provinsi = (int)$_POST['id_provinsi'];
        if (isset($_POST['id_negara'])) {
            $id_negara = (int)$_POST['id_negara'];
        }
    }
    
    // Build UPDATE query based on jenis
    if ($jenis == 'pusat') {
        $sql = "UPDATE $table_name SET 
                nama = ?, kode = ?, sk_kepengurusan = ?,
                periode_mulai = ?, periode_akhir = ?, alamat_sekretariat = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $nama, $kode, $sk_kepengurusan,
                         $periode_mulai, $periode_akhir, $alamat, $id);
    } elseif ($jenis == 'provinsi') {
        $sql = "UPDATE $table_name SET 
                nama = ?, kode = ?, id_negara = ?, sk_kepengurusan = ?,
                periode_mulai = ?, periode_akhir = ?, alamat_sekretariat = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisssi", $nama, $kode, $id_negara, $sk_kepengurusan,
                         $periode_mulai, $periode_akhir, $alamat, $id);
    } else { // kota
        $sql = "UPDATE $table_name SET 
                nama = ?, kode = ?, id_negara = ?, id_provinsi = ?, sk_kepengurusan = ?,
                periode_mulai = ?, periode_akhir = ?, alamat_sekretariat = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiisssi", $nama, $kode, $id_negara, $id_provinsi, $sk_kepengurusan,
                         $periode_mulai, $periode_akhir, $alamat, $id);
    }
    
    if ($stmt->execute()) {
        $success = "Data berhasil diupdate!";
        header("refresh:2;url=pengurus_list.php?jenis=$jenis");
    } else {
        $error = "Error: " . $stmt->error;
    }
}

// Ambil daftar untuk dropdown
$negara_list = [];
$negara_result = $conn->query("SELECT id, kode, nama FROM negara ORDER BY nama");
while ($row = $negara_result->fetch_assoc()) {
    $negara_list[] = $row;
}

// Ambil daftar provinsi (untuk dropdown)
$provinsi_list = [];
$provinsi_result = $conn->query("SELECT id, negara_id, nama, kode FROM provinsi ORDER BY nama");
while ($row = $provinsi_result->fetch_assoc()) {
    $provinsi_list[] = $row;
}

// Ambil provinsi induk (untuk dropdown pada kota)
$provinsi_induk = [];
if ($jenis == 'kota') {
    $result = $conn->query("SELECT id, nama FROM provinsi ORDER BY nama");
    while ($row = $result->fetch_assoc()) {
        $provinsi_induk[] = $row;
    }
}

$label_jenis_text = [
    'pusat' => 'Negara',
    'provinsi' => 'Provinsi',
    'kota' => 'Kota/Kabupaten'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo $label_jenis_text[$jenis]; ?> - Sistem Beladiri</title>
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
        
        input[type="text"], input[type="date"], select, textarea {
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
        
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; text-decoration: none; }
        
        .button-group { display: flex; gap: 15px; margin-top: 35px; }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
    </style>
</head>
<body>
    <?php renderNavbar('📋 Edit ' . $label_jenis_text[$jenis]); ?>    
    
    <div class="container">
        <div class="form-container">
            <h1>Edit Data <?php echo $label_jenis_text[$jenis]; ?></h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama <?php echo $label_jenis_text[$jenis]; ?> <span class="required">*</span></label>
                        <input type="text" name="nama" value="<?php echo htmlspecialchars($pengurus['nama']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode <span class="required">*</span></label>
                        <input type="text" name="kode" value="<?php echo htmlspecialchars($pengurus['kode']); ?>" required>
                    </div>
                </div>
                
                <?php if (count($negara_list) > 0 && $jenis == 'provinsi'): ?>
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Negara <span class="required">*</span></label>
                        <select name="id_negara" id="negara_select" onchange="updateNegaraKode()" required>
                            <option value="">-- Pilih Negara --</option>
                            <?php foreach ($negara_list as $n): ?>
                                <option value="<?php echo $n['id']; ?>" data-kode="<?php echo $n['kode']; ?>" <?php echo ($pengurus['negara_id'] ?? 0) == $n['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($n['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kode Negara</label>
                        <input type="text" id="kode_negara" value="<?php echo htmlspecialchars(($pengurus['negara_id'] ?? 0) ? $negara_list[array_search($pengurus['negara_id'], array_column($negara_list, 'id'))]['kode'] ?? '' : ''); ?>" readonly>
                    </div>
                </div>
                <?php endif; ?>
                
                <script>
                function updateNegaraKode() {
                    const select = document.getElementById('negara_select');
                    const option = select.options[select.selectedIndex];
                    const kodeInput = document.getElementById('kode_negara');
                    kodeInput.value = option.dataset.kode || '';
                }
                // Initialize on page load
                document.addEventListener('DOMContentLoaded', updateNegaraKode);
                </script>
                
                <?php if (count($provinsi_induk) > 0 && $jenis == 'kota'): ?>
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Negara <span class="required">*</span></label>
                        <select name="id_negara" id="negara_select_kota" onchange="updateProvinsiForKota()" required>
                            <option value="">-- Pilih Negara --</option>
                            <?php foreach ($negara_list as $n): ?>
                                <option value="<?php echo $n['id']; ?>" data-kode="<?php echo $n['kode']; ?>" <?php echo ($pengurus['negara_id'] ?? 0) == $n['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($n['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kode Negara</label>
                        <input type="text" id="kode_negara_kota" value="<?php echo htmlspecialchars(($pengurus['negara_id'] ?? 0) ? $negara_list[array_search($pengurus['negara_id'], array_column($negara_list, 'id'))]['kode'] ?? '' : ''); ?>" readonly>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Provinsi <span class="required">*</span></label>
                        <select name="id_provinsi" id="provinsi_select_kota" required>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsi_list as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-id_negara="<?php echo $p['negara_id']; ?>" data-kode="<?php echo $p['kode']; ?>" <?php echo ($pengurus['provinsi_id'] ?? 0) == $p['id'] ? 'selected' : ''; ?> style="<?php echo ($p['negara_id'] ?? 0) != ($pengurus['negara_id'] ?? 0) ? 'display:none;' : ''; ?>">
                                    <?php echo htmlspecialchars($p['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kode</label>
                        <input type="text" id="kode_provinsi_kota" value="<?php echo htmlspecialchars(($pengurus['provinsi_id'] ?? 0) ? $provinsi_list[array_search($pengurus['provinsi_id'], array_column($provinsi_list, 'id'))]['kode'] ?? '' : ''); ?>" readonly>
                    </div>
                </div>
                
                <script>
                function updateProvinsiForKota() {
                    const negaraSelect = document.getElementById('negara_select_kota');
                    const provinsiSelect = document.getElementById('provinsi_select_kota');
                    const kodeNegaraInput = document.getElementById('kode_negara_kota');
                    
                    const negaraId = negaraSelect.value;
                    const negaraOption = negaraSelect.options[negaraSelect.selectedIndex];
                    
                    // Update kode negara
                    kodeNegaraInput.value = negaraOption.dataset.kode || '';
                    
                    // Show/hide provinces based on selected negara
                    Array.from(provinsiSelect.options).forEach(option => {
                        if (option.value === '') {
                            option.style.display = 'block';
                        } else if (option.dataset.id_negara === negaraId) {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                    
                    // Reset province selection if not matching
                    const selectedProvinsi = provinsiSelect.options[provinsiSelect.selectedIndex];
                    if (selectedProvinsi && selectedProvinsi.value !== '' && selectedProvinsi.dataset.id_negara !== negaraId) {
                        provinsiSelect.value = '';
                        document.getElementById('kode_provinsi_kota').value = '';
                    }
                }
                
                // Province change handler
                document.getElementById('provinsi_select_kota').addEventListener('change', function() {
                    const option = this.options[this.selectedIndex];
                    document.getElementById('kode_provinsi_kota').value = option.dataset.kode || '';
                });
                
                // Initialize on page load
                document.addEventListener('DOMContentLoaded', updateProvinsiForKota);
                </script>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Ketua <span class="required">*</span></label>
                        <input type="text" name="ketua_nama" value="<?php echo htmlspecialchars($pengurus['ketua_nama'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>No SK Kepengurusan <span class="required">*</span></label>
                        <input type="text" name="sk_kepengurusan" value="<?php echo htmlspecialchars($pengurus['sk_kepengurusan'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Periode Mulai <span class="required">*</span></label>
                        <input type="date" name="periode_mulai" value="<?php echo $pengurus['periode_mulai'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Periode Akhir <span class="required">*</span></label>
                        <input type="date" name="periode_akhir" value="<?php echo $pengurus['periode_akhir'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Alamat Sekretariat <span class="required">*</span></label>
                        <textarea name="alamat" required><?php echo htmlspecialchars($pengurus['alamat_sekretariat'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="pengurus_list.php?jenis=<?php echo $jenis; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>