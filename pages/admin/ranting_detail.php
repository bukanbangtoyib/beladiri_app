<?php
/**
 * @var array $pengaturan_nomor Number format settings from config/settings.php
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';
include '../../config/settings.php';

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

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Helper function untuk format no_anggota sesuai pengaturan
function formatNoAnggotaDisplay($no_anggota, $pengaturan_nomor) {
    if (empty($no_anggota)) return $no_anggota;
    
    // Try to parse the format
    if (preg_match('/^([A-Za-z0-9]+)\.([A-Za-z0-9]+)-([A-Za-z0-9]+)$/', $no_anggota, $matches)) {
        $kode_full = $matches[1];
        $ranting_kode = $matches[2];
        $year_seq = $matches[3];
    } elseif (preg_match('/^([A-Za-z0-9]+)-([A-Za-z0-9]+)$/', $no_anggota, $matches)) {
        $kode_full = '';
        $ranting_kode = $matches[1];
        $year_seq = $matches[2];
    } elseif (preg_match('/^([A-Za-z0-9]+)\.([A-Za-z0-9]+)$/', $no_anggota, $matches)) {
        $kode_full = $matches[1];
        $ranting_kode = $matches[2];
        $year_seq = '';
    } else {
        return $no_anggota;
    }
    
    $negara_kode = '';
    $provinsi_kode = '';
    $kota_kode = '';
    
    if (strlen($kode_full) >= 2) {
        $negara_kode = substr($kode_full, 0, 2);
    }
    if (strlen($kode_full) >= 5) {
        $provinsi_kode = substr($kode_full, 2, 3);
    }
    if (strlen($kode_full) >= 8) {
        $kota_kode = substr($kode_full, 5, 3);
    }
    
    $tahun = '';
    $urutan = '';
    if (strlen($year_seq) >= 4) {
        $tahun = substr($year_seq, 0, 4);
        $urutan = substr($year_seq, 4);
    }
    
    $kode_parts = [];
    if ($pengaturan_nomor['kode_negara'] ?? true) {
        $kode_parts[] = $negara_kode;
    }
    if ($pengaturan_nomor['kode_provinsi'] ?? true) {
        $kode_parts[] = $provinsi_kode;
    }
    if ($pengaturan_nomor['kode_kota'] ?? true) {
        $kode_parts[] = $kota_kode;
    }
    $kode_str = implode('', $kode_parts);
    
    $ranting_str = '';
    if ($pengaturan_nomor['kode_ranting'] ?? true) {
        if (!empty($kode_str)) {
            $ranting_str = '.' . $ranting_kode;
        } else {
            $ranting_str = $ranting_kode;
        }
    }
    
    $year_seq_str = '';
    $year_part = ($pengaturan_nomor['tahun_daftar'] ?? true) ? $tahun : '';
    $seq_part = ($pengaturan_nomor['urutan_daftar'] ?? true) ? $urutan : '';
    
    if (!empty($year_part) || !empty($seq_part)) {
        if (!empty($kode_str) || !empty($ranting_str)) {
            $year_seq_str = '-' . $year_part . $seq_part;
        } else {
            $year_seq_str = $year_part . $seq_part;
        }
    }
    
    return $kode_str . $ranting_str . $year_seq_str;
}

$id = (int)$_GET['id'];

$sql = "SELECT r.*, 
        k.id as kota_id,
        prov.id as provinsi_id,
        n.id as negara_id,
        k.nama as nama_kota,
        k.kode as kode_kota,
        prov.nama as nama_provinsi,
        prov.kode as kode_provinsi,
        n.nama as nama_negara,
        n.kode as kode_negara
        FROM ranting r 
        LEFT JOIN kota k ON r.kota_id = k.id
        LEFT JOIN provinsi prov ON k.provinsi_id = prov.id
        LEFT JOIN negara n ON prov.negara_id = n.id
        WHERE r.id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Unit/Ranting tidak ditemukan!");
}

$ranting = $result->fetch_assoc();

// Ambil daftar anggota di ranting ini
$anggota_sql = "SELECT COUNT(*) as count FROM anggota WHERE ranting_saat_ini_id = $id";
$anggota_count = $conn->query($anggota_sql)->fetch_assoc();

// Ambil data tingkatan untuk mapping urutan ke nama
$tingkatan_map = [];
$tingkat_stats = [];
$tk_result = $conn->query("SELECT urutan, nama_tingkat FROM tingkatan ORDER BY urutan");
while ($tk = $tk_result->fetch_assoc()) {
    $tingkatan_map[$tk['urutan']] = $tk['nama_tingkat'];
}

// Hitung anggota per tingkat
foreach ($tingkatan_map as $urutan => $nama) {
    $tingkat_stats[$urutan] = $conn->query(
        "SELECT COUNT(*) as cnt FROM anggota WHERE ranting_saat_ini_id = $id AND tingkat_id = $urutan"
    )->fetch_assoc()['cnt'];
}

// Ambil jadwal latihan
$jadwal_sql = "SELECT * FROM jadwal_latihan WHERE ranting_id = $id ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')";
$jadwal_result = $conn->query($jadwal_sql);

// Ambil daftar pelatih
$pelatih_sql = "SELECT a.id, a.nama_lengkap, a.no_anggota, t.nama_tingkat, rp.keterangan 
                FROM ranting_pelatih rp 
                JOIN anggota a ON rp.anggota_id = a.id 
                LEFT JOIN tingkatan t ON a.tingkat_id = t.urutan 
                WHERE rp.ranting_id = $id";
$pelatih_result = $conn->query($pelatih_sql);

// Cari file SK - HANYA YANG TERAKHIR
$upload_dir = '../../uploads/sk_pembentukan/';
$sk_file = null;
$sk_files = [];

if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        // Cari file yang cocok dengan pattern SK-ranting-kota-XX.pdf
        if (strpos($file, 'SK-') === 0) {
            $sk_files[] = $file;
        }
    }
}

// Sort descending untuk mendapatkan revisi terbaru di index 0
rsort($sk_files);

// Ambil hanya file terakhir
if (count($sk_files) > 0) {
    $sk_file = $sk_files[0];
}

function get_revision_number($filename) {
    // Extract nomor revisi dari format: SK-name-kota-XX.ext
    if (preg_match('/-(\d{2})\.[^.]+$/', $filename, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

// Handle SK upload
$upload_error = '';
$upload_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['sk_file'])) {
    // Permission check for upload (calculated later but we need it here)
    $can_upload = false;
    $user_role = $_SESSION['role'] ?? '';
    $user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;
    
    if (in_array($user_role, ['admin', 'superadmin'])) {
        $can_upload = true;
    } elseif ($user_role === 'pengkot') {
        $can_upload = ($ranting['kota_id'] ?? 0) == $user_pengurus_id;
    } elseif (($user_role === 'ranting' || $user_role === 'unit') && isset($_SESSION['ranting_id']) && $_SESSION['ranting_id'] == $id) {
        $can_upload = true;
    }

    if (!$can_upload) {
        $upload_error = "Akses ditolak! Anda tidak memiliki izin untuk mengupload SK.";
    } else {
        $file = $_FILES['sk_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_error = "Error upload file!";
        } else {
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file_ext != 'pdf') {
                $upload_error = "Hanya file PDF yang diperbolehkan!";
            } elseif ($file['size'] > 5242880) { // 5MB
                $upload_error = "Ukuran file maksimal 5MB!";
            } else {
                $upload_dir = '../../uploads/sk_pembentukan/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $ranting_clean = preg_replace("/[^a-z0-9 -]/i", "_", $ranting['nama_ranting']);
                $ranting_clean = str_replace(" ", "_", $ranting_clean);
                $kota_clean = preg_replace("/[^a-z0-9 -]/i", "_", $ranting['nama_kota'] ?? 'Unknown');
                $kota_clean = str_replace(" ", "_", $kota_clean);
                
                $pattern = 'SK-' . $ranting_clean . '-' . $kota_clean . '-';
                $max_revision = 0;
                
                if (is_dir($upload_dir)) {
                    $files = scandir($upload_dir);
                    foreach ($files as $f) {
                        if (strpos($f, $pattern) === 0) {
                            $rev = get_revision_number($f);
                            if ($rev > $max_revision) $max_revision = $rev;
                        }
                    }
                }
                
                $next_revision = $max_revision + 1;
                $file_name = $pattern . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . '.pdf';
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $upload_success = "SK berhasil diupload! (Revisi " . str_pad($next_revision, 2, '0', STR_PAD_LEFT) . ")";
                    // Refresh file list
                    $sk_files = [];
                    if (is_dir($upload_dir)) {
                        $files = scandir($upload_dir);
                        foreach ($files as $f) {
                            if (strpos($f, 'SK-') === 0) $sk_files[] = $f;
                        }
                    }
                    rsort($sk_files);
                    if (count($sk_files) > 0) $sk_file = $sk_files[0];
                } else {
                    $upload_error = "Gagal menyimpan file!";
                }
            }
        }
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'reset_to_default') {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    
    $check = $conn->query("SELECT u.id, u.username, u.nama_lengkap, u.role, u.no_anggota, a.tanggal_lahir 
                           FROM users u 
                           LEFT JOIN anggota a ON u.no_anggota = a.no_anggota 
                           WHERE u.id = $target_user_id");
    
    if ($check->num_rows > 0) {
        $target_user = $check->fetch_assoc();
        
        $is_allowed = false;
        if (in_array($_SESSION['role'], ['admin', 'superadmin'])) {
            $is_allowed = true;
        } elseif (in_array($_SESSION['role'], ['unit', 'ranting', 'pengkot'])) {
            if (in_array($_SESSION['role'], ['unit', 'ranting'])) {
                $anggota_check = $conn->query("SELECT ranting_saat_ini_id FROM anggota WHERE no_anggota = '" . $target_user['no_anggota'] . "'")->fetch_assoc();
                if ($anggota_check && $anggota_check['ranting_saat_ini_id'] == $_SESSION['ranting_id']) $is_allowed = true;
            } elseif ($_SESSION['role'] == 'pengkot') {
                $anggota_check = $conn->query("SELECT r.kota_id FROM anggota a JOIN ranting r ON a.ranting_saat_ini_id = r.id WHERE a.no_anggota = '" . $target_user['no_anggota'] . "'")->fetch_assoc();
                if ($anggota_check && $anggota_check['kota_id'] == $_SESSION['pengurus_id']) $is_allowed = true;
            }
        }
        
        if ($is_allowed) {
            $username = $target_user['username'];
            $role = $target_user['role'];
            $nama_lengkap = $target_user['nama_lengkap'];
            
            include_once '../../helpers/user_auto_creation.php';
            
            $password_default = '';
            if ($role === 'anggota') {
                $tgl = (!empty($target_user['tanggal_lahir']) && $target_user['tanggal_lahir'] !== '0000-00-00') ? date('dmY', strtotime($target_user['tanggal_lahir'])) : '';
                $password_default = formatPwd($nama_lengkap) . $tgl;
            }
            
            if ($password_default !== '') {
                $hashed = password_hash($password_default, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $target_user_id);
                if ($stmt->execute()) {
                    $upload_success = "Password anggota " . htmlspecialchars($username) . " berhasil direset ke default!";
                } else {
                    $upload_error = "Gagal reset password: " . $stmt->error;
                }
            } else {
                $upload_error = "Role ini tidak memiliki aturan password default.";
            }
        } else {
            $upload_error = "Akses ditolak! Anda tidak memiliki izin untuk mereset password user ini.";
        }
    } else {
        $upload_error = "User tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Unit/Ranting - Sistem Beladiri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .label { color: #666; font-weight: 600; }
        .value { color: #333; }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-ukm { background: #e3f2fd; color: #1976d2; }
        .badge-ranting { background: #f3e5f5; color: #7b1fa2; }
        .badge-unit { background: #fff3e0; color: #e65100; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
            font-size: 12px;
        }

        td { 
            padding: 12px; 
            border-bottom: 1px solid #eee;
            font-size: 12px; 
        }

        tr:hover { background: #f9f9f9; }
        
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
        
        .btn-primary { background: #667eea; color: white; }
        
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        
        .btn-warning:hover {
            background: #ffb300;
            transform: translateY(-2px);
        }

        .btn-download { background: #28a745; color: white; padding: 10px 20px; font-size: 13px; }
        .btn-download:hover { background: #218838; }
        
        .button-group { margin-top: 20px; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        h3 { color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea; }
        
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
        
        .stat-card {
            display: inline-block;
            background: #f8f9fa;
            padding: 15px 25px;
            border-radius: 8px;
            margin: 10px 10px 0 0;
        }
        
        .stat-number { font-size: 24px; font-weight: 700; color: #667eea; }
        .stat-label { font-size: 12px; color: #666; }
        
        .header-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .header-info h1 {
            color: #333;
            margin-bottom: 12px;
            font-size: 28px;
        }
        
        .header-stats-grid {
            display: grid;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .header-stats-grid.cols-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .header-stats-grid.tingkat-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stat-item .stat-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .stat-item .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-item .stat-value.chairman {
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-aktif { color: #27ae60; font-weight: 600; }
        .status-tidak { color: #e74c3c; font-weight: 600; }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }

        .link-nav {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .link-nav:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php renderNavbar('Detail Unit/Ranting'); ?>
    
    <div style="display: flex; justify-content: center;">
        <div class="container" style="width: 100%;">
            <?php if ($upload_error): ?>
                <div style="background: #fff5f5; color: #c00; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                    ⚠️ <?php echo $upload_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($upload_success): ?>
                <div style="background: #f0fdf4; color: #060; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                    ✅ <?php echo $upload_success; ?>
                </div>
            <?php endif; ?>
            <!-- Header Card -->
            <div class="header-card">
                <div class="header-info">
                    <h1><?php echo htmlspecialchars($ranting['nama_ranting']); ?></h1>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <span class="badge badge-<?php echo $ranting['jenis']; ?>"><?php echo strtoupper($ranting['jenis']); ?></span>
                        <div class="header-actions">
                            <?php 
                            $can_edit = false;
                            $user_role = $_SESSION['role'] ?? '';
                            $user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;
                            
                            if (in_array($user_role, ['admin', 'superadmin'])) {
                                $can_edit = true;
                            } elseif ($user_role === 'pengkot') {
                                // Pengkot can edit ranting in their city
                                $can_edit = ($ranting['kota_id'] ?? 0) == $user_pengurus_id;
                            } elseif (($user_role === 'ranting' || $user_role === 'unit') && isset($_SESSION['ranting_id']) && $_SESSION['ranting_id'] == $id) {
                                $can_edit = true;
                            }
                            // catatan: negara dan pengprov tidak dapat mengedit ranting
                            if ($can_edit): 
                            ?>
                            <a href="ranting_edit.php?id=<?php echo $id; ?>" class="btn btn-warning" style="margin-right: 10px;">✏️ Edit</a>
                            <?php endif; ?>
                            <button onclick="window.print()" class="btn btn-warning" style="background: #6c757d;">
                                🖨️ Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="header-stats-grid cols-2">
                        <div class="stat-item">
                            <div class="stat-label">Nama Ketua</div>
                            <div class="stat-value chairman"><?php echo htmlspecialchars($ranting['ketua_nama'] ?? '-'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Total Anggota</div>
                            <div class="stat-value"><?php echo array_sum($tingkat_stats); ?></div>
                        </div>
                    </div>
                    <div class="header-stats-grid tingkat-grid">
                        <?php foreach ($tingkatan_map as $urutan => $nama_tingkat): ?>
                        <div class="stat-item">
                            <div class="stat-label"><?php echo htmlspecialchars($nama_tingkat); ?></div>
                            <div class="stat-value"><?php echo $tingkat_stats[$urutan] ?? 0; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="info-card">
                <h3>📋 Informasi Dasar</h3>
                
                <div class="info-row">
                    <div class="label">Jenis</div>
                    <div class="value"><?php echo ucfirst($ranting['jenis']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Alamat</div>
                    <div class="value"><?php echo nl2br(htmlspecialchars($ranting['alamat'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">No Kontak</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['no_kontak'] ?? '-'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Negara</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['nama_negara'] ?? '-'); ?> (<?php echo htmlspecialchars($ranting['kode_negara'] ?? '-'); ?>)</div>
                </div>
                
                <div class="info-row">
                    <div class="label">Provinsi</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['nama_provinsi'] ?? '-'); ?> (<?php echo htmlspecialchars($ranting['kode_provinsi'] ?? '-'); ?>)</div>
                </div>
                
                <div class="info-row">
                    <div class="label">Kota</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['nama_kota'] ?? '-'); ?> (<?php echo htmlspecialchars($ranting['kode_kota'] ?? '-'); ?>)</div>
                </div>
            </div>
            
            <div class="info-card">
                <h3>👤 Struktur Organisasi</h3>
                
                <div class="info-row">
                    <div class="label">Ketua</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['ketua_nama'] ?? '-'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Penanggung Jawab Teknik</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['penanggung_jawab_teknik'] ?? '-'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="label">Tanggal SK</div>
                    <div class="value"><?php echo date('d M Y', strtotime($ranting['tanggal_sk_pembentukan'])); ?></div>
                </div>

                <div class="info-row">
                    <div class="label">No SK Pembentukan</div>
                    <div class="value"><?php echo htmlspecialchars($ranting['no_sk_pembentukan'] ?? '-'); ?></div>
                </div>
            </div>

            <!-- SK PEMBENTUKAN SECTION - HANYA SK TERAKHIR -->
            <div class="info-card">
                <h3>📄 SK Pembentukan</h3>
                
                <?php if ($can_edit): ?>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px dashed #667eea; margin-bottom: 20px;">
                    <form method="POST" enctype="multipart/form-data">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">Upload SK Baru (PDF)</label>
                        <input type="file" name="sk_file" accept=".pdf" required style="display: block; margin-bottom: 15px;">
                        <div style="font-size: 12px; color: #999; margin-bottom: 15px;">
                            Format: PDF | Ukuran maksimal: 5MB<br>
                            Setiap upload baru akan otomatis menambah nomor revisi
                        </div>
                        <button type="submit" class="btn btn-download">
                            <i class="fas fa-upload"></i> Upload SK
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="sk-section">
                    <?php if ($sk_file): 
                        $file_path = $upload_dir . $sk_file;
                        $file_size = filesize($file_path);
                        $file_size_kb = round($file_size / 1024, 2);
                        $revisi = get_revision_number($sk_file);
                        $upload_time = filectime($file_path);
                    ?>
                        <div class="sk-card">
                            <div class="sk-info">
                                <div class="sk-name">
                                    <i class="fas fa-file-pdf" style="color: #dc3545; margin-right: 8px;"></i>
                                    <?php echo htmlspecialchars($sk_file); ?>
                                </div>
                                <div class="sk-meta">
                                    <strong>Revisi:</strong> <?php echo str_pad($revisi, 2, '0', STR_PAD_LEFT); ?> | 
                                    <strong>Upload:</strong> <?php echo date('d M Y H:i', $upload_time); ?> | 
                                    <strong>Ukuran:</strong> <?php echo $file_size_kb; ?> KB
                                </div>
                            </div>
                            <a href="sk_download.php?file=<?php echo urlencode($sk_file); ?>&ranting=<?php echo $ranting['id']; ?>" 
                            class="btn btn-download">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <p>📭 Belum ada SK pembentukan yang diupload</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-card" id="trainer-section">
                <h3>👨‍🏫 Daftar Pelatih</h3>
                <?php if ($pelatih_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Pelatih</th>
                                <th>No Anggota</th>
                                <th>Tingkatan</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $pelatih_result->fetch_assoc()): ?>
                                <tr>
                                    <td><a href="anggota_detail.php?id=<?php echo $p['id']; ?>" class="link-nav"><?php echo htmlspecialchars($p['nama_lengkap']); ?></a></td>
                                    <td><code><?php echo htmlspecialchars(formatNoAnggotaDisplay($p['no_anggota'], $pengaturan_nomor)); ?></code></td>
                                    <td><span class="badge badge-ukm" style="background: #e3f2fd; color: #1976d2;"><?php echo htmlspecialchars($p['nama_tingkat'] ?? '-'); ?></span></td>
                                    <td><small><?php echo htmlspecialchars($p['keterangan'] ?? '-'); ?></small></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <p>📭 Belum ada pelatih yang terdata di ranting ini</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h3>⏰ Jadwal Latihan</h3>
                
                <?php if ($jadwal_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam Mulai</th>
                            <th>Jam Selesai</th>
                            <th style="width: 100px;">Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $jadwal_result->fetch_assoc()): 
                            $mulai = strtotime($row['jam_mulai']);
                            $selesai = strtotime($row['jam_selesai']);
                            $durasi = round(($selesai - $mulai) / 3600);
                        ?>
                        <tr>
                            <td><strong><?php echo $row['hari']; ?></strong></td>
                            <td><?php echo date('H:i', $mulai); ?></td>
                            <td><?php echo date('H:i', $selesai); ?></td>
                            <td><?php echo $durasi; ?> jam</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <p>📭 Belum ada jadwal latihan</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- MEMBER LIST SECTION [BARU] -->
            <!-- Tambahkan HTML section ini SEBELUM closing tag button-group dan container di ranting_detail.php -->

            <div class="info-card" id="member-section">
                <h3>👥 Daftar Anggota Unit/Ranting</h3>
                
                <!-- Filter Section [BARU] -->
                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: flex-end;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #666;">
                                🔍 Cari Nama Anggota
                            </label>
                            <input 
                                type="text" 
                                id="memberFilter" 
                                placeholder="Ketik nama anggota..." 
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;"
                            >
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #666;">
                                📊 Filter Tingkat
                            </label>
                            <select 
                                id="tingkatFilter" 
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; background: white;"
                            >
                                <option value="">-- Semua Tingkat --</option>
                                <?php 
                                $tingkat_query = $conn->query("SELECT DISTINCT t.urutan, t.nama_tingkat FROM tingkatan t 
                                                            INNER JOIN anggota a ON t.urutan = a.tingkat_id 
                                                            WHERE a.ranting_saat_ini_id = $id 
                                                            ORDER BY t.urutan");
                                while ($t = $tingkat_query->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $t['urutan']; ?>"><?php echo htmlspecialchars($t['nama_tingkat']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button 
                            onclick="resetFilters()" 
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;"
                        >
                            🔄 Reset
                        </button>
                    </div>
                </div>
                
                <!-- Members Table [BARU] -->
                <div style="overflow-x: auto;">
                    <table id="membersTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">No</th>
                                <th style="width: 200px;">No Anggota</th>
                                <th>Nama Anggota</th>
                                <th style="width: 125px;">Tingkat</th>
                                <th style="width: 150px; text-align: center;">Status</th>
                                <th style="width: 100px; text-align: center;">Reset</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div id="memberNoData" class="no-data" style="display: none; margin-top: 20px;">
                    📭 Tidak ada anggota ditemukan
                </div>
            </div>

            <!-- STYLES UNTUK MEMBER SECTION [BARU] -->
            <style>
                /* Toggle Switch Style */
                .toggle-switch {
                    position: relative;
                    display: inline-block;
                    width: 40px;
                    height: 20px;
                }
                
                .toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                
                .toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 15px;
                }
                
                .toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 12px;
                    width: 12px;
                    left: -4px;
                    bottom: 4px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                
                input:checked + .toggle-slider {
                    background-color: #28a745;
                }
                
                input:checked + .toggle-slider:before {
                    transform: translateX(30px);
                }
                
                .toggle-switch.disabled input {
                    cursor: not-allowed;
                }
                
                .toggle-switch.disabled .toggle-slider {
                    cursor: not-allowed;
                    opacity: 0.6;
                }
                
                /* Status Badge */
                .status-aktif {
                    background: #d4edda;
                    color: #155724;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: 600;
                    margin-left: 8px;
                }
                
                .status-tidak {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: 600;
                    margin-left: 8px;
                }
                
                .saving-status {
                    display: inline-block;
                    margin-left: 8px;
                    font-size: 12px;
                    color: #999;
                }
                
                .btn-detail {
                    padding: 6px 12px;
                    background: #017bfe;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: 600;
                    text-decoration: none;
                    display: inline-block;
                    transition: background 0.3s;
                }
                
                .btn-detail:hover {
                    background: #0056b3;
                }
                
                #membersTable tbody tr.hidden {
                    display: none;
                }
                
                #membersTable tbody tr:hover {
                    background: #f9f9f9;
                }
            </style>

            <!-- JAVASCRIPT UNTUK MEMBER LIST [BARU] -->
            <script>
                // Data anggota dari server
                const membersData = <?php 
                    // Check if is_active column exists
                    $check_column = $conn->query("SHOW COLUMNS FROM anggota LIKE 'is_active'");
                    $has_is_active = $check_column->num_rows > 0;
                    
                    $members_sql = "SELECT a.id, a.no_anggota, a.nama_lengkap, a.tingkat_id, 
                                        t.nama_tingkat, u.id as user_id, u.username as username" . ($has_is_active ? ", a.is_active" : ", 1 as is_active") . "
                                    FROM anggota a
                                    LEFT JOIN tingkatan t ON a.tingkat_id = t.urutan
                                    LEFT JOIN users u ON a.no_anggota = u.no_anggota AND u.role = 'anggota'
                                    WHERE a.ranting_saat_ini_id = $id
                                    ORDER BY a.nama_lengkap";
                    $members_result = $conn->query($members_sql);
                    $members = [];
                    if ($members_result) {
                        while ($row = $members_result->fetch_assoc()) {
                            $row['no_anggota_display'] = formatNoAnggotaDisplay($row['no_anggota'], $pengaturan_nomor);
                            $members[] = $row;
                        }
                    }
                    echo json_encode($members);
                ?>;
                
                const ratingId = <?php echo $id; ?>;
                const canReset = <?php echo ($can_edit) ? 'true' : 'false'; ?>;
                
                // Initialize table
                function renderTable() {
                    const tableBody = document.getElementById('membersTableBody');
                    const noData = document.getElementById('memberNoData');
                    
                    tableBody.innerHTML = '';
                    
                    if (membersData.length === 0) {
                        noData.style.display = 'block';
                        return;
                    }
                    
                    noData.style.display = 'none';
                    let visibleCount = 0;
                    
                    membersData.forEach((member, index) => {
                        const row = document.createElement('tr');
                        const isActive = parseInt(member.is_active) === 1;
                        
                        row.innerHTML = `
                            <td style="text-align: center; color: #999; font-weight: 600;">${index + 1}</td>
                            <td><code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px;">${htmlEscape(member.no_anggota_display)}</code></td>
                            <td><a href="anggota_detail.php?id=${member.id}" class="link-nav">${htmlEscape(member.nama_lengkap)}</a></td>
                            <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; font-weight: 600;">${htmlEscape(member.nama_tingkat || '-')}</span></td>
                            <td style="text-align: center;">
                                <label class="toggle-switch">
                                    <input 
                                        type="checkbox" 
                                        ${isActive ? 'checked' : ''} 
                                        onchange="toggleStatus(${member.id}, this)"
                                    >
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="status-${isActive ? 'aktif' : 'tidak'}" id="status-${member.id}">
                                    ${isActive ? '✓ Aktif' : '✗ Non Aktif'}
                                </span>
                                <span class="saving-status" id="saving-${member.id}"></span>
                            </td>
                            <td style="text-align: center;">
                                ${canReset && member.user_id ? `
                                <form method="POST" onsubmit="return confirm('Reset password akun ${member.username} ke default?')">
                                    <input type="hidden" name="action_type" value="reset_to_default">
                                    <input type="hidden" name="user_id" value="${member.user_id}">
                                    <button type="submit" class="btn-detail" style="background: #f1c40f; color: #333; padding: 4px 8px;">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </form>
                                ` : '-'}
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                        visibleCount++;
                    });
                    
                    // Jika tidak ada data yang visible, tampilkan no data
                    if (visibleCount === 0) {
                        noData.style.display = 'block';
                    }
                }
                
                // Toggle member status dengan autosave
                function toggleStatus(anggotaId, checkbox) {
                    const isActive = checkbox.checked ? 1 : 0;
                    const savingEl = document.getElementById(`saving-${anggotaId}`);
                    const statusEl = document.getElementById(`status-${anggotaId}`);
                    const toggle = checkbox.parentElement;
                    
                    // Show saving status
                    savingEl.textContent = '⏳ Menyimpan...';
                    savingEl.style.color = '#667eea';
                    toggle.classList.add('disabled');
                    checkbox.disabled = true;
                    
                    // Debug log
                    console.log('Toggle status for anggota:', anggotaId, 'to:', isActive);
                    
                    // Construct API path dynamically - lebih reliable
                    const currentPath = window.location.pathname;
                    let apiUrl;
                    
                    // Extract base path dari window.location
                    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/pages/admin/'));
                    
                    // Use absolute path from root
                    apiUrl = '/beladiri_app/api/toggle_member_status.php';
                    
                    console.log('Using API URL:', apiUrl, 'Current path:', currentPath, 'Base path:', basePath);
                    
                    fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            anggota_id: anggotaId,
                            status: isActive
                        })
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP Error: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            // Update status badge
                            statusEl.className = isActive ? 'status-aktif' : 'status-tidak';
                            statusEl.innerHTML = isActive ? '✓ Aktif' : '✗ Non Aktif';
                            savingEl.innerHTML = '✓ Tersimpan';
                            savingEl.style.color = '#28a745';
                            
                            // Clear saving message after 2 seconds
                            setTimeout(() => {
                                savingEl.textContent = '';
                                savingEl.style.color = '#999';
                            }, 2000);
                        } else {
                            // Revert on error
                            checkbox.checked = !isActive;
                            savingEl.innerHTML = '❌ ' + (data.message || 'Gagal menyimpan');
                            savingEl.style.color = '#dc3545';
                            console.error('API Error:', data.message);
                            
                            // Show alert dengan detailed message
                            alert('Error: ' + (data.message || 'Gagal menyimpan status'));
                            
                            // Auto clear after 4 seconds
                            setTimeout(() => {
                                savingEl.textContent = '';
                                savingEl.style.color = '#999';
                            }, 4000);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        checkbox.checked = !isActive;
                        savingEl.innerHTML = '❌ Error: ' + error.message;
                        savingEl.style.color = '#dc3545';
                        
                        // Show alert
                        alert('Error menyimpan: ' + error.message + '\n\nBuka DevTools (F12) untuk detail lebih lanjut');
                        
                        // Auto clear after 4 seconds
                        setTimeout(() => {
                            savingEl.textContent = '';
                            savingEl.style.color = '#999';
                        }, 4000);
                    })
                    .finally(() => {
                        toggle.classList.remove('disabled');
                        checkbox.disabled = false;
                    });
                }
                
                // Filter nama anggota
                function filterMembers() {
                    const searchText = document.getElementById('memberFilter').value.toLowerCase();
                    const tingkatId = document.getElementById('tingkatFilter').value;
                    const rows = document.getElementById('membersTableBody').querySelectorAll('tr');
                    let visibleCount = 0;
                    
                    rows.forEach(row => {
                        const nama = row.cells[2].textContent.toLowerCase();
                        const tingkat = row.cells[3].textContent.trim();
                        
                        // Check nama filter
                        const namaMatch = nama.includes(searchText);
                        
                        // Check tingkat filter
                        const tingkatMatch = tingkatId === '' || row.dataset.tingkatId === tingkatId;
                        
                        if (namaMatch && tingkatMatch) {
                            row.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            row.classList.add('hidden');
                        }
                    });
                    
                    // Show/hide no data message
                    const noData = document.getElementById('memberNoData');
                    if (visibleCount === 0) {
                        noData.style.display = 'block';
                    } else {
                        noData.style.display = 'none';
                    }
                }
                
                // Reset filters
                function resetFilters() {
                    document.getElementById('memberFilter').value = '';
                    document.getElementById('tingkatFilter').value = '';
                    filterMembers();
                }
                
                // HTML escape function
                function htmlEscape(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Set tingkat_id as data attribute untuk filter
                function enhanceTableWithData() {
                    const rows = document.getElementById('membersTableBody').querySelectorAll('tr');
                    const memberIndex = {};
                    
                    membersData.forEach(member => {
                        memberIndex[member.id] = member;
                    });
                    
                    rows.forEach(row => {
                        const nama = row.cells[2].textContent;
                        // Find corresponding member by nama to get tingkat_id
                        membersData.forEach(member => {
                            if (member.nama_lengkap === nama) {
                                row.dataset.tingkatId = member.tingkat_id || '';
                            }
                        });
                    });
                }
                
                // Event listeners
                document.getElementById('memberFilter').addEventListener('keyup', filterMembers);
                document.getElementById('tingkatFilter').addEventListener('change', filterMembers);
                
                // Initialize - run when DOM is ready
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('DOM Content Loaded - Initializing member table...');
                    console.log('Members data count:', membersData.length);
                    renderTable();
                    enhanceTableWithData();
                });
                
                // Also run immediately in case DOMContentLoaded already fired
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    console.log('Document already ready - Initializing member table...');
                    console.log('Members data count:', membersData.length);
                    renderTable();
                    enhanceTableWithData();
                }
            </script>    
        </div>
    </div>
</body>
</html>