<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];

$sql = "SELECT r.*, p.nama_pengurus FROM ranting r 
        LEFT JOIN pengurus p ON r.pengurus_kota_id = p.id
        WHERE r.id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Unit/Ranting tidak ditemukan!");
}

$ranting = $result->fetch_assoc();

// Ambil daftar anggota di ranting ini
$anggota_sql = "SELECT COUNT(*) as count FROM anggota WHERE ranting_saat_ini_id = $id";
$anggota_count = $conn->query($anggota_sql)->fetch_assoc();

// Ambil jadwal latihan
$jadwal_sql = "SELECT * FROM jadwal_latihan WHERE ranting_id = $id ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')";
$jadwal_result = $conn->query($jadwal_sql);

// Cari file SK - HANYA YANG TERAKHIR
$upload_dir = '../../uploads/sk_pembentukan/';
$sk_file = null;
$sk_files = [];

if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        // Cari file yang cocok dengan pattern SK-ranting-pengurus-XX.pdf
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
    // Extract nomor revisi dari format: SK-name-pengurus-XX.ext
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
        }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-warning { background: #ffc107; color: black; }
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
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📋 Detail Unit/Ranting</h2>
        <a href="ranting.php" style="color: white;">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="info-card">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <h1 style="color: #333; margin-bottom: 10px;"><?php echo htmlspecialchars($ranting['nama_ranting']); ?></h1>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $anggota_count['count']; ?></div>
                        <div class="stat-label">Anggota Aktif</div>
                    </div>
                </div>
                <span class="badge badge-<?php echo $ranting['jenis']; ?>" style="margin-top: 10px;">
                    <?php echo strtoupper($ranting['jenis']); ?>
                </span>
            </div>
        </div>
        
        <div class="info-card">
            <h3>📋 Informasi Dasar</h3>
            
            <div class="info-row">
                <div class="label">Jenis</div>
                <div class="value"><?php echo ucfirst($ranting['jenis']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Tanggal SK</div>
                <div class="value"><?php echo date('d M Y', strtotime($ranting['tanggal_sk_pembentukan'])); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Alamat</div>
                <div class="value"><?php echo nl2br(htmlspecialchars($ranting['alamat'])); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Pengurus Kota</div>
                <div class="value"><?php echo htmlspecialchars($ranting['nama_pengurus'] ?? '-'); ?></div>
            </div>
        </div>

        <!-- SK PEMBENTUKAN SECTION - HANYA SK TERAKHIR -->
        <div class="info-card">
            <h3>📄 SK Pembentukan</h3>
            
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
                <div class="label">No Kontak</div>
                <div class="value"><?php echo htmlspecialchars($ranting['no_kontak'] ?? '-'); ?></div>
            </div>
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
        
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <div class="button-group">
            <a href="ranting_edit.php?id=<?php echo $id; ?>" class="btn btn-warning">✏️ Edit Data</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>