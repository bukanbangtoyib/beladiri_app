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
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

$GLOBALS['permission_manager'] = $permission_manager;

if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$id = (int)$_GET['id'];
$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'pusat';
if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

// Map jenis to table
$table_map = [
    'pusat' => ['table' => 'negara', 'label' => 'Negara'],
    'provinsi' => ['table' => 'provinsi', 'label' => 'Provinsi'],
    'kota' => ['table' => 'kota', 'label' => 'Kota/Kabupaten']
];

$table_info = $table_map[$jenis];
$table_name = $table_info['table'];
$label = $table_info['label'];

// Label jenis - for display
$label_jenis = $table_info['label'];

$error = '';
$success = '';

// Handle SK upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['sk_file'])) {
    $file = $_FILES['sk_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error upload file!";
    } else {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_ext != 'pdf') {
            $error = "Hanya file PDF yang diperbolehkan!";
        } elseif ($file['size'] > 5242880) { // 5MB
            $error = "Ukuran file maksimal 5MB!";
        } else {
            $upload_dir = '../../uploads/sk_pengurus/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Get data untuk naming
            $data = $conn->query("SELECT * FROM $table_name WHERE id = $id")->fetch_assoc();
            $nama_clean = preg_replace("/[^a-z0-9 -]/i", "_", $data['nama']);
            $nama_clean = str_replace(" ", "_", $nama_clean);
            
            // Get next revision number
            $pattern = 'SK-' . $nama_clean . '-';
            $max_revision = 0;
            
            if (is_dir($upload_dir)) {
                $files = scandir($upload_dir);
                foreach ($files as $f) {
                    if (strpos($f, $pattern) === 0) {
                        if (preg_match('/-(\d{2})\.[^.]+$/', $f, $matches)) {
                            $revision = (int)$matches[1];
                            if ($revision > $max_revision) {
                                $max_revision = $revision;
                            }
                        }
                    }
                }
            }
            
            $next_revision = $max_revision + 1;
            $file_name = $pattern . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . '.pdf';
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $success = "SK berhasil diupload! (Revisi " . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . ")";
            } else {
                $error = "Gagal menyimpan file!";
            }
        }
    }
}

// Handle SK delete
if (isset($_GET['delete_sk']) && $_SESSION['role'] == 'admin') {
    $sk_file = basename($_GET['delete_sk']);
    $upload_dir = '../../uploads/sk_pengurus/';
    $file_path = $upload_dir . $sk_file;
    
    if (file_exists($file_path) && is_file($file_path)) {
        unlink($file_path);
        $success = "SK berhasil dihapus!";
    }
}

// Ambil data pengurus - tanpa join untuk menghindari error kolom
$sql = "SELECT * FROM $table_name WHERE id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("$label tidak ditemukan!");
}

$pengurus = $result->fetch_assoc();

// Ambil anak (child) data
$anak_sql = "SELECT id, nama FROM $table_name WHERE " . ($jenis == 'pusat' ? '1=1' : ($jenis == 'provinsi' ? 'negara_id' : 'provinsi_id')) . " = $id ORDER BY nama";
$anak_result = $conn->query($anak_sql);

// Cari SK files
$upload_dir = '../../uploads/sk_pengurus/';
$sk_files = [];

if (is_dir($upload_dir)) {
    $nama_clean = preg_replace("/[^a-z0-9 -]/i", "_", $pengurus['nama']);
    $nama_clean = str_replace(" ", "_", $nama_clean);
    $pattern = 'SK-' . $nama_clean . '-';
    
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        if (strpos($file, $pattern) === 0) {
            $sk_files[] = $file;
        }
    }
    
    rsort($sk_files);
}

// Label jenis
$label_jenis_text = [
    'pusat' => 'Negara',
    'provinsi' => 'Provinsi',
    'kota' => 'Kota/Kabupaten'
];

// Ranting count (khusus untuk kota)
$ranting_count = 0;
$ranting_result = NULL;
if ($jenis == 'kota') {
    $r = $conn->query("SELECT COUNT(*) as count FROM ranting WHERE kota_id = $id")->fetch_assoc();
    $ranting_count = $r['count'];
    $ranting_result = $conn->query("SELECT id, nama_ranting, jenis, ketua_nama FROM ranting WHERE kota_id = $id ORDER BY nama_ranting");
}

// Status aktif
$status = (strtotime($pengurus['periode_akhir']) >= strtotime(date('Y-m-d'))) ? 'Aktif' : 'Tidak Aktif';
$status_class = $status == 'Aktif' ? 'status-aktif' : 'status-tidak';

// Format tanggal to DD-MM-YYYY
function formatTanggal($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

function getRevisionNumber($filename) {
    if (preg_match('/-(\d{2})\.[^.]+$/', $filename, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?php echo $label_jenis; ?> - Sistem Beladiri</title>
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
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .breadcrumb {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .header-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .header-info h1 {
            color: #333;
            margin-bottom: 12px;
            font-size: 28px;
        }
        
        .header-info p {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #1976d2;
            margin-right: 10px;
        }
        
        .status-aktif {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-tidak {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .info-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        h3 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            font-size: 18px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .label {
            color: #666;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .value {
            color: #333;
            font-size: 15px;
        }
        
        .value-highlight {
            color: #667eea;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #f8f9fa;
            padding: 14px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        
        .btn-warning:hover {
            background: #ffb300;
            transform: translateY(-2px);
        }
        
        .btn-download {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            font-size: 13px;
        }
        
        .btn-download:hover {
            background: #218838;
        }
        
        .btn-danger-small {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
        }
        
        .button-group {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .link-nav {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .link-nav:hover {
            text-decoration: underline;
        }
        
        .sk-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .sk-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .sk-info {
            flex: 1;
        }
        
        .sk-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .sk-meta {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }
        
        .sk-actions {
            display: flex;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .sk-upload-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 2px dashed #667eea;
            margin-bottom: 20px;
        }
        
        .sk-upload-form input[type="file"] {
            display: block;
            margin: 15px 0;
        }
        
        @media print {
            .navbar, .button-group, .sk-upload-form, .btn { display: none; }
        }
    </style>
</head>
<body>
    <?php renderNavbar('📋 Detail ' . $label_jenis); ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="pengurus.php">Pengurus</a> > 
            <a href="pengurus_list.php?jenis=<?php echo $jenis; ?>"><?php echo $label_jenis; ?></a> > 
            <strong><?php echo htmlspecialchars($pengurus['nama']); ?></strong>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Header Card -->
        <div class="header-card">
            <div class="header-info">
                <h1><?php echo htmlspecialchars($pengurus['nama']); ?></h1>
                <p style="margin-bottom: 15px;">
                    <span class="badge"><?php echo $label_jenis; ?></span>
                    <span class="<?php echo $status_class; ?>">● <?php echo $status; ?></span>
                </p>
                <p><strong>Ketua:</strong> <?php echo htmlspecialchars($pengurus['ketua_nama'] ?? '-'); ?></p>
            </div>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div>
                <a href="pengurus_edit.php?id=<?php echo $id; ?>" class="btn btn-warning">✏️ Edit Data</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Informasi Dasar -->
        <div class="info-card">
            <h3>📌 Informasi Dasar</h3>
            
            <div class="info-row">
                <div class="label">Jenis Pengurus</div>
                <div class="value"><?php echo $label_jenis; ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">No SK</div>
                <div class="value"><?php echo htmlspecialchars($pengurus['sk_kepengurusan'] ?? '-'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Periode</div>
                <div class="value"><?php echo formatTanggal($pengurus['periode_mulai']); ?> - <?php echo formatTanggal($pengurus['periode_akhir']); ?></div>
            </div>
            
            <?php if (isset($pengurus['pengurus_induk']) && $pengurus['pengurus_induk']): ?>
            <div class="info-row">
                <div class="label">Pengurus Induk</div>
                <div class="value">
                    <a href="pengurus_detail.php?id=<?php echo $pengurus['pengurus_induk_id']; ?>" class="link-nav">
                        <?php echo htmlspecialchars($pengurus['pengurus_induk']); ?> →
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- SK Pembentukan Section -->
        <div class="info-card">
            <h3>📄 SK Pembentukan</h3>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="sk-upload-form">
                <form method="POST" enctype="multipart/form-data">
                    <label>Upload SK Baru (PDF)</label>
                    <input type="file" name="sk_file" accept=".pdf" required>
                    <div style="font-size: 12px; color: #999; margin-top: 8px;">
                        Format: PDF | Ukuran maksimal: 5MB<br>
                        Setiap upload baru akan otomatis menambah nomor revisi
                    </div>
                    <button type="submit" class="btn btn-download" style="margin-top: 10px;">📁 Upload SK</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="sk-section">
                <?php if (count($sk_files) > 0): ?>
                    <?php foreach ($sk_files as $sk_file): 
                        $file_path = $upload_dir . $sk_file;
                        $file_size = filesize($file_path);
                        $file_size_kb = round($file_size / 1024, 2);
                        $revisi = getRevisionNumber($sk_file);
                        $upload_time = filectime($file_path);
                    ?>
                    <div class="sk-card">
                        <div class="sk-info">
                            <div class="sk-name">
                                📄 <?php echo htmlspecialchars($sk_file); ?>
                            </div>
                            <div class="sk-meta">
                                <strong>Revisi:</strong> <?php echo str_pad($revisi, 2, '0', STR_PAD_LEFT); ?> | 
                                <strong>Upload:</strong> <?php echo formatTanggal(date('Y-m-d', $upload_time)); ?> | 
                                <strong>Ukuran:</strong> <?php echo $file_size_kb; ?> KB
                            </div>
                        </div>
                        <div class="sk-actions">
                            <a href="sk_download_pengurus.php?file=<?php echo urlencode($sk_file); ?>&id=<?php echo $id; ?>" 
                               class="btn btn-download">📥 Download</a>
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                            <a href="pengurus_detail.php?id=<?php echo $id; ?>&delete_sk=<?php echo urlencode($sk_file); ?>" 
                               class="btn btn-danger-small" onclick="return confirm('Hapus SK ini?')">Hapus</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>Belum ada SK pembentukan yang diupload</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Struktur Organisasi -->
        <div class="info-card">
            <h3>👥 Struktur Organisasi</h3>
            
            <div class="info-row">
                <div class="label">Ketua</div>
                <div class="value"><?php echo htmlspecialchars($pengurus['ketua_nama'] ?? '-'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Alamat Sekretariat</div>
                <div class="value"><?php echo nl2br(htmlspecialchars($pengurus['alamat_sekretariat'] ?? '-')); ?></div>
            </div>
        </div>
        
        <!-- Struktur yang Dinaungi -->
        <?php if ($anak_result->num_rows > 0): ?>
        <div class="info-card">
            <h3>📊 Struktur yang Dinaungi</h3>
            
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $anak_result->num_rows; ?></div>
                    <div class="stat-label">
                        <?php 
                        if ($jenis == 'pusat') {
                            echo 'Pengurus Provinsi';
                        } else {
                            echo 'Pengurus Kota';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th>Ketua</th>
                        <th>Periode</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $anak_result->data_seek(0);
                    while ($row = $anak_result->fetch_assoc()): 
                        $child_table = ($jenis == 'pusat' ? 'negara' : ($jenis == 'provinsi' ? 'provinsi' : 'kota'));
                        $child_detail = $conn->query("SELECT nama, kode FROM $child_table WHERE id = " . $row['id'])->fetch_assoc();
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                        <td><?php echo $label_jenis_text[$jenis]; ?></td>
                        <td><?php echo htmlspecialchars($child_detail['kode'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($child_detail['nama'] ?? '-'); ?></td>
                        <td>
                            <a href="pengurus_detail.php?id=<?php echo $row['id']; ?>&jenis=<?php echo $jenis == 'pusat' ? 'provinsi' : 'kota'; ?>" class="link-nav">Lihat →</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Unit/Ranting yang Dinaungi (khusus Pengurus Kota) -->
        <?php if ($jenis == 'kota'): ?>
        <div class="info-card">
            <h3>🌳 Unit / Ranting yang Dinaungi</h3>
            
            <?php if ($ranting_count > 0): ?>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $ranting_count; ?></div>
                    <div class="stat-label">Unit / Ranting Aktif</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nama Unit/Ranting</th>
                        <th>Jenis</th>
                        <th>Ketua</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $ranting_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_ranting']); ?></strong></td>
                        <td><?php echo ucfirst($row['jenis']); ?></td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td>
                            <a href="ranting_detail.php?id=<?php echo $row['id']; ?>" class="link-nav">Lihat →</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🌳</div>
                <p>Belum ada unit/ranting yang dinaungi</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>