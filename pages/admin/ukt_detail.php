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

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$id = (int)$_GET['id'];

// Ambil data UKT
$ukt_result = $conn->query("SELECT u.*, p.nama_pengurus as nama_penyelenggara 
                            FROM ukt u 
                            LEFT JOIN pengurus p ON u.penyelenggara_id = p.id 
                            WHERE u.id = $id");
if ($ukt_result->num_rows == 0) {
    die("UKT tidak ditemukan!");
}

$ukt = $ukt_result->fetch_assoc();

// Ambil data peserta UKT
$peserta_sql = "SELECT up.*, a.nama_lengkap, a.no_anggota, t1.nama_tingkat as tingkat_dari, t2.nama_tingkat as tingkat_ke
                FROM ukt_peserta up
                JOIN anggota a ON up.anggota_id = a.id
                LEFT JOIN tingkatan t1 ON up.tingkat_dari_id = t1.id
                LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
                WHERE up.ukt_id = $id
                ORDER BY a.nama_lengkap";

$peserta_result = $conn->query($peserta_sql);
$total_peserta = $peserta_result->num_rows;

// Hitung statistik
$stat_lulus = $conn->query("SELECT COUNT(*) as count FROM ukt_peserta WHERE ukt_id = $id AND status = 'lulus'")->fetch_assoc();
$stat_tidak = $conn->query("SELECT COUNT(*) as count FROM ukt_peserta WHERE ukt_id = $id AND status = 'tidak_lulus'")->fetch_assoc();

$is_readonly = $_SESSION['role'] == 'user';

// Handle print mode with proper validation and error handling
$print_mode = filter_input(INPUT_GET, 'print', FILTER_VALIDATE_BOOLEAN);

if ($print_mode) {
    // Hitung statistik per tingkat (berdasarkan tingkat dari)
    $stat_tingkat = [];
    $tingkat_list = ['Dasar I', 'Dasar II', 'Calon Keluarga', 'Putih', 'Putih Hijau', 'Hijau', 'Hijau Biru', 'Biru', 'Biru Merah', 'Merah', 'Merah Kuning', 'Kuning', 'Pendekar'];
    foreach ($tingkat_list as $tingkat) {
        $count = $conn->query("SELECT COUNT(*) as count FROM ukt_peserta up JOIN anggota a ON up.anggota_id = a.id JOIN tingkatan t ON up.tingkat_dari_id = t.id WHERE up.ukt_id = $id AND t.nama_tingkat = '" . $conn->real_escape_string($tingkat) . "'")->fetch_assoc();
        $stat_tingkat[$tingkat] = $count['count'];
    }
    
    // Set proper headers for print
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print - Daftar Peserta UKT</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 15px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 5px 0; font-size: 20px; }
        .header p { color: #666; font-size: 12px; margin: 2px 0; }
        .ukt-info { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .ukt-info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .ukt-info-row:last-child { margin-bottom: 0; }
        .ukt-info-label { font-weight: 600; color: #333; }
        .ukt-info-value { color: #666; }
        .ukt-info-2col { display: flex; gap: 30px; }
        .ukt-info-col { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; font-weight: bold; }
        .stat-summary { margin-top: 20px; }
        .stat-tingkat { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 15px; }
        .stat-col { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .stat-col h4 { margin-bottom: 10px; font-size: 12px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .stat-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 5px; }
        .print-info { text-align: right; font-size: 10px; color: #666; margin-top: 20px; }
        @page { size: A4 landscape; margin: 10mm; }
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">
        <button onclick="window.history.back()" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
            ← Kembali
        </button>
    </div>

    <div class="header">
        <h1>Daftar Peserta Ujian Kenaikan Tingkat</h1>
    </div>
    
    <div class="ukt-info">
        <div class="ukt-info-2col">
            <div class="ukt-info-col">
                <div class="ukt-info-row">
                    <span class="ukt-info-label">Penyelenggara: </span>
                    <span class="ukt-info-value"><?php echo htmlspecialchars($ukt['nama_penyelenggara'] ?? '-'); ?></span>
                </div>
                <div class="ukt-info-row">
                    <span class="ukt-info-label">Tanggal Ujian:</span>
                    <span class="ukt-info-value"><?php echo date('d M Y', strtotime($ukt['tanggal'] ?? 'now')); ?></span>
                </div>
                <div class="ukt-info-row">
                    <span class="ukt-info-label">Lokasi:</span>
                    <span class="ukt-info-value"><?php echo htmlspecialchars($ukt['lokasi'] ?? '-'); ?></span>
                </div>
            </div>
            <div class="ukt-info-col">
                <div class="ukt-info-row">
                    <span class="ukt-info-label">Jumlah Peserta:</span>
                    <span class="ukt-info-value"><?php echo $total_peserta; ?></span>
                </div>
                <div class="ukt-info-row">
                    <span class="ukt-info-label">Lulus:</span>
                    <span class="ukt-info-value" style="color: #27ae60;"><?php echo $stat_lulus['count']; ?></span>
                </div>
                <div class="ukt-info-row">
                    <span class="ukt-info-label">Tidak Lulus:</span>
                    <span class="ukt-info-value" style="color: #e74c3c;"><?php echo $stat_tidak['count']; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 12%;">No Anggota</th>
                <th style="width: 20%;">Nama Anggota</th>
                <th style="width: 12%;">Dari Tingkat</th>
                <th style="width: 12%;">Ke Tingkat</th>
                <th style="width: 10%;">Nilai Rata</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $peserta_result->data_seek(0); // Reset pointer
            $no = 1;
            while ($row = $peserta_result->fetch_assoc()):
                $rata = $row['rata_rata'] ? number_format($row['rata_rata'], 2) : '-';
                $status_class = $row['status'] == 'lulus' ? 'status-lulus' : 'status-tidak';
                $status_text = $row['status'] == 'lulus' ? 'Lulus' : ($row['status'] == 'tidak_lulus' ? 'Tidak Lulus' : '-');
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['no_anggota']); ?></td>
                <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                <td><?php echo htmlspecialchars($row['tingkat_dari'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['tingkat_ke'] ?? '-'); ?></td>
                <td><?php echo $rata; ?></td>
                <td class="<?php echo $status_class; ?>"><?php echo $status_text; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div class="stat-summary" style="margin-top: 20px; font-size: 10px;">
        <h4 style="font-size: 10px; margin-bottom: 8px;">Ringkasan Peserta Berdasarkan Tingkat:</h4>
        <div class="stat-tingkat" style="display: flex; gap: 20px; font-size: 10px;">
            <div style="flex: 1;">
                <span>DI: <?php echo $stat_tingkat['Dasar I'] ?? 0; ?></span> |
                <span>DII: <?php echo $stat_tingkat['Dasar II'] ?? 0; ?></span> |
                <span>Cakel: <?php echo $stat_tingkat['Calon Keluarga'] ?? 0; ?></span> |
                <span>P: <?php echo $stat_tingkat['Putih'] ?? 0; ?></span> |
                <span>PH: <?php echo $stat_tingkat['Putih Hijau'] ?? 0; ?></span> |
                <span>H: <?php echo $stat_tingkat['Hijau'] ?? 0; ?></span> |
                <span>HB: <?php echo $stat_tingkat['Hijau Biru'] ?? 0; ?></span> |
                <span>B: <?php echo $stat_tingkat['Biru'] ?? 0; ?></span> |
                <span>BM: <?php echo $stat_tingkat['Biru Merah'] ?? 0; ?></span> |
                <span>M: <?php echo $stat_tingkat['Merah'] ?? 0; ?></span> |
                <span>MK: <?php echo $stat_tingkat['Merah Kuning'] ?? 0; ?></span> |
                <span>K: <?php echo $stat_tingkat['Kuning'] ?? 0; ?></span> |
                <span>PKE: <?php echo $stat_tingkat['Pendekar'] ?? 0; ?></span>
            </div>
        </div>
    </div>
    
    <div class="print-info">
        <p>Dicetak dari Sistem Manajemen Perisai Diri</p>
        <p><?php echo date('d/m/Y H:i'); ?></p>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
    <?php
    http_response_code(200);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail UKT - Sistem Beladiri</title>
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
        
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number { font-size: 28px; font-weight: 700; color: #667eea; }
        .stat-label { color: #666; margin-top: 10px; }
        
        table { width: 100%; border-collapse: collapse; }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
        }

        td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 13px; }

        tr:hover { background: #f9f9f9; }
        
        th:nth-child(2), td:nth-child(2) {
            text-align: left;
        }

        .status-lulus { color: #27ae60; font-weight: 600; }
        .status-tidak { color: #e74c3c; font-weight: 600; }
        
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            margin-right: 8px;
            margin-bottom: 10px;
        }
        
        .btn-disabled {
            background: #d0d0d0 !important;
            color: #888 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
            pointer-events: none;
        }

        .btn-primary { background: #667eea; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-print { background: #6c757d; color: white; }
        .btn-lihat { background: #17a2b8; color: white; font-size: 12px; padding: 8px 12px; }
        
        .btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        .sertifikat-status {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .sertifikat-ada {
            background: #d4edda;
            color: #155724;
        }
        
        .sertifikat-belum {
            background: #f8d7da;
            color: #721c24;
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
        
        /* Center align untuk kolom */
        th:nth-child(1), td:nth-child(1),    /* No Anggota */
        th:nth-child(3), td:nth-child(3),    /* Dari Tingkat */
        th:nth-child(4), td:nth-child(4),    /* Ke Tingkat */
        th:nth-child(5), td:nth-child(5),    /* Nilai */
        th:nth-child(6), td:nth-child(6),    /* Status */
        th:nth-child(7), td:nth-child(7),    /* Sertifikat */
        th:nth-child(8), td:nth-child(8){    /* Aksi*/
            text-align: center;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
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
    <?php renderNavbar('📋 Detail Pelaksanaan UKT'); ?>
    
    <div class="container">
        <div class="info-card">
            <h3 style="color: #333; margin-bottom: 20px;">Informasi UKT</h3>
            
            <div class="info-row">
                <div class="label">Tanggal Pelaksanaan</div>
                <div class="value"><strong><?php echo date('d M Y', strtotime($ukt['tanggal_pelaksanaan'])); ?></strong></div>
            </div>
            
            <div class="info-row">
                <div class="label">Lokasi</div>
                <div class="value"><?php echo htmlspecialchars($ukt['lokasi']); ?></div>
            </div>
            
            <?php if ($ukt['nama_penyelenggara']): ?>
            <div class="info-row">
                <div class="label">Penyelenggara</div>
                <div class="value"><?php echo htmlspecialchars($ukt['nama_penyelenggara']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <div class="label">Dibuat Pada</div>
                <div class="value"><?php echo date('d M Y H:i', strtotime($ukt['created_at'])); ?></div>
            </div>
        </div>
        
        <div class="section">
            <div class="ukt-info-box">
                <strong>ℹ️ Catatan:</strong> <?php echo htmlspecialchars($ukt['catatan'] ?? '-'); ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_peserta; ?></div>
                <div class="stat-label">Total Peserta</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #27ae60;"><?php echo $stat_lulus['count']; ?></div>
                <div class="stat-label">Lulus</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #e74c3c;"><?php echo $stat_tidak['count']; ?></div>
                <div class="stat-label">Tidak Lulus</div>
            </div>
        </div>
        
        <div class="info-card">
            <h3 style="color: #333; margin-bottom: 20px;">Daftar Peserta UKT</h3>
            
            <?php if (!$is_readonly): ?>
            <div class="btn-group">
                <a href="ukt_tambah_peserta.php?id=<?php echo $id; ?>" class="btn btn-primary">+ Tambah Peserta</a>
                <a href="ukt_input_nilai.php?id=<?php echo $id; ?>" class="btn btn-warning">📝 Input Nilai</a>
                <a href="?id=<?php echo $id; ?>&print=1" class="btn btn-print">🖨️ Cetak Peserta</a>
            </div>
            <?php endif; ?>
            
            <?php if ($total_peserta > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>No Anggota</th>
                        <th>Nama Anggota</th>
                        <th>Dari Tingkat</th>
                        <th>Ke Tingkat</th>
                        <th>Nilai Rata-rata</th>
                        <th>Status</th>
                        <?php if (!$is_readonly): ?>
                        <th>Sertifikat</th>
                        <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $peserta_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['no_anggota']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td><?php echo $row['tingkat_dari'] ?? '-'; ?></td>
                        <td><?php echo $row['tingkat_ke'] ?? '-'; ?></td>
                        <td><?php echo $row['rata_rata'] ? number_format($row['rata_rata'], 2) : '-'; ?></td>
                        <td>
                            <?php 
                            if ($row['status'] == 'lulus') echo '<span class="status-lulus">✓ LULUS</span>';
                            else if ($row['status'] == 'tidak_lulus') echo '<span class="status-tidak">✗ TIDAK LULUS</span>';
                            else echo '<span style="color: #3498db;">• PESERTA</span>';
                            ?>
                        </td>
                        <?php if (!$is_readonly): ?>
                        <td>
                            <?php 
                            if ($row['status'] == 'lulus') {
                                if (!empty($row['sertifikat_path'])) {
                                    echo '<span class="sertifikat-status sertifikat-ada">✓ Ada</span>';
                                } else {
                                    echo '<span class="sertifikat-status sertifikat-belum">❌ Belum</span>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="ukt_detail_peserta.php?id=<?php echo $row['id']; ?>&ukt_id=<?php echo $id; ?>" 
                               class="btn btn-lihat">👁️ Lihat</a>
                            
                            <?php if ($row['status'] == 'lulus'): ?>
                                <a href="ukt_input_sertifikat.php?id=<?php echo $row['id']; ?>&ukt_id=<?php echo $id; ?>" 
                                class="btn btn-primary" style="padding: 8px 12px; font-size: 12px;">
                                    📜 Sertifikat
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary btn-disabled" style="padding: 8px 12px; font-size: 12px;" disabled title="Hanya peserta yang lulus dapat upload sertifikat">
                                    📜 Sertifikat
                                </button>
                            <?php endif; ?>
                            
                            <a href="ukt_hapus_peserta.php?id=<?php echo $row['id']; ?>&ukt_id=<?php echo $id; ?>" 
                               class="btn btn-primary" onclick="return confirm('Hapus peserta?')" style="padding: 8px 12px; font-size: 12px;">Hapus</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">📭 Belum ada peserta UKT</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
