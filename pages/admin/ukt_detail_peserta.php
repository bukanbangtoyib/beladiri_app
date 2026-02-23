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

// Check permission
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$peserta_id = (int)$_GET['id'] ?? 0;
$ukt_id = (int)$_GET['ukt_id'] ?? 0;

if (!$peserta_id || !$ukt_id) {
    die("❌ Parameter tidak lengkap!");
}

// Ambil data peserta dengan info lengkap
$sql = "SELECT up.*, 
               a.nama_lengkap, a.no_anggota, a.ranting_saat_ini_id,
               u.tanggal_pelaksanaan, u.lokasi,
               t1.nama_tingkat as tingkat_dari, 
               t2.nama_tingkat as tingkat_ke,
               r.nama_ranting,
               p.nama as nama_penyelenggara
        FROM ukt_peserta up
        JOIN ukt u ON up.ukt_id = u.id
        JOIN anggota a ON up.anggota_id = a.id
        LEFT JOIN tingkatan t1 ON up.tingkat_dari_id = t1.id
        LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id
        LEFT JOIN kota p ON u.penyelenggara_id = p.id
        WHERE up.id = $peserta_id AND up.ukt_id = $ukt_id";

$result = $conn->query($sql);
if ($result->num_rows == 0) {
    die("❌ Data peserta tidak ditemukan!");
}

$peserta = $result->fetch_assoc();

// Fungsi singkatan tingkat
function singkatTingkat($tingkat_ke) {
    $singkat = [
        'Dasar I' => 'DI',
        'Dasar II' => 'DII',
        'Calon Keluarga' => 'Cakel',
        'Putih' => 'P',
        'Putih Hijau' => 'PH',
        'Hijau' => 'H',
        'Hijau Biru' => 'HB',
        'Biru' => 'B',
        'Biru Merah' => 'BM',
        'Merah' => 'M',
        'Merah Kuning' => 'MK',
        'Kuning' => 'K/PM',
        'Pendekar' => 'PKE'
    ];
    return isset($singkat[$tingkat_ke]) ? $singkat[$tingkat_ke] : $tingkat_ke;
}

// Hitung rata-rata dari 10 komponen
$nilai_komponen = [
    'A' => $peserta['nilai_a'],
    'B' => $peserta['nilai_b'],
    'C' => $peserta['nilai_c'],
    'D' => $peserta['nilai_d'],
    'E' => $peserta['nilai_e'],
    'F' => $peserta['nilai_f'],
    'G' => $peserta['nilai_g'],
    'H' => $peserta['nilai_h'],
    'I' => $peserta['nilai_i'],
    'J' => $peserta['nilai_j']
];

// Hitung statistik
$nilai_terisi = 0;
$nilai_tertinggi = 0;
$nilai_terendah = 100;
$nilai_kosong = 0;

foreach ($nilai_komponen as $nilai) {
    if ($nilai !== null) {
        $nilai_terisi++;
        $nilai_tertinggi = max($nilai_tertinggi, $nilai);
        $nilai_terendah = min($nilai_terendah, $nilai);
    } else {
        $nilai_kosong++;
    }
}

$rata_rata = $peserta['rata_rata'] ?? null;
$print_mode = filter_input(INPUT_GET, 'print', FILTER_VALIDATE_BOOLEAN);

// Handle print mode with proper validation and error handling
if ($print_mode) {
    // Set proper headers for print
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    ?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print - Nilai Peserta UKT</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; }
        .header { 
            text-align: 
            center;
            margin-bottom: 20px; 
        }

        .header h1 { 
            margin: 5px 0; 
        }

        .header p { 
            color: #666; 
            font-size: 13px; 
            margin: 2px 0; 
        }

        table { 
            width: 100%; 
            border-collapse: 
            collapse; 
            margin-top: 15px; 
        }

        th, td { 
            border: 1px solid #333; 
            padding: 8px; 
            text-align: left; 
            font-size: 12px; 
        }

        th { 
            background: #f0f0f0; 
            font-weight: bold; 
        
        }
        .print-info { 
            text-align: right; 
            font-size: 11px; 
            color: #666; 
            margin-top: 15px; 
        }

        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
    
    <!-- CSS untuk print -->
    <style media="print">
        @page { size: A4 landscape; margin: 10mm; }
        body { margin: 0; }
    </style>
</head>
<body>
    <div style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">
        <button onclick="window.history.back()" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
            ← Kembali
        </button>
    </div>
    <div class="header">
        <h1>Nilai Peserta UKT</h1>
        <p>Tanggal Cetak: <?php echo date('d M Y H:i:s'); ?></p>
        <p>Penyelenggara: <?php echo htmlspecialchars($peserta['nama_penyelenggara'] ?? '-'); ?></p>
        <p>Lokasi: <?php echo htmlspecialchars($peserta['lokasi'] ?? '-'); ?></p>
        <p>Total Peserta: Diisi mari ngene></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 10%;">No Anggota</th>
                <th style="width: 18%;">Nama Lengkap</th>                
                <th style="width: 8%;">Tingkat</th>
                <th style="width: 15%;">Unit / Ranting</th>
                <th style="width: 4%;">Nilai 1</th>
                <th style="width: 4%;">Nilai 2</th>
                <th style="width: 4%;">Nilai 3</th>
                <th style="width: 4%;">Nilai 4</th>
                <th style="width: 4%;">Nilai 5</th>
                <th style="width: 4%;">Nilai 6</th>
                <th style="width: 4%;">Nilai 7</th>
                <th style="width: 4%;">Nilai 8</th>
                <th style="width: 4%;">Nilai 9</th>
                <th style="width: 4%;">Nilai 10</th>
                <th style="width: 4%;">Rata-rata</th>
                <th style="width: 4%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td><?php echo htmlspecialchars($peserta['no_anggota']); ?></td>
                <td><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></td>
                <td><?php echo singkatTingkat($peserta['tingkat_ke'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($peserta['nama_ranting'] ?? '-'); ?></td>
                <td><?php echo $peserta['nilai_a'] !== null ? number_format($peserta['nilai_a'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_b'] !== null ? number_format($peserta['nilai_b'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_c'] !== null ? number_format($peserta['nilai_c'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_d'] !== null ? number_format($peserta['nilai_d'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_e'] !== null ? number_format($peserta['nilai_e'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_f'] !== null ? number_format($peserta['nilai_f'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_g'] !== null ? number_format($peserta['nilai_g'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_h'] !== null ? number_format($peserta['nilai_h'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_i'] !== null ? number_format($peserta['nilai_i'], 2) : '-'; ?></td>
                <td><?php echo $peserta['nilai_j'] !== null ? number_format($peserta['nilai_j'], 2) : '-'; ?></td>
                <td><?php echo $peserta['rata_rata'] !== null ? number_format($peserta['rata_rata'], 2) : '-'; ?></td>
                <td>
                    <?php 
                    if ($peserta['status'] == 'lulus') {
                        echo 'LULUS';
                    } elseif ($peserta['status'] == 'tidak_lulus') {
                        echo 'TIDAK LULUS';
                    } else {
                        echo 'PESERTA';
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <div class="print-info">
        <p>Halaman ini dicetak dari Sistem Manajemen Perisai Diri</p>
        <p>Printed: <?php echo date('d/m/Y H:i'); ?></p>
    </div>
    
    <script>
        window.print();
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
    <title>Detail Nilai Peserta - Perisai Diri</title>
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
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        
        .btn-print {
            padding: 12px 25px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .btn-print:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .info-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .label {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .value {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
            font-size: 20px;
        }
        
        
        h2 {
            color: white;
        }
        
        h3 {
            color: #333;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .nilai-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .nilai-table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
        }
        
        .nilai-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        
        .nilai-table tr:hover {
            background: #f9f9f9;
        }
        
        .nilai-ada {
            color: #27ae60;
            font-weight: 600;
        }
        
        .nilai-kosong {
            color: #999;
            font-style: italic;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            text-align: center;
        }
        
        .stat-box-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .stat-box-value {
            color: #667eea;
            font-size: 24px;
            font-weight: 700;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .status-lulus {
            background: #d4edda;
            color: #155724;
        }
        
        .status-tidak-lulus {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-peserta {
            background: #cce5ff;
            color: #004085;
        }
        
        .note-box {
            background: #e8f4f8;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .note-box p {
            color: #333;
            margin: 0;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php renderNavbar('📊 Detail Nilai Peserta UKT'); ?>
    
    <div class="container">          
        <div class="info-card">
            <h1>📋 Informasi Peserta UKT</h1>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">No Anggota</span>
                    <span class="value"><?php echo htmlspecialchars($peserta['no_anggota']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Nama Lengkap</span>
                    <span class="value"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Ranting / Unit</span>
                    <span class="value"><?php echo $peserta['nama_ranting'] ?? '-'; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Tingkat Dari - Ke</span>
                    <span class="value"><?php echo ($peserta['tingkat_dari'] ?? '-') . ' → ' . ($peserta['tingkat_ke'] ?? '-'); ?></span>
                </div>
            </div>
            
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Tanggal UKT</span>
                    <span class="value"><?php echo date('d M Y', strtotime($peserta['tanggal_pelaksanaan'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Lokasi</span>
                    <span class="value"><?php echo htmlspecialchars($peserta['lokasi']); ?></span>
                </div>
                <?php if ($peserta['nama_penyelenggara']): ?>
                <div class="info-item">
                    <span class="label">Penyelenggara</span>
                    <span class="value"><?php echo htmlspecialchars($peserta['nama_penyelenggara']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="label">Status</span>
                    <span>
                        <?php 
                        if ($peserta['status'] == 'lulus') {
                            echo '<span class="status-badge status-lulus">✓ LULUS</span>';
                        } elseif ($peserta['status'] == 'tidak_lulus') {
                            echo '<span class="status-badge status-tidak-lulus">✗ TIDAK LULUS</span>';
                        } else {
                            echo '<span class="status-badge status-peserta">• PESERTA</span>';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="info-card">
            <h1>📊 Detail Nilai Penilaian</h1>
            
            <h3>Statistik Nilai</h3>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-box-label">Nilai Rata-rata</div>
                    <div class="stat-box-value"><?php echo $rata_rata ? number_format($rata_rata, 2) : '-'; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">Nilai Tertinggi</div>
                    <div class="stat-box-value" style="color: #27ae60;"><?php echo $nilai_terisi > 0 ? number_format($nilai_tertinggi, 2) : '-'; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">Nilai Terendah</div>
                    <div class="stat-box-value" style="color: #e74c3c;"><?php echo $nilai_terisi > 0 ? number_format($nilai_terendah, 2) : '-'; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">Komponen Terisi</div>
                    <div class="stat-box-value"><?php echo $nilai_terisi; ?> / 10</div>
                </div>
            </div>
            
            <h3>Nilai Per Komponen</h3>
            <table class="nilai-table">
                <thead>
                    <tr>
                        <th>Nilai 1</th>
                        <th>Nilai 2</th>
                        <th>Nilai 3</th>
                        <th>Nilai 4</th>
                        <th>Nilai 5</th>
                        <th>Nilai 6</th>
                        <th>Nilai 7</th>
                        <th>Nilai 8</th>
                        <th>Nilai 9</th>
                        <th>Nilai 10</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="<?php echo $peserta['nilai_a'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_a'] !== null ? number_format($peserta['nilai_a'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_b'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_b'] !== null ? number_format($peserta['nilai_b'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_c'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_c'] !== null ? number_format($peserta['nilai_c'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_d'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_d'] !== null ? number_format($peserta['nilai_d'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_e'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_e'] !== null ? number_format($peserta['nilai_e'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_f'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_f'] !== null ? number_format($peserta['nilai_f'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_g'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_g'] !== null ? number_format($peserta['nilai_g'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_h'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_h'] !== null ? number_format($peserta['nilai_h'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_i'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_i'] !== null ? number_format($peserta['nilai_i'], 2) : '-'; ?>
                        </td>
                        <td class="<?php echo $peserta['nilai_j'] !== null ? 'nilai-ada' : 'nilai-kosong'; ?>">
                            <?php echo $peserta['nilai_j'] !== null ? number_format($peserta['nilai_j'], 2) : '-'; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="note-box">
                <p>
                    <strong>📝 Catatan:</strong>
                    Peserta ini memiliki 
                    <strong><?php echo $nilai_terisi; ?> dari 10 komponen</strong> 
                    yang sudah dinilai.
                    <?php if ($nilai_kosong > 0): ?>
                        Masih ada <strong><?php echo $nilai_kosong; ?> komponen</strong> yang belum dinilai.
                    <?php else: ?>
                        <span style="color: #27ae60;">✓ Semua komponen sudah dinilai!</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <button onclick="window.location.href='?<?php echo http_build_query($_GET); ?>&print=1'" class="btn btn-print">🖨️ Cetak</button>
        </div>
    </div>
</body>
</html>
