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

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_jenis = isset($_GET['filter_jenis']) ? $_GET['filter_jenis'] : '';
$filter_negara = isset($_GET['filter_negara']) ? (int)$_GET['filter_negara'] : 0;
$filter_provinsi = isset($_GET['filter_provinsi']) ? (int)$_GET['filter_provinsi'] : 0;
$filter_kota = isset($_GET['filter_kota']) ? (int)$_GET['filter_kota'] : 0;

$sql = "SELECT r.*, k.nama as nama_kota, p.nama as nama_provinsi, n.nama as nama_negara 
        FROM ranting r 
        LEFT JOIN kota k ON r.kota_id = k.id
        LEFT JOIN provinsi p ON k.provinsi_id = p.id
        LEFT JOIN negara n ON p.negara_id = n.id
        WHERE 1=1";

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (r.nama_ranting LIKE '%" . $search . "%' OR r.alamat LIKE '%" . $search . "%')";
}

if ($filter_jenis) {
    $filter_jenis = $conn->real_escape_string($filter_jenis);
    $sql .= " AND r.jenis = '" . $filter_jenis . "'";
}

if ($filter_negara > 0) {
    $sql .= " AND n.id = " . intval($filter_negara);
}

if ($filter_provinsi > 0) {
    $sql .= " AND p.id = " . intval($filter_provinsi);
}

if ($filter_kota > 0) {
    $sql .= " AND r.kota_id = " . intval($filter_kota);
}

$sql .= " ORDER BY r.nama_ranting";

$result = $conn->query($sql);
$total_ranting = $result->num_rows;

// Ambil daftar untuk dropdown
$negara_result = $conn->query("SELECT id, nama FROM negara ORDER BY nama");
$provinsi_result = null;
$kota_result = null;

if ($filter_negara > 0) {
    $provinsi_result = $conn->query("SELECT id, nama FROM provinsi WHERE negara_id = " . intval($filter_negara) . " ORDER BY nama");
}

if ($filter_provinsi > 0) {
    $kota_result = $conn->query("SELECT id, nama FROM kota WHERE provinsi_id = " . intval($filter_provinsi) . " ORDER BY nama");
}

$is_readonly = $_SESSION['role'] == 'user';

// Handle print mode
$print_mode = filter_input(INPUT_GET, 'print', FILTER_VALIDATE_BOOLEAN);

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
    <title>Print - Daftar Unit / Ranting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 5px 0; font-size: 18px; }
        .header p { color: #666; font-size: 13px; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f0f0f0; font-weight: bold; }
        .print-info { text-align: right; font-size: 11px; color: #666; margin-top: 15px; }
        .badge { display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-ukm { background: #e3f2fd; color: #1976d2; }
        .badge-ranting { background: #f3e5f5; color: #7b1fa2; }
        .badge-unit { background: #fff3e0; color: #e65100; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
    <style media="print">
        @page { size: A4 landscape; margin: 10mm; }
        body { margin: 0; }
    </style>
</head>
<body>
    <div style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;" class="no-print">
        <button onclick="window.history.back()" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
            ← Kembali
        </button>
    </div>
    <div class="header">
        <h1>DAFTAR UNIT / RANTING PERISAI DIRI</h1>
        <p>Tanggal Cetak: <?php echo date('d M Y H:i:s'); ?></p>
        <p>Total Unit / Ranting: <?php echo $total_ranting; ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 18%;">Nama Unit / Ranting</th>
                <th style="width: 8%;">Jenis</th>
                <th style="width: 15%;">Ketua</th>
                <th style="width: 20%;">Alamat</th>
                <th style="width: 12%;">Kontak</th>
                <th style="width: 17%;">Pengurus Kota</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><strong><?php echo htmlspecialchars($row['nama_ranting']); ?></strong></td>
                <td><span class="badge badge-<?php echo $row['jenis']; ?>"><?php echo strtoupper($row['jenis']); ?></span></td>
                <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['alamat'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['no_kontak'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['nama_kota'] ?? '-'); ?></td>
            </tr>
            <?php endwhile; ?>
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
    <title>Manajemen Unit / Ranting - Kelatnas Indonesia Perisai Diri</title>
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
            align-items: center;
        }
        
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #333; }
        
        .button-group { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-print { background: #6c757d; color: white; }
        
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        input[type="text"], select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 100%;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
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
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-ukm { background: #e3f2fd; color: #1976d2; }
        .badge-ranting { background: #f3e5f5; color: #7b1fa2; }
        .badge-unit { background: #fff3e0; color: #e65100; }
        
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
        
        .icon-view { background: #3498db; }
        .icon-view:hover { background: #2980b9; }
        .icon-edit { background: #f39c12; }
        .icon-edit:hover { background: #d68910; }
        .icon-delete { background: #e74c3c; }
        .icon-delete:hover { background: #c0392b; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
    <link rel="stylesheet" href="../../styles/print.css">
</head>
<body>
    <?php renderNavbar('🏢 Manajemen Unit / Ranting'); ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Unit / Ranting</h1>
                <p style="color: #666; margin-top: 5px;">Total: <strong><?php echo $total_ranting; ?></strong></p>
            </div>
            <div class="button-group">
                <?php if (!$is_readonly): ?>
                <a href="ranting_tambah.php" class="btn btn-primary">+ Tambah Unit / Ranting</a>
                <a href="ranting_import.php" class="btn btn-success">⬆️ Impor CSV</a>
                <?php endif; ?>
                <button onclick="window.location.href='ranting.php?print=true'" class="btn btn-print">🖨️ Cetak</button>
            </div>
        </div>
        
        <!-- Filter Cascade -->
        <div class="search-filter">
            <form method="GET" action="" id="rantingForm">
                <div class="filter-section-title">🔍 Pencarian & Filter</div>
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Cari nama atau alamat..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="filter_jenis">
                        <option value="">-- Semua Jenis --</option>
                        <option value="ukm" <?php echo $filter_jenis == 'ukm' ? 'selected' : ''; ?>>UKM</option>
                        <option value="ranting" <?php echo $filter_jenis == 'ranting' ? 'selected' : ''; ?>>Ranting</option>
                        <option value="unit" <?php echo $filter_jenis == 'unit' ? 'selected' : ''; ?>>Unit</option>
                    </select>
                </div>

                <div class="filter-section-title">📋 Filter Regional (Cascade)</div>
                <div class="filter-row">
                    <div>
                        <select name="filter_negara" id="filter_negara" onchange="updateProvinsiKota()">
                            <option value="">-- Semua Negara --</option>
                            <?php 
                            $negara_result->data_seek(0);
                            while ($row = $negara_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $filter_negara == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nama']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <select name="filter_provinsi" id="filter_provinsi" onchange="updateKota()">
                            <option value="">-- Semua Provinsi --</option>
                            <?php 
                            if ($provinsi_result) {
                                $provinsi_result->data_seek(0);
                                while ($row = $provinsi_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $row['id']; ?>" <?php echo $filter_provinsi == $row['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama']); ?>
                                    </option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <select name="filter_kota" id="filter_kota" onchange="this.form.submit()">
                            <option value="">-- Semua Kota --</option>
                            <?php 
                            if ($kota_result) {
                                $kota_result->data_seek(0);
                                while ($row = $kota_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $row['id']; ?>" <?php echo $filter_kota == $row['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama']); ?>
                                    </option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="filter-row" style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="ranting.php" class="btn" style="background: #6c757d; color: white;">Reset</a>
                </div>
            </form>
        </div>

        <!-- Tabel Ranting -->
        <div class="table-container">
            <?php if ($total_ranting > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Unit / Ranting</th>
                        <th>Jenis</th>
                        <th>Ketua</th>
                        <th>Alamat</th>
                        <th>Kontak</th>
                        <th>Pengurus Kota</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_ranting']); ?></strong></td>
                        <td><span class="badge badge-<?php echo $row['jenis']; ?>"><?php echo strtoupper($row['jenis']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['alamat'] ?? '-', 0, 40)); ?></td>
                        <td><?php echo htmlspecialchars($row['no_kontak'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_pengurus'] ?? '-'); ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="ranting_detail.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!$is_readonly): ?>
                                <a href="ranting_edit.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="ranting_hapus.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">🔍 Tidak ada data unit / ranting</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateProvinsiKota() {
            const negaraSelect = document.getElementById('filter_negara');
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            
            const negaraId = negaraSelect.value;
            
            // Reset and disable dependent dropdowns
            provinsiSelect.innerHTML = '<option value="">-- Semua Provinsi --</option>';
            kotaSelect.innerHTML = '<option value="">-- Semua Kota --</option>';
            provinsiSelect.disabled = true;
            kotaSelect.disabled = true;
            
            if (negaraId === '') {
                return;
            }
            
            // Fetch provinces
            fetch('../../api/get_provinsi.php?negara_id=' + negaraId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Semua Provinsi --</option>';
                        data.data.forEach(provinsi => {
                            html += '<option value="' + provinsi.id + '">' + provinsi.nama + '</option>';
                        });
                        provinsiSelect.innerHTML = html;
                        provinsiSelect.disabled = false;
                    }
                })
                .catch(error => console.error('Error:', error));
            
            // Submit form
            document.getElementById('rantingForm').submit();
        }
        
        function updateKota() {
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            
            const provinsiId = provinsiSelect.value;
            
            // Reset and disable dependent dropdown
            kotaSelect.innerHTML = '<option value="">-- Semua Kota --</option>';
            kotaSelect.disabled = true;
            
            if (provinsiId === '') {
                document.getElementById('rantingForm').submit();
                return;
            }
            
            // Fetch cities
            fetch('../../api/get_kota.php?provinsi_id=' + provinsiId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Semua Kota --</option>';
                        data.data.forEach(kota => {
                            html += '<option value="' + kota.id + '">' + kota.nama + '</option>';
                        });
                        kotaSelect.innerHTML = html;
                        kotaSelect.disabled = false;
                    }
                })
                .catch(error => console.error('Error:', error));
            
            // Submit form
            document.getElementById('rantingForm').submit();
        }
    </script>
</body>
</html>