<?php
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
    $_SESSION['ranting_id'] ?? null
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

$sql = "SELECT a.*, t.nama_tingkat, t.urutan as urutan_tingkat, r.nama_ranting, ra.nama_ranting as nama_ranting_awal,
        n.nama as nama_negara, p.nama as nama_provinsi, k.nama as nama_kota
        FROM anggota a 
        LEFT JOIN tingkatan t ON a.tingkat_id = t.id 
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id 
        LEFT JOIN ranting ra ON a.ranting_awal_id = ra.id 
        LEFT JOIN kota k ON r.kota_id = k.id
        LEFT JOIN provinsi p ON k.provinsi_id = p.id
        LEFT JOIN negara n ON p.negara_id = n.id
        WHERE a.id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Anggota tidak ditemukan!");
}

$anggota = $result->fetch_assoc();

// Ambil riwayat UKT [LAMA - TETAP SAMA]
$ukt_sql = "SELECT up.*, u.tanggal_pelaksanaan, u.lokasi, 
            t1.nama_tingkat as tingkat_dari, t2.nama_tingkat as tingkat_ke
            FROM ukt_peserta up
            JOIN ukt u ON up.ukt_id = u.id
            LEFT JOIN tingkatan t1 ON up.tingkat_dari_id = t1.id
            LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
            WHERE up.anggota_id = $id
            ORDER BY u.tanggal_pelaksanaan DESC";

$ukt_result = $conn->query($ukt_sql);

// Ambil UKT Terakhir yang LULUS [LAMA - TETAP SAMA]
$ukt_terakhir_query = $conn->query(
    "SELECT u.tanggal_pelaksanaan 
     FROM ukt_peserta up
     JOIN ukt u ON up.ukt_id = u.id
     WHERE up.anggota_id = $id AND up.status = 'lulus'
     ORDER BY u.tanggal_pelaksanaan DESC
     LIMIT 1"
);

$ukt_terakhir_date = null;
if ($ukt_terakhir_query->num_rows > 0) {
    $data = $ukt_terakhir_query->fetch_assoc();
    $ukt_terakhir_date = $data['tanggal_pelaksanaan'];
}

// Fallback ke kolom ukt_terakhir jika data UKT kosong [LAMA - TETAP SAMA]
if ($ukt_terakhir_date === null && !empty($anggota['ukt_terakhir'])) {
    $ukt_terakhir_date = $anggota['ukt_terakhir'];
}

// Helper function untuk format tanggal yang aman
function formatDateDisplay($date) {
    if (empty($date) || $date === '0000-00-00' || $date === 'NULL') {
        return null;
    }
    // Coba parse sebagai yyyy-mm-dd (MySQL format)
    $timestamp = strtotime($date);
    if ($timestamp !== false && $timestamp > 0) {
        return date('d M Y', $timestamp);
    }
    // Coba parse sebagai dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        $timestamp = mktime(0, 0, 0, $matches[2], $matches[1], $matches[3]);
        if ($timestamp !== false) {
            return date('d M Y', $timestamp);
        }
    }
    // Coba parse sebagai year saja
    if (preg_match('/^(\d{4})$/', $date, $matches)) {
        return $matches[1];
    }
    return null;
}

// Ambil data kerohanian
$kerohanian_sql = "SELECT k.*, t_pembuka.nama_tingkat as tingkat_pembuka_nama 
                    FROM kerohanian k 
                    LEFT JOIN tingkatan t_pembuka ON k.tingkat_pembuka_id = t_pembuka.id 
                    WHERE k.anggota_id = $id 
                    ORDER BY k.tanggal_pembukaan DESC";
$kerohanian_result = $conn->query($kerohanian_sql);

// Ambil data prestasi [BARU]
$prestasi_sql = "SELECT * FROM prestasi WHERE anggota_id = $id ORDER BY tanggal_pelaksanaan DESC";
$prestasi_result = $conn->query($prestasi_sql);

// Ambil data sertifikat UKT [BARU - SERTIFIKAT]
$sertifikat_sql = "SELECT up.*, u.tanggal_pelaksanaan, t2.nama_tingkat as tingkat_ke
                   FROM ukt_peserta up
                   JOIN ukt u ON up.ukt_id = u.id
                   LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
                   WHERE up.anggota_id = $id AND up.status = 'lulus' AND up.sertifikat_path IS NOT NULL
                   ORDER BY u.tanggal_pelaksanaan DESC";
$sertifikat_result = $conn->query($sertifikat_sql);

// Hitung umur [LAMA - TETAP SAMA]
$birthDate = new DateTime($anggota['tanggal_lahir']);
$today = new DateTime("today");
$age = $birthDate->diff($today)->y;

// Cari foto dengan berbagai kemungkinan nama file [BARU - support format lama dan baru]
function findPhotoFile($upload_dir, $no_anggota, $nama_lengkap, $ranting_saat_ini_id = null, $conn = null) {
    // Sanitasi nama lengkap
    $nama_clean = preg_replace("/[^a-z0-9 -]/i", "", $nama_lengkap);
    $nama_clean = str_replace(" ", "_", $nama_clean);
    
    // Pertama, coba cari dengan format baru: ranting_nama_anggota.ext
    if ($ranting_saat_ini_id && $conn) {
        $ranting_id = (int)$ranting_saat_ini_id;
        $rantingQuery = $conn->query("SELECT nama_ranting FROM ranting WHERE id = $ranting_id");
        if ($ranting = $rantingQuery->fetch_assoc()) {
            $ranting_clean = preg_replace("/[^a-z0-9 -]/i", "", $ranting['nama_ranting']);
            $ranting_clean = str_replace(" ", "_", $ranting_clean);
            $new_pattern = $upload_dir . preg_quote($ranting_clean) . '_' . preg_quote($nama_clean) . '.*';
            $new_files = glob($new_pattern);
            if (!empty($new_files)) {
                return basename($new_files[0]); // Return nama file yang ditemukan
            }
        }
    }
    
    // Kemudian, coba cari dengan format lama: NoAnggota_Nama.ext
    $pattern = $upload_dir . preg_quote($no_anggota) . '_' . preg_quote($nama_clean) . '.*';
    $files = glob($pattern);
    
    if (!empty($files)) {
        return basename($files[0]); // Return nama file yang ditemukan
    }
    
    return null; // Tidak ada foto ditemukan
}

// Get foto path yang benar [BARU - support format baru dan lama]
$upload_dir = '../../uploads/foto_anggota/';
$foto_filename = findPhotoFile($upload_dir, $anggota['no_anggota'], $anggota['nama_lengkap'], $anggota['ranting_saat_ini_id'], $conn);
$foto_path = null;

if ($foto_filename && file_exists($upload_dir . $foto_filename)) {
    $foto_path = $upload_dir . $foto_filename;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Anggota - Sistem Beladiri</title>
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
        
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 40px;
        }
        
        .profile-photo {
            text-align: center;
        }
        
        .profile-photo img {
            width: 250px;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 15px;
        }
        
        .no-photo {
            width: 250px;
            height: 300px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .badge-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .profile-info h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .profile-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 20px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .label {
            color: #666;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .value {
            color: #333;
            font-size: 15px;
            font-weight: 500;
        }
        
        .value.highlight {
            color: #667eea;
            font-weight: 700;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            font-size: 18px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        .status-lulus {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-tidak_lulus {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .status-peserta {
            color: #3498db;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d68910;
            transform: translateY(-2px);
        }
        
        .button-group {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .ukt-info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .ukt-info-box strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php renderNavbar('👥 Manajemen Anggota'); ?>
    
    <div class="container">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-photo">
                <?php if ($foto_path): ?>
                    <img src="<?php echo $foto_path; ?>" alt="Foto Profil">
                <?php else: ?>
                    <div class="no-photo">📷</div>
                <?php endif; ?>
                <div class="badge-status" style="background:#0d6efd;color:#fff;font-weight:bold;">
                    <?php 
                    // Tampilkan nama jenis dari tabel jenis_anggota berdasarkan ID
                    $jenis_id = isset($anggota['jenis_anggota']) ? (int)$anggota['jenis_anggota'] : 0;
                    $jenis_check = $conn->query("SELECT nama_jenis FROM jenis_anggota WHERE id = " . $jenis_id);
                    $jenis_nama = 'Tidak Diketahui';
                    if ($jenis_check && $jenis_check->num_rows > 0) {
                        $jenis_data = $jenis_check->fetch_assoc();
                        $jenis_nama = $jenis_data['nama_jenis'];
                    }
                    echo htmlspecialchars(strtoupper(str_replace('_', ' ', $jenis_nama))); 
                    ?>
                </div>
            </div>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($anggota['nama_lengkap']); ?></h2>
                <div class="profile-subtitle">
                    <strong>No Anggota  :</strong> <?php echo formatNoAnggotaDisplay($anggota['no_anggota'], $pengaturan_nomor); ?>
                </div>
                <div class="profile-subtitle" style="margin-top: 5px;">
                    <strong>Regional    :</strong> <strong><?php echo htmlspecialchars(($anggota['nama_negara'] ?? '-') . ' / ' . ($anggota['nama_provinsi'] ?? '-') . ' / ' . ($anggota['nama_kota'] ?? '-')); ?></strong>
                </div>
                
                <div class="info-section">
                    <div class="info-row">
                        <div class="label">Jenis Kelamin</div>
                        <div class="value"><?php echo $anggota['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Tempat Lahir</div>
                        <div class="value"><?php echo htmlspecialchars($anggota['tempat_lahir']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Tanggal Lahir</div>
                        <div class="value"><?php echo date('d M Y', strtotime($anggota['tanggal_lahir'])); ?> (<?php echo $age; ?> tahun)</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Tahun Bergabung</div>
                        <div class="value"><?php echo $anggota['tahun_bergabung'] ?? '-'; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">No. Handphone</div>
                        <div class="value"><?php echo $anggota['no_handphone'] ?? '-'; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Tingkat Saat Ini</div>
                        <div class="value highlight"><?php echo $anggota['nama_tingkat']; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Unit/Ranting Awal</div>
                        <div class="value">
                            <?php if (!empty($anggota['nama_ranting_awal'])): ?>
                                <a href="ranting_detail.php?id=<?php echo $anggota['ranting_awal_id']; ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                    <?php echo htmlspecialchars($anggota['nama_ranting_awal']); ?> ↗
                                </a>
                            <?php elseif (!empty($anggota['ranting_awal_manual'])): ?>
                                <?php echo htmlspecialchars($anggota['ranting_awal_manual']); ?>
                            <?php else: echo '-';
                            endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Unit/Ranting Saat Ini</div>
                        <div class="value">
                            <?php if (!empty($anggota['nama_ranting'])): ?>
                                <a href="ranting_detail.php?id=<?php echo $anggota['ranting_saat_ini_id']; ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                    <?php echo htmlspecialchars($anggota['nama_ranting']); ?> ↗
                                </a>
                            <?php else: echo '-';
                            endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Status Kerohanian</div>
                        <div class="value">
                            <?php if ($anggota['status_kerohanian'] == 'sudah'): ?>
                                <span style="color: #27ae60;">✓ Sudah (<?php echo date('d M Y', strtotime($anggota['tanggal_pembukaan_kerohanian'])); ?>)</span>
                            <?php else: ?>
                                <span style="color: #e74c3c;">✗ Belum</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="label">UKT Terakhir</div>
                        <div class="value">
                            <?php 
                            $ukt_display = formatDateDisplay($ukt_terakhir_date);
                            if ($ukt_display) {
                                echo htmlspecialchars($ukt_display);
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="label">Status Anggota</div>
                        <div class="value">
                            <?php 
                            // Check if is_active column exists, default to 1 if not set
                            $is_active = isset($anggota['is_active']) ? (int)$anggota['is_active'] : 1;
                            
                            if ($is_active === 1): 
                            ?>
                                <span style="color: #27ae60; font-weight: 600; font-size: 15px;">✓ Aktif</span>
                            <?php else: ?>
                                <span style="color: #e74c3c; font-weight: 600; font-size: 15px;">✗ Non Aktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="button-group">
                    <a href="anggota_edit.php?id=<?php echo $anggota['id']; ?>" class="btn btn-warning">✏️ Edit Data</a>
                    <button onclick="window.print()" class="btn btn-warning" style="background: #6c757d;">
                        🖨️ Print Detail
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informasi UKT Terakhir -->
        <?php if ($ukt_terakhir_date): ?>
        <div class="section">
            <div class="ukt-info-box">
                <strong>ℹ️ Catatan:</strong> "UKT Terakhir" menampilkan tanggal pelaksanaan UKT terakhir yang <strong>LULUS</strong>. 
                Jika tidak ada data UKT lulus, maka akan menggunakan data UKT Terakhir dari input manual saat registrasi.
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistik UKT -->
        <?php
        $ukt_result->data_seek(0);
        $total_ukt = $ukt_result->num_rows;
        $lulus_count = 0;
        $tidak_lulus_count = 0;
        
        $ukt_result->data_seek(0);
        while ($row = $ukt_result->fetch_assoc()) {
            if ($row['status'] == 'lulus') $lulus_count++;
            if ($row['status'] == 'tidak_lulus') $tidak_lulus_count++;
        }
        ?>
        
        <!-- 1. Riwayat Ujian Kenaikan Tingkat (UKT) -->
        <div class="section">
            <h3>🏆 Riwayat Ujian Kenaikan Tingkat (UKT)</h3>
            
            <?php if ($total_ukt > 0): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_ukt; ?></div>
                    <div class="stat-label">Total UKT Diikuti</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #27ae60;"><?php echo $lulus_count; ?></div>
                    <div class="stat-label">Lulus</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #e74c3c;"><?php echo $tidak_lulus_count; ?></div>
                    <div class="stat-label">Tidak Lulus</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Tanggal Pelaksanaan</th>
                        <th>Dari Tingkat</th>
                        <th>Ke Tingkat</th>
                        <th>Nilai</th>
                        <th>Status</th>
                        <th>Lokasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ukt_result->data_seek(0);
                    while ($row = $ukt_result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($row['tanggal_pelaksanaan'])); ?></strong></td>
                        <td><?php echo $row['tingkat_dari'] ?? '-'; ?></td>
                        <td><?php echo $row['tingkat_ke'] ?? '-'; ?></td>
                        <td>
                            <?php if (!empty($row['id'])): ?>
                                <a href="ukt_detail_peserta.php?id=<?php echo $row['id']; ?>&ukt_id=<?php echo $row['ukt_id']; ?>" title="Lihat Detail Nilai">
                                    <?php echo isset($row['rata_rata']) ? number_format($row['rata_rata'], 2) : '-'; ?>
                                </a>
                            <?php else: ?>
                                <?php echo isset($row['rata_rata']) ? number_format($row['rata_rata'], 2) : '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($row['status'] == 'lulus') {
                                echo '<span class="status-lulus">✓ LULUS</span>';
                            } else if ($row['status'] == 'tidak_lulus') {
                                echo '<span class="status-tidak_lulus">✗ TIDAK LULUS</span>';
                            } else {
                                echo '<span class="status-peserta">• PESERTA</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo $row['lokasi'] ?? '-'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <p>Belum ada riwayat UKT</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 2. Sertifikat Kelulusan UKT -->
        <div class="section">
            <h3>📜 Sertifikat Kelulusan UKT</h3>
            
            <?php if ($sertifikat_result && $sertifikat_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal UKT</th>
                        <th>Tingkat Kenaikan</th>
                        <th>Nama File Sertifikat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $sertifikat_result->fetch_assoc()): 
                        $sert_path = '../../uploads/sertifikat_ukt/' . $row['sertifikat_path'];
                        $sert_exists = file_exists($sert_path);
                    ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($row['tanggal_pelaksanaan'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['tingkat_ke'] ?? '-'); ?></td>
                        <td>
                            <span style="font-family: monospace; font-size: 12px; background: #f0f0f0; padding: 4px 8px; border-radius: 3px;">
                                <?php echo htmlspecialchars($row['sertifikat_path']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sert_exists): ?>
                                <a href="<?php echo $sert_path; ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 12px;" target="_blank" download>
                                    ⬇️ Download
                                </a>
                            <?php else: ?>
                                <span style="color: #e74c3c; font-size: 12px;">❌ File tidak ditemukan</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📜</div>
                <p>Belum ada sertifikat kelulusan UKT</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 3. Prestasi yang Diraih -->
        <div class="section">
            <h3>🏆 Prestasi yang Diraih</h3>
            
            <?php if ($prestasi_result && $prestasi_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Tanggal Pelaksanaan</th>
                        <th>Penyelenggara</th>
                        <th>Kategori</th>
                        <th>Prestasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $prestasi_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['event_name']); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($row['tanggal_pelaksanaan'])); ?></td>
                        <td><?php echo htmlspecialchars($row['penyelenggara'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['kategori'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['prestasi'] ?? '-'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🏆</div>
                <p>Belum ada prestasi yang terdaftar</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 4. Riwayat Pembukaan Kerohanian -->
        <div class="section">
            <h3>🙏 Riwayat Pembukaan Kerohanian</h3>
            
            <?php if ($kerohanian_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal Pembukaan</th>
                        <th>Lokasi</th>
                        <th>Pembuka</th>
                        <th>Tingkat Pembuka</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $kerohanian_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><a href="kerohanian.php" title="Lihat Semua Kerohanian"><?php echo date('d M Y', strtotime($row['tanggal_pembukaan'])); ?></a></strong></td>
                        <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                        <td><?php echo htmlspecialchars($row['pembuka_nama']); ?></td>
                        <td><?php echo htmlspecialchars($row['tingkat_pembuka_nama'] ?? '-'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🙏</div>
                <p>Belum ada pembukaan kerohanian</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>