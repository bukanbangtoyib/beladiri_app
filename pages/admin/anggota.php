<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../pages/admin/ukt_eligibility_helper.php';

// Ambil data anggota
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_tingkat = isset($_GET['filter_tingkat']) ? $_GET['filter_tingkat'] : '';
$filter_ranting = isset($_GET['filter_ranting']) ? $_GET['filter_ranting'] : '';
$filter_pengprov = isset($_GET['filter_pengprov']) ? $_GET['filter_pengprov'] : '';
$filter_pengkot = isset($_GET['filter_pengkot']) ? $_GET['filter_pengkot'] : '';
$filter_layak_ukt = isset($_GET['filter_layak_ukt']) ? $_GET['filter_layak_ukt'] : '';
$filter_kerohanian = isset($_GET['filter_kerohanian']) ? $_GET['filter_kerohanian'] : '';
$print_mode = isset($_GET['print']) ? true : false;

// Fungsi singkatan tingkat
function singkatTingkat($nama_tingkat) {
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
    return isset($singkat[$nama_tingkat]) ? $singkat[$nama_tingkat] : $nama_tingkat;
}

// Build query dengan JOIN ke pengurus
$sql = "SELECT a.*, t.nama_tingkat, t.urutan, r.nama_ranting, 
               pk.nama_pengurus as peng_kota, pp.nama_pengurus as peng_prov
        FROM anggota a 
        LEFT JOIN tingkatan t ON a.tingkat_id = t.id 
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id 
        LEFT JOIN pengurus pk ON r.pengurus_kota_id = pk.id
        LEFT JOIN pengurus pp ON pk.pengurus_induk_id = pp.id
        WHERE 1=1";

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (a.nama_lengkap LIKE '%$search%' OR a.no_anggota LIKE '%$search%')";
}

if ($filter_tingkat) {
    $filter_tingkat = (int)$filter_tingkat;
    $sql .= " AND a.tingkat_id = $filter_tingkat";
}

if ($filter_ranting) {
    $filter_ranting = (int)$filter_ranting;
    $sql .= " AND a.ranting_saat_ini_id = $filter_ranting";
}

if ($filter_pengprov) {
    $filter_pengprov = (int)$filter_pengprov;
    $sql .= " AND pp.id = $filter_pengprov";
}

if ($filter_pengkot) {
    $filter_pengkot = (int)$filter_pengkot;
    $sql .= " AND pk.id = $filter_pengkot";
}

if ($filter_kerohanian) {
    $sql .= " AND a.status_kerohanian = '$filter_kerohanian'";
}

// Filter layak UKT dilakukan di PHP setelah query
$sql .= " ORDER BY a.nama_lengkap ASC";

$result = $conn->query($sql);

// Proses filter layak UKT dan hitung total
$filtered_results = [];
while ($row = $result->fetch_assoc()) {
    $eligibility = checkUKTEligibility($conn, $row['id']);
    $is_eligible = $eligibility['layak'];
    
    // Apply filter layak UKT
    if ($filter_layak_ukt == 'ya' && !$is_eligible) {
        continue;
    } elseif ($filter_layak_ukt == 'tidak' && $is_eligible) {
        continue;
    }
    
    $row['eligibility'] = $eligibility;
    $row['is_eligible'] = $is_eligible;
    $filtered_results[] = $row;
}

$total_anggota = count($filtered_results);

// Ambil data untuk dropdown filter
$tingkatan_result = $conn->query("SELECT * FROM tingkatan ORDER BY urutan");
$ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting ORDER BY nama_ranting");
$pengprov_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'provinsi' ORDER BY nama_pengurus");
$pengkot_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' ORDER BY nama_pengurus");

$is_readonly = $_SESSION['role'] == 'user';

// Jika mode print
if ($print_mode) {
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print - Daftar Anggota</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 5px 0; }
        .header p { color: #666; font-size: 13px; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f0f0f0; font-weight: bold; }
        .print-info { text-align: right; font-size: 11px; color: #666; margin-top: 15px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>DAFTAR ANGGOTA LEMBAGA BELADIRI</h1>
        <p>Tanggal Cetak: <?php echo date('d M Y H:i:s'); ?></p>
        <p>Total Anggota: <?php echo $total_anggota; ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">No</th>
                <th style="width: 12%;">No Anggota</th>
                <th style="width: 18%;">Nama Lengkap</th>
                <th style="width: 4%;">JK</th>
                <th style="width: 8%;">Tingkat</th>
                <th style="width: 15%;">Unit/Ranting</th>
                <th style="width: 12%;">PengKota/Kabupaten</th>
                <th style="width: 12%;">PengProv</th>
                <th style="width: 8%;">Layak UKT</th>
                <th style="width: 7%;">Kerohanian</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($filtered_results as $row): 
                $jk = ($row['jenis_kelamin'] == 'L') ? 'L' : 'P';
                $kerohanian = ($row['status_kerohanian'] == 'sudah') ? '✓' : '✗';
                $layak_text = $row['is_eligible'] ? '✓' : ($row['urutan'] == 13 ? 'PKE' : '✗');
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo $row['no_anggota']; ?></td>
                <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                <td><?php echo $jk; ?></td>
                <td><?php echo singkatTingkat($row['nama_tingkat'] ?? ''); ?></td>
                <td><?php echo $row['nama_ranting'] ?? '-'; ?></td>
                <td><?php echo $row['peng_kota'] ?? '-'; ?></td>
                <td><?php echo $row['peng_prov'] ?? '-'; ?></td>
                <td><?php echo $layak_text; ?></td>
                <td><?php echo $kerohanian; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="print-info">
        <p>Halaman ini dicetak dari Sistem Manajemen Lembaga Beladiri</p>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Anggota - Sistem Beladiri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }

        .btn-print {
            background: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background: #5a6268;
        }
        
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .filter-section-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }
        
        input[type="text"],
        select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 100%;
        }
        
        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 12px;
        }
        
        td {
            padding: 11px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        a.data-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        a.data-link:hover {
            text-decoration: underline;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-layak {
            background: #c8e6c9;
            color: #1b5e20;
        }
        
        .badge-tidak-layak {
            background: #ffcdd2;
            color: #b71c1c;
        }

        .badge-pke {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-yes {
            background: #c8e6c9;
            color: #1b5e20;
        }
        
        .badge-no {
            background: #ffcdd2;
            color: #b71c1c;
        }
        
        .action-icons {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
            color: white;
        }
        
        .icon-view {
            background: #3498db;
        }
        
        .icon-view:hover {
            background: #2980b9;
        }
        
        .icon-edit {
            background: #f39c12;
        }
        
        .icon-edit:hover {
            background: #d68910;
        }
        
        .icon-delete {
            background: #e74c3c;
        }
        
        .icon-delete:hover {
            background: #c0392b;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .filter-btn {
            background: #667eea;
            color: white;
        }
        
        .reset-btn {
            background: #6c757d;
            color: white;
        }

        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>👥 Manajemen Anggota</h2>
        <div>
            <span style="margin-right: 20px;">Halo, <?php echo $_SESSION['nama']; ?></span>
            <a href="../../index.php">← Kembali</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Anggota</h1>
                <p style="color: #666; margin-top: 5px;">Total: <strong><?php echo $total_anggota; ?> anggota</strong></p>
            </div>
            <div class="button-row">
                <?php if (!$is_readonly): ?>
                <a href="anggota_tambah.php" class="btn btn-primary">+ Tambah Anggota</a>
                <a href="anggota_import.php" class="btn btn-success">⬆️ Import Excel</a>
                <?php endif; ?>
                <button onclick="window.location.href='?<?php echo http_build_query($_GET); ?>&print=1'" class="btn btn-print">🖨️ Cetak</button>
            </div>
        </div>
        
        <!-- Search & Filter -->
        <div class="search-filter">
            <form method="GET" action="">
                <!-- Pencarian Nama/No Anggota -->
                <div class="filter-section-title">🔍 Cari</div>
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Cari nama atau no anggota..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <!-- Filter Struktur Organisasi -->
                <div class="filter-section-title">📋 Filter Struktur Organisasi</div>
                <div class="filter-row">
                    <select name="filter_pengprov">
                        <option value="">-- Semua Pengurus Provinsi --</option>
                        <?php 
                        $pengprov_result->data_seek(0);
                        while ($prov = $pengprov_result->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $prov['id']; ?>" <?php echo $filter_pengprov == $prov['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nama_pengurus']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="filter_pengkot">
                        <option value="">-- Semua Pengurus Kota / Kabupaten --</option>
                        <?php 
                        $pengkot_result->data_seek(0);
                        while ($kota = $pengkot_result->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $kota['id']; ?>" <?php echo $filter_pengkot == $kota['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kota['nama_pengurus']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="filter_ranting">
                        <option value="">-- Semua Unit / Ranting --</option>
                        <?php 
                        $ranting_result->data_seek(0);
                        while ($rant = $ranting_result->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $rant['id']; ?>" <?php echo $filter_ranting == $rant['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rant['nama_ranting']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Filter Teknis -->
                <div class="filter-section-title">⚙️ Filter Teknis</div>
                <div class="filter-row">
                    <select name="filter_tingkat">
                        <option value="">-- Semua Tingkat --</option>
                        <?php 
                        $tingkatan_result->data_seek(0);
                        while ($ting = $tingkatan_result->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $ting['id']; ?>" <?php echo $filter_tingkat == $ting['id'] ? 'selected' : ''; ?>>
                            <?php echo singkatTingkat($ting['nama_tingkat']); ?> (<?php echo $ting['nama_tingkat']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="filter_layak_ukt">
                        <option value="">-- Semua Layak UKT --</option>
                        <option value="ya" <?php echo $filter_layak_ukt == 'ya' ? 'selected' : ''; ?>>✓ Layak UKT</option>
                        <option value="tidak" <?php echo $filter_layak_ukt == 'tidak' ? 'selected' : ''; ?>>✗ Belum Layak</option>
                    </select>

                    <select name="filter_kerohanian">
                        <option value="">-- Semua Kerohanian --</option>
                        <option value="sudah" <?php echo $filter_kerohanian == 'sudah' ? 'selected' : ''; ?>>✓ Sudah</option>
                        <option value="belum" <?php echo $filter_kerohanian == 'belum' ? 'selected' : ''; ?>>✗ Belum</option>
                    </select>
                </div>

                <!-- Tombol Aksi -->
                <div class="filter-row">
                    <button type="submit" class="btn filter-btn">🔍 Cari</button>
                    <a href="anggota.php" class="btn reset-btn">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Tabel Anggota -->
        <div class="table-container">
            <?php if ($total_anggota > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>No Anggota</th>
                        <th>Nama Lengkap</th>
                        <th>JK</th>
                        <th>Tingkat</th>
                        <th>Unit/Ranting</th>
                        <th>PengKot/Kab</th>
                        <th>PengProv</th>
                        <th>Layak UKT</th>
                        <th>Kerohanian</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_results as $row): 
                        $jk = ($row['jenis_kelamin'] == 'L') ? 'L' : 'P';
                    ?>
                    <tr>
                        <td>
                            <a href="anggota_detail.php?id=<?php echo $row['id']; ?>" class="data-link">
                                <?php echo $row['no_anggota']; ?>
                            </a>
                        </td>
                        <td>
                            <a href="anggota_detail.php?id=<?php echo $row['id']; ?>" class="data-link">
                                <?php echo htmlspecialchars($row['nama_lengkap']); ?>
                            </a>
                        </td>
                        <td><?php echo $jk; ?></td>
                        <td>
                            <a href="anggota.php?filter_tingkat=<?php echo $row['tingkat_id'] ?? ''; ?>" class="data-link">
                                <?php echo singkatTingkat($row['nama_tingkat'] ?? '-'); ?>
                            </a>
                        </td>
                        <td>
                            <a href="anggota.php?filter_ranting=<?php echo $row['ranting_saat_ini_id'] ?? ''; ?>" class="data-link">
                                <?php echo $row['nama_ranting'] ?? '-'; ?>
                            </a>
                        </td>
                        <td>
                            <?php echo $row['peng_kota'] ?? '-'; ?>
                        </td>
                        <td>
                            <?php echo $row['peng_prov'] ?? '-'; ?>
                        </td>
                        <td>
                            <?php if ($row['urutan'] == 13): ?>
                                <span class="badge badge-pke">PKE</span>
                            <?php elseif ($row['is_eligible']): ?>
                                <span class="badge badge-layak">✓ Layak</span>
                            <?php else: ?>
                                <span class="badge badge-tidak-layak">✗ <?php echo $row['eligibility']['hari_tersisa']; ?>h</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $kerohanian = isset($row['status_kerohanian']) ? $row['status_kerohanian'] : 'belum';
                            $badge_krh = ($kerohanian == 'sudah') ? 'badge-yes' : 'badge-no';
                            $krh_text = ($kerohanian == 'sudah') ? '✓' : '✗';
                            ?>
                            <span class="badge <?php echo $badge_krh; ?>"><?php echo $krh_text; ?></span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="anggota_detail.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!$is_readonly): ?>
                                <a href="anggota_edit.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="anggota_hapus.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus data anggota ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>🔍 Tidak ada data anggota</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>