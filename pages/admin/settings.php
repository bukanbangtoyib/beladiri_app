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

// Auto-update status based on periode_akhir (jika tanggal sekarang > periode_akhir, maka aktif = 0)
$today = date('Y-m-d');
$conn->query("UPDATE negara SET aktif = 0 WHERE periode_akhir IS NOT NULL AND periode_akhir != '' AND periode_akhir < '$today' AND aktif = 1");
$conn->query("UPDATE provinsi SET aktif = 0 WHERE periode_akhir IS NOT NULL AND periode_akhir != '' AND periode_akhir < '$today' AND aktif = 1");
$conn->query("UPDATE kota SET aktif = 0 WHERE periode_akhir IS NOT NULL AND periode_akhir != '' AND periode_akhir < '$today' AND aktif = 1");

$error = '';
$success = '';

// Load settings (dari file config atau bisa dari database)
$settings_file = '../../config/settings.php';

$default_settings = [
    'nama_organisasi' => 'Lembaga Beladiri',
    'alamat' => 'Jl. Contoh No. 123',
    'no_telp' => '(031) 123-4567',
    'email' => 'info@beladiri.com',
    'tahun_berdiri' => '2020'
];

$default_nomor_settings = [
    'kode_negara' => true,
    'kode_provinsi' => true,
    'kode_kota' => true,
    'kode_ranting' => true,
    'tahun_daftar' => true,
    'urutan_daftar' => true,
    'separator' => '.',
    'default_negara' => 1
];

// Load existing settings
if (file_exists($settings_file)) {
    include $settings_file;
    if (!isset($settings)) {
        $settings = $default_settings;
    }
    if (!isset($pengaturan_nomor)) {
        $pengaturan_nomor = $default_nomor_settings;
    }
} else {
    $settings = $default_settings;
    $pengaturan_nomor = $default_nomor_settings;
}

// Process update untuk organisasi
if (isset($_POST['save_org'])) {
    $settings['nama_organisasi'] = $_POST['nama_organisasi'];
    $settings['alamat'] = $_POST['alamat'];
    $settings['no_telp'] = $_POST['no_telp'];
    $settings['email'] = $_POST['email'];
    $settings['tahun_berdiri'] = $_POST['tahun_berdiri'];
    
    // Handle logo delete
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] == '1') {
        if (!empty($settings['logo']) && file_exists('../../' . $settings['logo'])) {
            unlink('../../' . $settings['logo']);
        }
        $settings['logo'] = '';
    }
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = '../../uploads/logo/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = 'logo_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                // Delete old logo if exists
                if (!empty($settings['logo']) && file_exists('../../' . $settings['logo'])) {
                    unlink('../../' . $settings['logo']);
                }
                $settings['logo'] = 'uploads/logo/' . $new_filename;
            }
        }
    }
    
    // Save to file
    $content = "<?php\n\$settings = " . var_export($settings, true) . ";\n";
    $content .= "\$pengaturan_nomor = " . var_export($pengaturan_nomor, true) . ";\n";
    $content .= "?>";
    
    if (file_put_contents($settings_file, $content)) {
        $success = "Pengaturan organisasi berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan pengaturan!";
    }
}

// Process update untuk nomor settings
if (isset($_POST['save_nomor'])) {
    $pengaturan_nomor['kode_negara'] = isset($_POST['kode_negara']);
    $pengaturan_nomor['kode_provinsi'] = isset($_POST['kode_provinsi']);
    $pengaturan_nomor['kode_kota'] = isset($_POST['kode_kota']);
    $pengaturan_nomor['kode_ranting'] = isset($_POST['kode_ranting']);
    $pengaturan_nomor['tahun_daftar'] = isset($_POST['tahun_daftar']);
    $pengaturan_nomor['urutan_daftar'] = isset($_POST['urutan_daftar']);
    $pengaturan_nomor['separator'] = $_POST['separator'] ?? '.';
    $pengaturan_nomor['default_negara'] = (int)($_POST['default_negara'] ?? 1);
    
    // Save to file
    $content = "<?php\n\$settings = " . var_export($settings, true) . ";\n";
    $content .= "\$pengaturan_nomor = " . var_export($pengaturan_nomor, true) . ";\n";
    $content .= "?>";
    
    if (file_put_contents($settings_file, $content)) {
        $success = "Pengaturan nomor anggota berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan pengaturan nomor!";
    }
}

// Get data untuk tingkatan
$tingkatan_result = $conn->query("SELECT * FROM tingkatan ORDER BY urutan ASC");
$tingkatan_list = [];
while ($row = $tingkatan_result->fetch_assoc()) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM anggota WHERE tingkat_id = " . $row['id'])->fetch_assoc();
    $row['jumlah_anggota'] = $count['cnt'];
    $tingkatan_list[] = $row;
}

// Get data untuk jenis_anggota
$jenis_result = $conn->query("SELECT * FROM jenis_anggota ORDER BY id ASC");
$jenis_list = [];
while ($row = $jenis_result->fetch_assoc()) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM anggota WHERE jenis_anggota = " . (int)$row['id'])->fetch_assoc();
    $row['jumlah_anggota'] = $count['cnt'];
    $jenis_list[] = $row;
}

// Get max urutan untuk tingkatan
$max_urutan = $conn->query("SELECT MAX(urutan) as max_urut FROM tingkatan")->fetch_assoc();
$next_urutan = ($max_urutan['max_urut'] ?? 0) + 1;

// Get data for numbering tables - using new table structure
$negara_list = [];
$negara_result = $conn->query("SELECT *, 1 as aktif FROM negara ORDER BY nama ASC");
if ($negara_result) {
    while ($row = $negara_result->fetch_assoc()) { $negara_list[] = $row; }
}

$provinsi_result = $conn->query("
    SELECT 
        p.id,
        p.negara_id,
        p.kode,
        p.nama as nama_pengurus,
        p.nama,
        1 as aktif,
        (SELECT nama FROM negara WHERE id = p.negara_id) as nama_negara
    FROM provinsi p 
    ORDER BY negara_id ASC, id ASC
");
$provinsi_list = [];
while ($row = $provinsi_result->fetch_assoc()) { $provinsi_list[] = $row; }

// Get kota list from kota table - with aktif and related data
$kota_result = $conn->query("
    SELECT 
        k.id,
        k.kode,
        k.nama,
        k.aktif,
        k.provinsi_id,
        p.nama as nama_provinsi,
        n.nama as nama_negara
    FROM kota k
    LEFT JOIN provinsi p ON k.provinsi_id = p.id
    LEFT JOIN negara n ON p.negara_id = n.id
    ORDER BY n.nama ASC, p.nama ASC, k.nama ASC
");
$kota_list = [];
while ($row = $kota_result->fetch_assoc()) { $kota_list[] = $row; }

// Get ranting list - from ranting table
$ranting_result = $conn->query("
    SELECT 
        r.id,
        r.kota_id as id_kota,
        r.nama_ranting as nama,
        r.nama_ranting,
        r.jenis,
        r.kode as kode,
        1 as aktif,
        k.nama as nama_kota,
        k.kode as kode_kota
    FROM ranting r 
    LEFT JOIN kota k ON r.kota_id = k.id
    ORDER BY k.nama ASC, r.nama_ranting ASC
");
$ranting_list = [];
while ($row = $ranting_result->fetch_assoc()) { $ranting_list[] = $row; }

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Sistem Beladiri</title>
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
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        h1 { color: #333; margin-bottom: 10px; }
        h2 { color: #555; margin-bottom: 20px; font-size: 1.3em; }
        h3 { color: #666; margin-bottom: 15px; font-size: 1.1em; }
        h4 { color: #777; margin-bottom: 10px; font-size: 1em; }
        .form-group { margin-bottom: 22px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"], input[type="email"], input[type="file"], textarea, select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input[type="file"] {
            padding: 8px 14px;
            background: #f9f9f9;
        }
        
        input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        
        .form-hint {
            margin-top: 5px;
            font-size: 12px;
            color: #888;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea { resize: vertical; min-height: 80px; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .button-group { margin-top: 20px; }
        
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
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 25px;
        }
        
        /* Table styles */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        
        /* Tabs */
        .tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #ddd; flex-wrap: wrap; }
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .tab:hover { color: #667eea; }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Sub-tabs */
        .sub-tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #eee; flex-wrap: wrap; }
        .sub-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .sub-tab:hover { color: #667eea; }
        .sub-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9ff;
        }
        .sub-tab-content { display: none; }
        .sub-tab-content.active { display: block; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-close {
            background: none; border: none; font-size: 24px; cursor: pointer;
        }
        
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        
        /* Checkbox grid for format config */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .checkbox-item:hover {
            background: #e9ecef;
        }
        .checkbox-item input {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        /* Preview box */
        .preview-box {
            background: #f0f7ff;
            border: 2px dashed #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        .preview-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            font-family: 'Courier New', monospace;
        }
        .preview-label {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
        }
        
        /* Kode management sub-tabs */
        .kode-sub-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }
        .kode-sub-tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .kode-sub-tab:hover {
            color: #667eea;
            background: #f8f9ff;
        }
        .kode-sub-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f0f4ff;
        }
        .kode-sub-tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .kode-sub-tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Card styles for kode management */
        .kode-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .kode-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kode-card-header h3 {
            margin: 0;
            color: white;
            font-size: 1.2em;
        }
        .kode-card-body {
            padding: 20px;
        }
        .kode-card-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }
        
        /* Compact table styles */
        .compact-table {
            width: 100%;
            border-collapse: collapse;
        }
        .compact-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }
        .compact-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .compact-table tr:hover {
            background: #f8f9ff;
        }
        
        /* Stats row */
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            flex: 1;
            text-align: center;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-card .label {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
    </style>
</head>
<body>
    <?php renderNavbar('⚙️ Pengaturan Sistem'); ?>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('organisasi', this)">📋 Organisasi</button>
            <button class="tab" onclick="showTab('tingkatan', this)">🏆 Tingkatan</button>
            <button class="tab" onclick="showTab('jenis', this)">👥 Jenis Anggota</button>
            <button class="tab" onclick="showTab('nomor', this)">🔢 Nomor Anggota</button>
        </div>
        
        <!-- Tab Organisasi -->
        <div id="tab-organisasi" class="tab-content active">
            <div class="form-container">
                <h1>Pengaturan Organisasi</h1>
                
                <div class="info-box">
                    <strong>ℹ️ Informasi:</strong> Edit data organisasi Anda di sini. Data ini akan muncul di laporan dan dokumen resmi.
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="save_org" value="1">
                    
                    <div class="form-group">
                        <label>Logo Organisasi</label>
                        <?php if (!empty($settings['logo'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="max-height: 80px; max-width: 200px; object-fit: contain;">
                                <div style="margin-top: 5px;">
                                    <label style="display: inline; font-weight: normal;">
                                        <input type="checkbox" name="delete_logo" value="1"> Hapus logo
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*">
                        <div class="form-hint">Format: JPG, PNG, GIF, WebP (Ukuran maksimal 2MB)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Organisasi / Lembaga</label>
                        <input type="text" name="nama_organisasi" value="<?php echo htmlspecialchars($settings['nama_organisasi']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Kantor Pusat</label>
                        <textarea name="alamat" required><?php echo htmlspecialchars($settings['alamat']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nomor Telepon</label>
                            <input type="text" name="no_telp" value="<?php echo htmlspecialchars($settings['no_telp']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tahun Berdiri</label>
                        <input type="text" name="tahun_berdiri" value="<?php echo htmlspecialchars($settings['tahun_berdiri']); ?>" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tab Tingkat -->
        <div id="tab-tingkatan" class="tab-content">
            <div class="form-container">
                <h2>🏆 Manajemen Tingkatan</h2>
                
                <div class="info-box">
                    <strong>ℹ️ Informasi:</strong> Kelola data tingkatan di sini. Tingkat yang sedang digunakan oleh anggota tidak dapat dihapus.
                </div>
                
                <div style="margin-bottom: 20px;">
                    <button class="btn btn-success" onclick="openModal('tingkatan')">+ Tambah Tingkat</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Tingkat</th>
                            <th>Urutan</th>
                            <th>Jumlah Anggota</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tingkatan_list as $i => $tingkat): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($tingkat['nama_tingkat']); ?></strong></td>
                            <td><?php echo $tingkat['urutan']; ?></td>
                            <td>
                                <?php if ($tingkat['jumlah_anggota'] > 0): ?>
                                    <span class="badge badge-warning"><?php echo $tingkat['jumlah_anggota']; ?> anggota</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editTingkat(<?php echo $tingkat['id']; ?>, '<?php echo htmlspecialchars($tingkat['nama_tingkat']); ?>', <?php echo $tingkat['urutan']; ?>)">✏️ Edit</button>
                                <?php if ($tingkat['jumlah_anggota'] == 0): ?>
                                    <button class="btn btn-danger btn-sm" onclick="deleteTingkat(<?php echo $tingkat['id']; ?>, '<?php echo htmlspecialchars($tingkat['nama_tingkat']); ?>')">🗑️ Hapus</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tab Jenis -->
        <div id="tab-jenis" class="tab-content">
            <div class="form-container">
                <h2>👥 Manajemen Jenis Anggota</h2>
                
                <div class="info-box">
                    <strong>ℹ️ Informasi:</strong> Kelola jenis anggota di sini. Jenis yang sedang digunakan oleh anggota tidak dapat dihapus.
                </div>
                
                <div style="margin-bottom: 20px;">
                    <button class="btn btn-success" onclick="openModal('jenis')">+ Tambah Jenis</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Jenis</th>
                            <th>Jumlah Anggota</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jenis_list as $i => $jenis): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($jenis['nama_jenis']); ?></strong></td>
                            <td>
                                <?php if ($jenis['jumlah_anggota'] > 0): ?>
                                    <span class="badge badge-warning"><?php echo $jenis['jumlah_anggota']; ?> anggota</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editJenis(<?php echo $jenis['id']; ?>, '<?php echo htmlspecialchars($jenis['nama_jenis']); ?>')">✏️ Edit</button>
                                <?php if ($jenis['jumlah_anggota'] == 0): ?>
                                    <button class="btn btn-danger btn-sm" onclick="deleteJenis(<?php echo $jenis['id']; ?>, '<?php echo htmlspecialchars($jenis['nama_jenis']); ?>')">🗑️ Hapus</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tab Nomor Anggota -->
        <div id="tab-nomor" class="tab-content">
            <div class="form-container">
                <h2>🔢 Pengaturan Nomor Anggota</h2>
                
                <!-- All Sub-tabs consolidated -->
                <div class="sub-tabs" style="margin-top: 25px;">
                    <button class="sub-tab active" onclick="showSubTab('format', this)">⚙️ Konfigurasi Format</button>
                    <button class="sub-tab" onclick="showKodeTab('negara', this)">🌍 Negara</button>
                    <button class="sub-tab" onclick="showKodeTab('provinsi', this)">🏙️ Provinsi</button>
                    <button class="sub-tab" onclick="showKodeTab('kota', this)">🏘️ Kota</button>
                    <button class="sub-tab" onclick="showKodeTab('ranting', this)">🏠 Ranting</button>
                </div>
                
                <div id="subtab-format" class="sub-tab-content active">
                    <h3>⚙️ Konfigurasi Format Nomor Anggota</h3>
                    
                    <div class="info-box">
                        <strong>ℹ️ Informasi:</strong> Konfigurasi format nomor anggota. Format default: NNPPPKKK.RRR-YYYYXXX<br>
                        <strong>NN</strong> = Kode Negara (2 digit), <strong>PPP</strong> = Kode Provinsi (3 digit), <strong>KKK</strong> = Kode Kota (3 digit), <strong>RRR</strong> = Kode Ranting (3 digit), <strong>YYYY</strong> = Tahun Daftar, <strong>XXX</strong> = Nomor Urut<br>
                        <strong>Catatan:</strong> Jika kode sebelum ranting tidak digunakan, titik tidak ditampilkan. Jika kode sebelum tahun tidak digunakan, titik & garis tidak ditampilkan.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="save_nomor" value="1">
                        
                        <h4>Pilih Komponen yang Aktif:</h4>
                        <div class="checkbox-grid">
                            <div class="checkbox-item">
                                <input type="checkbox" id="kode_negara" name="kode_negara" <?php echo ($pengaturan_nomor['kode_negara'] ?? true) ? 'checked' : ''; ?>>
                                <label for="kode_negara">Kode Negara (NN)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="kode_provinsi" name="kode_provinsi" <?php echo ($pengaturan_nomor['kode_provinsi'] ?? true) ? 'checked' : ''; ?>>
                                <label for="kode_provinsi">Kode Provinsi (PPP)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="kode_kota" name="kode_kota" <?php echo ($pengaturan_nomor['kode_kota'] ?? true) ? 'checked' : ''; ?>>
                                <label for="kode_kota">Kode Kota (KKK)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="kode_ranting" name="kode_ranting" <?php echo ($pengaturan_nomor['kode_ranting'] ?? true) ? 'checked' : ''; ?>>
                                <label for="kode_ranting">Kode Unit/Ranting (RRR)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="tahun_daftar" name="tahun_daftar" <?php echo ($pengaturan_nomor['tahun_daftar'] ?? true) ? 'checked' : ''; ?>>
                                <label for="tahun_daftar">Tahun Daftar (YYYY)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="urutan_daftar" name="urutan_daftar" <?php echo ($pengaturan_nomor['urutan_daftar'] ?? true) ? 'checked' : ''; ?>>
                                <label for="urutan_daftar">Nomor Urut (XXX)</label>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Negara Default</label>
                                <select name="default_negara">
                                    <?php foreach ($negara_list as $negara): ?>
                                        <option value="<?php echo $negara['id']; ?>" <?php echo ($pengaturan_nomor['default_negara'] ?? 1) == $negara['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($negara['kode'] . ' - ' . $negara['nama']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 Simpan Konfigurasi</button>
                        
                        <div class="preview-box">
                            <h4>Contoh Format Nomor Anggota:</h4>
                            <div class="preview-number" id="preview-number">
                                <?php 
                                // Build format: NNPPPKKK.RRR-YYYYXXX
                                $parts = [];
                                
                                // Bagian kode: negara + provinsi + kota digabungkan tanpa separator
                                $kode_parts = [];
                                if ($pengaturan_nomor['kode_negara'] ?? false) $kode_parts[] = 'ID';
                                if ($pengaturan_nomor['kode_provinsi'] ?? false) $kode_parts[] = '001';
                                if ($pengaturan_nomor['kode_kota'] ?? false) $kode_parts[] = '001';
                                
                                $kode_str = implode('', $kode_parts);
                                
                                // Ranting - add dot prefix if there's kode before it
                                $ranting_str = '';
                                if ($pengaturan_nomor['kode_ranting'] ?? true) {
                                    if (!empty($kode_str)) {
                                        $ranting_str = '.' . '001';
                                    } else {
                                        $ranting_str = '001';
                                    }
                                }
                                
                                // Year and sequence
                                $year_seq = '';
                                if (($pengaturan_nomor['tahun_daftar'] ?? true) || ($pengaturan_nomor['urutan_daftar'] ?? true)) {
                                    $year_part = ($pengaturan_nomor['tahun_daftar'] ?? true) ? date('Y') : '';
                                    $seq_part = ($pengaturan_nomor['urutan_daftar'] ?? true) ? '001' : '';
                                    
                                    // Add dash before year if there's something before it
                                    if (!empty($kode_str) || !empty($ranting_str)) {
                                        $year_seq = '-' . $year_part . $seq_part;
                                    } else {
                                        $year_seq = $year_part . $seq_part;
                                    }
                                }
                                
                                echo $kode_str . $ranting_str . $year_seq;
                                ?>
                            </div>
                            <div class="preview-label">Format: NNPPPKKK.RRR-YYYYXXX</div>
                        </div>
                    </form>
                </div>
                

                              
                <div id="kode-negara" class="kode-sub-tab-content">
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="number"><?php echo count($negara_list); ?></div>
                            <div class="label">Total Negara</div>
                        </div>
                        <div class="stat-card">
                            <div class="number"><?php echo count(array_filter($negara_list, fn($n) => $n['aktif'])); ?></div>
                            <div class="label">Negara Aktif</div>
                        </div>
                    </div>
                                        
                    <div class="kode-card">
                        <div class="kode-card-header">
                            <h3>🌍 Manajemen Negara</h3>                            
                        </div>
                        <div class="kode-card-body">
                            <div class="info-box">
                                <strong>ℹ️ Informasi:</strong> Kelola kode negara di sini. Negara default digunakan untuk anggota baru.
                            </div>
                            <table class="compact-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th style="width: 80px;">Kode</th>
                                        <th>Nama Negara</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 150px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="negara-table">
                                    <?php foreach ($negara_list as $i => $negara): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($negara['kode'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($negara['nama']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $negara['aktif'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $negara['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-<?php echo $negara['aktif'] ? 'warning' : 'success'; ?> btn-sm" onclick="toggleNegara(<?php echo $negara['id']; ?>)">
                                                <?php echo $negara['aktif'] ? '⛔' : '✅'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Sub-tab: Provinsi -->
                <div id="kode-provinsi" class="kode-sub-tab-content">
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="number"><?php echo count($provinsi_list); ?></div>
                            <div class="label">Total Provinsi</div>
                        </div>
                        <div class="stat-card">
                            <div class="number"><?php echo count(array_filter($provinsi_list, fn($p) => $p['aktif'])); ?></div>
                            <div class="label">Provinsi Aktif</div>
                        </div>
                    </div>
                    
                    <div class="kode-card">
                        <div class="kode-card-header">
                            <h3>🏙️ Manajemen Provinsi</h3>
                            <div style="display: flex; gap: 10px;">
                                <select id="filter-provinsi-negara" onchange="filterProvinsiTable()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="">Semua Negara</option>
                                    <?php foreach ($negara_list as $negara): ?>
                                        <option value="<?php echo $negara['id']; ?>"><?php echo htmlspecialchars($negara['nama']); ?></option>
                                    <?php endforeach; ?>
                                </select>                                
                            </div>
                        </div>
                        <div class="kode-card-body">
                            <div class="info-box">
                                <strong>ℹ️ Informasi:</strong> Kelola kode provinsi di sini. Provinsi diurutkan berdasarkan negara dan urutan internal.
                            </div>
                            <table class="compact-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th style="width: 80px;">Kode</th>
                                        <th>Nama Provinsi</th>
                                        <th>Negara</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 150px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="provinsi-table">
                                    <?php foreach ($provinsi_list as $i => $provinsi): ?>
                                    <tr data-negara="<?php echo $provinsi['negara_id']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($provinsi['kode'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($provinsi['nama_pengurus']); ?></td>
                                        <td><?php echo htmlspecialchars($provinsi['nama_negara'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $provinsi['aktif'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $provinsi['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-<?php echo $provinsi['aktif'] ? 'warning' : 'success'; ?> btn-sm" onclick="toggleProvinsi(<?php echo $provinsi['id']; ?>)">
                                                <?php echo $provinsi['aktif'] ? '⛔' : '✅'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Sub-tab: Kota -->
                <div id="kode-kota" class="kode-sub-tab-content">
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="number"><?php echo count($kota_list); ?></div>
                            <div class="label">Total Kota</div>
                        </div>
                        <div class="stat-card">
                            <div class="number"><?php echo count(array_filter($kota_list, fn($k) => $k['aktif'])); ?></div>
                            <div class="label">Kota Aktif</div>
                        </div>
                    </div>
                    
                    <div class="kode-card">
                        <div class="kode-card-header">
                            <h3>🏘️ Manajemen Kota/Kabupaten</h3>
                            <div style="display: flex; gap: 10px;">
                                <select id="filter-kota-provinsi" onchange="filterKotaTable()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="">Semua Provinsi</option>
                                    <?php foreach ($provinsi_list as $provinsi): ?>
                                        <option value="<?php echo $provinsi['id']; ?>"><?php echo htmlspecialchars($provinsi['nama_pengurus'] ?? $provinsi['nama'] ?? '-'); ?></option>
                                    <?php endforeach; ?>
                                </select>                                
                            </div>
                        </div>
                        <div class="kode-card-body">
                            <div class="info-box">
                                <strong>ℹ️ Informasi:</strong> Kelola kode kota/kabupaten di sini.
                            </div>
                            <table class="compact-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th style="width: 80px;">Kode</th>
                                        <th>Nama Kota</th>
                                        <th>Provinsi</th>
                                        <th>Negara</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 150px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="kota-table">
                                    <?php foreach ($kota_list as $i => $kota): ?>
                                    <tr data-provinsi="<?php echo $kota['provinsi_id']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($kota['kode'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($kota['nama'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($kota['nama_provinsi'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($kota['nama_negara'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo ($kota['aktif'] ?? 0) ? 'success' : 'secondary'; ?>">
                                                <?php echo ($kota['aktif'] ?? 0) ? 'Aktif' : 'Nonaktif'; ?>
                                            </span>
                                        </td>
                                        <td>    
                                            <button class="btn btn-<?php echo $kota['aktif'] ? 'warning' : 'success'; ?> btn-sm" onclick="toggleKota(<?php echo $kota['id']; ?>)">
                                                <?php echo $kota['aktif'] ? '⛔' : '✅'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Sub-tab: Ranting -->
                <div id="kode-ranting" class="kode-sub-tab-content">
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="number"><?php echo count($ranting_list); ?></div>
                            <div class="label">Total Ranting</div>
                        </div>
                        <div class="stat-card">
                            <div class="number"><?php echo count(array_filter($ranting_list, fn($r) => $r['aktif'])); ?></div>
                            <div class="label">Ranting Aktif</div>
                        </div>
                    </div>
                    
                    <div class="kode-card">
                        <div class="kode-card-header">
                            <h3>🏠 Manajemen Unit/Ranting</h3>
                            <div style="display: flex; gap: 10px;">
                                <select id="filter-ranting-kota" onchange="filterRantingTable()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="">Semua Kota</option>
                                    <?php foreach ($kota_list as $kota): ?>
                                        <option value="<?php echo $kota['id']; ?>"><?php echo htmlspecialchars($kota['nama']); ?></option>
                                    <?php endforeach; ?>
                                </select>                                
                            </div>
                        </div>
                        <div class="kode-card-body">
                            <div class="info-box">
                                <strong>ℹ️ Informasi:</strong> Kelola kode unit/ranting di sini.
                            </div>
                            <table class="compact-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th style="width: 80px;">Kode</th>
                                        <th>Nama Unit/Ranting</th>
                                        <th>Kota</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 150px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="ranting-table">
                                    <?php foreach ($ranting_list as $i => $ranting): ?>
                                    <tr data-kota="<?php echo $ranting['id_kota']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($ranting['kode'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($ranting['nama_ranting'] ?? $ranting['nama'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($ranting['kode_kota'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $ranting['aktif'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $ranting['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-<?php echo $ranting['aktif'] ? 'warning' : 'success'; ?> btn-sm" onclick="toggleRanting(<?php echo $ranting['id']; ?>)">
                                                <?php echo $ranting['aktif'] ? '⛔' : '✅'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    
    <!-- Modal Tingkat -->
    <div id="modal-tingkatan" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah/Edit Tingkat</h3>
                <button class="modal-close" onclick="closeModal('tingkatan')">&times;</button>
            </div>
            <form id="form-tingkatan" onsubmit="saveTingkat(event)">
                <input type="hidden" name="id" id="tingkatan_id">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Nama Tingkat</label>
                    <input type="text" name="nama_tingkat" id="tingkatan_nama" required>
                </div>
                <div class="form-group">
                    <label>Urutan</label>
                    <input type="number" name="urutan" id="tingkatan_urutan" value="<?php echo $next_urutan; ?>" required>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('tingkatan')">Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Jenis -->
    <div id="modal-jenis" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah/Edit Jenis Anggota</h3>
                <button class="modal-close" onclick="closeModal('jenis')">&times;</button>
            </div>
            <form id="form-jenis" onsubmit="saveJenis(event)">
                <input type="hidden" name="id" id="jenis_id">
                <input type="hidden" name="nama_jenis_lama" id="jenis_nama_lama">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Nama Jenis</label>
                    <input type="text" name="nama_jenis" id="jenis_nama" required>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('jenis')">Batal</button>
                </div>
            </form>
        </div>
    </div>           
    
    <script>
        // Kode management functions
        function showKodeTab(tabName, btnElement) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.sub-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.kode-sub-tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.sub-tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            if (btnElement) {
                btnElement.classList.add('active');
            } else {
                document.querySelector('.sub-tab').classList.add('active');
            }
            
            // Show corresponding tab content
            const kodeTabContent = document.getElementById('kode-' + tabName);
            if (kodeTabContent) {
                kodeTabContent.classList.add('active');
            }
        }
        
        function filterKotaTable() {
            const provinsiId = document.getElementById('filter-kota-provinsi').value;
            document.querySelectorAll('#kota-table tr').forEach(row => {
                if (!provinsiId || row.dataset.provinsi == provinsiId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function filterProvinsiTable() {
            const negaraId = document.getElementById('filter-provinsi-negara').value;
            document.querySelectorAll('#provinsi-table tr').forEach(row => {
                if (!negaraId || row.dataset.negara == negaraId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function filterRantingTable() {
            const kotaId = document.getElementById('filter-ranting-kota').value;
            document.querySelectorAll('#ranting-table tr').forEach(row => {
                if (!kotaId || row.dataset.kota == kotaId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Tab functions
        function showTab(tabName, btnElement) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            if (btnElement) {
                btnElement.classList.add('active');
            } else {
                document.querySelector('.tab').classList.add('active');
            }
            
            // Show corresponding tab content
            const tabContent = document.getElementById('tab-' + tabName);
            if (tabContent) {
                tabContent.classList.add('active');
            }
        }
        
        function showSubTab(subTabName, btnElement) {
            // Remove active class from all sub-tabs and contents
            document.querySelectorAll('.sub-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sub-tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.kode-sub-tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked sub-tab
            if (btnElement) {
                btnElement.classList.add('active');
            } else {
                document.querySelector('.sub-tab').classList.add('active');
            }
            
            // Show corresponding sub-tab content
            const subTabContent = document.getElementById('subtab-' + subTabName);
            if (subTabContent) {
                subTabContent.classList.add('active');
            }
        }
        
        function openModal(type) {
            document.getElementById('modal-' + type).classList.add('active');
            if (type === 'tingkatan') {
                document.getElementById('form-tingkatan').reset();
                document.getElementById('tingkatan_id').value = '';
                document.getElementById('form-tingkatan').querySelector('input[name="action"]').value = 'create';
            } else if (type === 'jenis') {
                document.getElementById('form-jenis').reset();
                document.getElementById('jenis_id').value = '';
                document.getElementById('form-jenis').querySelector('input[name="action"]').value = 'create';
            }
        }
        
        function closeModal(type) {
            document.getElementById('modal-' + type).classList.remove('active');
        }
        
        function editTingkat(id, nama, urutan) {
            document.getElementById('tingkatan_id').value = id;
            document.getElementById('tingkatan_nama').value = nama;
            document.getElementById('tingkatan_urutan').value = urutan;
            document.getElementById('form-tingkatan').querySelector('input[name="action"]').value = 'update';
            document.getElementById('modal-tingkatan').classList.add('active');
        }
        
        function editJenis(id, nama) {
            document.getElementById('jenis_id').value = id;
            document.getElementById('jenis_nama').value = nama;
            document.getElementById('jenis_nama_lama').value = nama;
            document.getElementById('form-jenis').querySelector('input[name="action"]').value = 'update';
            document.getElementById('modal-jenis').classList.add('active');
        }
        
        function saveTingkat(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-tingkatan'));
            formData.append('action', document.getElementById('form-tingkatan').querySelector('input[name="action"]').value || 'create');
            
            fetch('../../api/manage_tingkatan.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('tingkatan');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function saveJenis(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-jenis'));
            
            fetch('../../api/manage_jenis_anggota.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('jenis');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function saveNegara(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-negara'));
            
            fetch('../../api/manage_negara.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('negara');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function saveProvinsi(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-provinsi'));
            
            fetch('../../api/manage_provinsi.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('provinsi');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function saveKota(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-kota'));
            
            fetch('../../api/manage_kota.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('kota');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function saveRanting(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-ranting'));
            
            fetch('../../api/manage_unit_ranting.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('ranting');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function deleteTingkat(id, nama) {
            if (confirm('Apakah Anda yakin ingin menghapus tingkat "' + nama + '"?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('../../api/manage_tingkatan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function deleteJenis(id, nama) {
            if (confirm('Apakah Anda yakin ingin menghapus jenis "' + nama + '"?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                formData.append('nama_jenis', nama);
                
                fetch('../../api/manage_jenis_anggota.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function toggleNegara(id) {
            if (confirm('Apakah Anda yakin ingin mengubah status negara ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('../../api/manage_negara.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function toggleProvinsi(id) {
            if (confirm('Apakah Anda yakin ingin mengubah status provinsi ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('../../api/manage_provinsi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function toggleKota(id) {
            if (confirm('Apakah Anda yakin ingin mengubah status kota ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('../../api/manage_kota.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function toggleRanting(id) {
            if (confirm('Apakah Anda yakin ingin mengubah status unit/ranting ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('../../api/manage_unit_ranting.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function filterKota() {
            const provinsiId = document.getElementById('filter-kota-provinsi').value;
            const rows = document.querySelectorAll('#kota-table tr');
            rows.forEach(row => {
                if (!provinsiId || row.dataset.provinsi == provinsiId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function filterRanting() {
            const kotaId = document.getElementById('filter-ranting-kota').value;
            const rows = document.querySelectorAll('#ranting-table tr');
            rows.forEach(row => {
                if (!kotaId || row.dataset.kota == kotaId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Close modal on outside click
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        }
        
        // Format preview update
        document.querySelectorAll('input[type="checkbox"][name^="kode_"], input[type="checkbox"][name="tahun_daftar"], input[type="checkbox"][name="urutan_daftar"]').forEach(el => {
            el.addEventListener('change', updatePreview);
        });
        
        function updatePreview() {
            const kodeNegara = document.getElementById('kode_negara').checked;
            const kodeProvinsi = document.getElementById('kode_provinsi').checked;
            const kodeKota = document.getElementById('kode_kota').checked;
            const kodeRanting = document.getElementById('kode_ranting').checked;
            const tahunDaftar = document.getElementById('tahun_daftar').checked;
            const urutanDaftar = document.getElementById('urutan_daftar').checked;
            
            // Build format: NNPPPKKK.RRR-YYYYXXX
            let kodeStr = '';
            if (kodeNegara) kodeStr += 'ID';
            if (kodeProvinsi) kodeStr += '001';
            if (kodeKota) kodeStr += '001';
            
            // Ranting - add dot prefix if there's kode before it
            let rantingStr = '';
            if (kodeRanting) {
                if (kodeStr !== '') {
                    rantingStr = '.001';
                } else {
                    rantingStr = '001';
                }
            }
            
            // Year and sequence - add dash prefix if there's something before
            let yearSeqStr = '';
            if (tahunDaftar || urutanDaftar) {
                const yearPart = tahunDaftar ? new Date().getFullYear().toString() : '';
                const seqPart = urutanDaftar ? '001' : '';
                
                if (kodeStr !== '' || rantingStr !== '') {
                    yearSeqStr = '-' + yearPart + seqPart;
                } else {
                    yearSeqStr = yearPart + seqPart;
                }
            }
            
            document.getElementById('preview-number').textContent = kodeStr + rantingStr + yearSeqStr;
        }
    </script>
</body>
</html>
