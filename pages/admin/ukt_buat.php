<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';

// Initialize permission manager
$permission_manager = new PermissionManager($conn, $_SESSION['user_id'], $_SESSION['role'], $_SESSION['pengurus_id'] ?? null, $_SESSION['ranting_id'] ?? null, $_SESSION['no_anggota'] ?? null);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
$can_create_info = $permission_manager->canCreateOwnUKT();
if (!$can_create_info['can']) {
    die("❌ Akses ditolak! Anda tidak memiliki izin untuk membuat UKT.");
}

// Get user's level and default values
$user_jenis_peny = $can_create_info['jenis'];
$user_peny_id = $can_create_info['peny_id'];

// Get organization's name for display
$peny_nama = '';
if ($user_peny_id && $user_jenis_peny) {
    if ($user_jenis_peny === 'pusat') {
        $result = $conn->query("SELECT nama FROM negara WHERE id = " . (int)$user_peny_id);
        $row = $result->fetch_assoc();
        $peny_nama = $row['nama'] ?? '';
    } elseif ($user_jenis_peny === 'provinsi') {
        $result = $conn->query("SELECT nama FROM provinsi WHERE id = " . (int)$user_peny_id);
        $row = $result->fetch_assoc();
        $peny_nama = $row['nama'] ?? '';
    } elseif ($user_jenis_peny === 'kota') {
        $result = $conn->query("SELECT nama FROM kota WHERE id = " . (int)$user_peny_id);
        $row = $result->fetch_assoc();
        $peny_nama = $row['nama'] ?? '';
    }
}

// Check if admin (can choose any level)
$is_admin = ($_SESSION['role'] === 'admin');

// Map role to display name
$role_jenis_map = [
    'negara' => 'pusat',
    'pengprov' => 'provinsi', 
    'pengkot' => 'kota'
];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal_pelaksanaan = $_POST['tanggal_pelaksanaan'];
    $lokasi = $conn->real_escape_string($_POST['lokasi']);
    $penyelenggara_id = !empty($_POST['penyelenggara_id']) ? (int)$_POST['penyelenggara_id'] : null;
    $jenis_penyelenggara = !empty($_POST['jenis_penyelenggara']) ? $conn->real_escape_string($_POST['jenis_penyelenggara']) : null;
    
    // Check specific level permission
    if (!$permission_manager->canManageUKT('ukt_create', $jenis_penyelenggara, $penyelenggara_id)) {
        $error = "Anda tidak memiliki izin untuk membuat UKT tingkat " . ucfirst($jenis_penyelenggara) . " untuk penyelenggara ini.";
    } else {
    
    $sql = "INSERT INTO ukt (tanggal_pelaksanaan, lokasi, penyelenggara_id, jenis_penyelenggara) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssis", $tanggal_pelaksanaan, $lokasi, $penyelenggara_id, $jenis_penyelenggara);
    
        if ($stmt->execute()) {
            $ukt_id = $stmt->insert_id;
            $success = "UKT berhasil dibuat! Sekarang tambahkan peserta.";
            header("refresh:2;url=ukt_tambah_peserta.php?id=$ukt_id");
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat UKT - Sistem Beladiri</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }

        .form-container {
            background: white;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 10px; }
        
        .form-group { margin-bottom: 25px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }        
        
        h3 {
            color: #333;
            margin: 30px 0 20px 0;
            font-size: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
        }
        
        h3:first-child {
            margin-top: 0;
        }
        
        input[type="date"], input[type="text"], select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 6px; }    
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .form-row.full { grid-template-columns: 1fr; }

        .form-row .form-group {
            margin-bottom: 0;
        }
        
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .button-group { display: flex; gap: 15px; margin-top: 35px; }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        
        .loading {
            display: none;
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }

        /* Select2 alignment */
        .select2-container--default .select2-selection--single {
            height: 41px;
            padding: 6px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
            padding-left: 0;
            font-size: 14px;
            color: #333;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #999;
        }
    </style>
</head>
<body>
    <?php renderNavbar('➕ Buat UKT Baru'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>📋 Formulir Pembuatan UKT Baru</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="formBuatUKT">
                <h3>📋 Informasi UKT</h3>

                <div class="form-group">
                    <label>Tanggal Pelaksanaan <span class="required">*</span></label>
                    <input type="date" name="tanggal_pelaksanaan" required>
                    <div class="form-hint">Tanggal kapan UKT akan dilaksanakan</div>
                </div>
                
                <div class="form-group">
                    <label>Lokasi Pelaksanaan <span class="required">*</span></label>
                    <input type="text" name="lokasi" required placeholder="Contoh: Gedung Olahraga Jakarta">
                    <div class="form-hint">Tempat dimana UKT akan diselenggarakan</div>
                </div>
                
                <div class="form-group">
                    <h3>🏛️ Penyelenggara</h3>
                    
                    <?php
                    // Determine which options to show based on role
                    $show_pusat = in_array($_SESSION['role'], ['admin', 'negara']);
                    $show_provinsi = in_array($_SESSION['role'], ['admin', 'negara', 'pengprov']);
                    $show_kota = in_array($_SESSION['role'], ['admin', 'negara', 'pengprov', 'pengkot']);
                    
                    // For non-admin, show as read-only
                    $is_readonly = !$is_admin;
                    ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jenis Penyelenggara</label>
                            <?php if ($is_readonly && $user_jenis_peny): ?>
                                <input type="hidden" name="jenis_penyelenggara" value="<?php echo htmlspecialchars($user_jenis_peny); ?>">
                                <input type="text" value="<?php 
                                    if ($user_jenis_peny === 'pusat') echo 'Pusat (PP)';
                                    elseif ($user_jenis_peny === 'provinsi') echo 'Provinsi';
                                    elseif ($user_jenis_peny === 'kota') echo 'Kota / Kabupaten';
                                ?>" readonly style="background:#e9ecef;">
                            <?php else: ?>
                            <select name="jenis_penyelenggara" id="jenisPenyelenggara" onchange="handleJenisPenyelenggaraChange()">
                                <option value="">-- Pilih Jenis Penyelenggara --</option>
                                <?php if ($show_pusat): ?>
                                <option value="pusat" <?php echo $user_jenis_peny === 'pusat' ? 'selected' : ''; ?>>Pusat (PP)</option>
                                <?php endif; ?>
                                <?php if ($show_provinsi): ?>
                                <option value="provinsi" <?php echo $user_jenis_peny === 'provinsi' ? 'selected' : ''; ?>>Provinsi (PengProv)</option>
                                <?php endif; ?>
                                <?php if ($show_kota): ?>
                                <option value="kota" <?php echo $user_jenis_peny === 'kota' ? 'selected' : ''; ?>>Kota / Kabupaten (PengKot)</option>
                                <?php endif; ?>
                            </select>
                            <?php endif; ?>
                            <div class="form-hint">Tingkat organisasi penyelenggara</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Penyelenggara</label>
                            <?php if ($is_readonly && $user_peny_id && $peny_nama): ?>
                                <input type="hidden" name="penyelenggara_id" value="<?php echo (int)$user_peny_id; ?>">
                                <input type="text" value="<?php echo htmlspecialchars($peny_nama); ?>" readonly style="background:#e9ecef;">
                            <?php else: ?>
                            <select name="penyelenggara_id" id="namaPenyelenggara" <?php echo $user_peny_id ? '' : 'disabled'; ?>>
                                <?php if ($user_peny_id): ?>
                                <option value="<?php echo $user_peny_id; ?>">-- Terpilih --</option>
                                <?php else: ?>
                                <option value="">-- Pilih Penyelenggara --</option>
                                <?php endif; ?>
                            </select>
                            <?php endif; ?>
                            <div class="form-hint">Organisasi yang menyelenggarakan UKT</div>
                            <div class="loading" id="loadingPenyelenggara">Memuat data...</div>
                        </div>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">✓ Buat UKT</button>
                    <a href="ukt.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Only initialize Select2 if form is NOT read-only
            const isReadonly = <?php echo $is_readonly ? 'true' : 'false'; ?>;
            
            if (!isReadonly) {
                $('#namaPenyelenggara').select2({
                    placeholder: "-- Pilih Penyelenggara --",
                    allowClear: true,
                    width: '100%'
                }).on('select2:open', function(e) {
                    // Focus the search field
                    const searchField = document.querySelector('.select2-search__field');
                    if (searchField) searchField.focus();
                });
            }
            
            // Auto-load options if user has a pre-selected level and form is NOT read-only
            const preSelectedJenis = '<?php echo $user_jenis_peny; ?>';
            const preSelectedId = '<?php echo $user_peny_id; ?>';
            
            if (!isReadonly && preSelectedJenis && preSelectedJenis !== 'all' && preSelectedId) {
                // Pre-selected - load the options and select the user's organisasi
                loadPenyelenggara(preSelectedJenis, preSelectedId);
            }
        });
        
        function loadPenyelenggara(jenisPenyelenggara, selectedId = null) {
            const namaPenyelenggaraSelect = document.getElementById('namaPenyelenggara');
            const loadingDiv = document.getElementById('loadingPenyelenggara');
            
            loadingDiv.style.display = 'block';
            
            fetch('../../api/get_penyelenggara.php?jenis_pengurus=' + encodeURIComponent(jenisPenyelenggara))
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    if (data.success && data.data.length > 0) {
                        namaPenyelenggaraSelect.innerHTML = '<option value="">-- Pilih Penyelenggara --</option>';
                        data.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.nama;
                            if (selectedId && item.id == selectedId) {
                                option.selected = true;
                            }
                            namaPenyelenggaraSelect.appendChild(option);
                        });
                        namaPenyelenggaraSelect.disabled = false;
                    } else {
                        namaPenyelenggaraSelect.innerHTML = '<option value="">-- Tidak ada data --</option>';
                    }
                    // Trigger Select2 update
                    $('#namaPenyelenggara').trigger('change');
                })
                .catch(error => { 
                    loadingDiv.style.display = 'none'; 
                    console.error('Error:', error);
                });
        }
        
        function handleJenisPenyelenggaraChange() {
            const jenisPenyelenggara = document.getElementById('jenisPenyelenggara').value;
            const namaPenyelenggaraSelect = document.getElementById('namaPenyelenggara');
            const loadingDiv = document.getElementById('loadingPenyelenggara');
            
            namaPenyelenggaraSelect.innerHTML = '<option value="">-- Pilih Penyelenggara --</option>';
            namaPenyelenggaraSelect.disabled = true;
            loadingDiv.style.display = 'none';
            
            if (!jenisPenyelenggara) return;
            
            loadPenyelenggara(jenisPenyelenggara);
        }
    </script>
</body>
</html>