<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];

$sql = "SELECT a.*, t.nama_tingkat, t.urutan as urutan_tingkat, r.nama_ranting 
        FROM anggota a 
        LEFT JOIN tingkatan t ON a.tingkat_id = t.id 
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id 
        WHERE a.id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Anggota tidak ditemukan!");
}

$anggota = $result->fetch_assoc();

// Ambil riwayat UKT
$ukt_sql = "SELECT up.*, u.tanggal_pelaksanaan, u.lokasi, 
            t1.nama_tingkat as tingkat_dari, t2.nama_tingkat as tingkat_ke
            FROM ukt_peserta up
            JOIN ukt u ON up.ukt_id = u.id
            LEFT JOIN tingkatan t1 ON up.tingkat_dari_id = t1.id
            LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
            WHERE up.anggota_id = $id
            ORDER BY u.tanggal_pelaksanaan DESC";

$ukt_result = $conn->query($ukt_sql);

// Ambil UKT Terakhir yang LULUS
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

// Fallback ke kolom ukt_terakhir jika data UKT kosong (dari input manual saat registrasi)
if ($ukt_terakhir_date === null && !empty($anggota['ukt_terakhir'])) {
    $ukt_terakhir_date = $anggota['ukt_terakhir'];
}

// Ambil data kerohanian
$kerohanian_sql = "SELECT * FROM kerohanian WHERE anggota_id = $id ORDER BY tanggal_pembukaan DESC";
$kerohanian_result = $conn->query($kerohanian_sql);

// Hitung umur
$birthDate = new DateTime($anggota['tanggal_lahir']);
$today = new DateTime("today");
$age = $birthDate->diff($today)->y;

// Cari foto dengan berbagai kemungkinan nama file
function findPhotoFile($upload_dir, $no_anggota, $nama_lengkap) {
    // Sanitasi nama lengkap
    $nama_clean = preg_replace("/[^a-z0-9 -]/i", "", $nama_lengkap);
    $nama_clean = str_replace(" ", "_", $nama_clean);
    
    // Cari file dengan format NoAnggota_Nama.ext
    $pattern = $upload_dir . preg_quote($no_anggota) . '_' . preg_quote($nama_clean) . '.*';
    
    // Gunakan glob untuk cari file
    $files = glob($pattern);
    
    if (!empty($files)) {
        return basename($files[0]); // Return nama file yang ditemukan
    }
    
    return null; // Tidak ada foto ditemukan
}

// Get foto path yang benar
$upload_dir = '../../uploads/foto_anggota/';
$foto_filename = findPhotoFile($upload_dir, $anggota['no_anggota'], $anggota['nama_lengkap']);
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
        
        .badge-murid {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-pelatih {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-pelatih_unit {
            background: #fff3e0;
            color: #e65100;
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
    <div class="navbar">
        <h2>📋 Detail Anggota</h2>
        <a href="anggota.php">← Kembali ke Daftar</a>
    </div>
    
    <div class="container">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-photo">
                <?php if ($foto_path): ?>
                    <img src="<?php echo $foto_path; ?>" alt="Foto Profil">
                <?php else: ?>
                    <div class="no-photo">📷</div>
                <?php endif; ?>
                <div class="badge-status badge-<?php echo $anggota['jenis_anggota']; ?>">
                    <?php echo strtoupper(str_replace('_', ' ', $anggota['jenis_anggota'])); ?>
                </div>
            </div>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($anggota['nama_lengkap']); ?></h2>
                <div class="profile-subtitle">
                    <strong>No Anggota:</strong> <?php echo $anggota['no_anggota']; ?>
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
                        <div class="label">Tingkat Saat Ini</div>
                        <div class="value highlight"><?php echo $anggota['nama_tingkat']; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="label">Unit/Ranting Saat Ini</div>
                        <div class="value"><?php echo $anggota['nama_ranting'] ?? '-'; ?></div>
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
                            if ($ukt_terakhir_date) {
                                echo date('d M Y', strtotime($ukt_terakhir_date));
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="button-group">
                    <a href="anggota_edit.php?id=<?php echo $anggota['id']; ?>" class="btn btn-warning">✏️ Edit Data</a>
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
        
        <!-- Riwayat UKT -->
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
                        <td><?php echo $row['nilai'] ?? '-'; ?></td>
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
        
        <!-- Data Kerohanian -->
        <div class="section">
            <h3>🙏 Riwayat Pembukaan Kerohanian</h3>
            
            <?php if ($kerohanian_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal Pembukaan</th>
                        <th>Lokasi</th>
                        <th>Pembuka</th>
                        <th>Unit/Ranting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $kerohanian_result->fetch_assoc()): 
                        $ranting_name = '';
                        if ($row['ranting_id']) {
                            $r_result = $conn->query("SELECT nama_ranting FROM ranting WHERE id = " . $row['ranting_id']);
                            if ($r_result->num_rows > 0) {
                                $r_data = $r_result->fetch_assoc();
                                $ranting_name = $r_data['nama_ranting'];
                            }
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($row['tanggal_pembukaan'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                        <td><?php echo htmlspecialchars($row['pembuka_nama']); ?></td>
                        <td><?php echo $ranting_name; ?></td>
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