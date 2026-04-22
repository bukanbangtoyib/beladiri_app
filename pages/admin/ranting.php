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
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Handle AJAX request untuk filter
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    // Get user info for role-based filtering
    $user_role = $_SESSION['role'] ?? '';
    $user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;
    
    $search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
    $filter_jenis = isset($_GET['filter_jenis']) ? $conn->real_escape_string($_GET['filter_jenis']) : '';
    $filter_negara = isset($_GET['filter_negara']) ? (int)$_GET['filter_negara'] : 0;
    $filter_provinsi = isset($_GET['filter_provinsi']) ? (int)$_GET['filter_provinsi'] : 0;
    $filter_kota = isset($_GET['filter_kota']) ? (int)$_GET['filter_kota'] : 0;
    
    // Build where clause for AJAX
    // Search and jenis filters apply globally to all data (not restricted by role hierarchy)
    $where_clause = "1=1";
    
    // Only apply role-based restrictions if NOT filtering by search or jenis
    // Search and jenis filters work globally across all regional data
    if (empty($search) && empty($filter_jenis)) {
        if ($user_role === 'negara') {
            $where_clause .= " AND k.provinsi_id IN (SELECT id FROM provinsi WHERE negara_id = " . (int)$user_pengurus_id . ")";
        } elseif ($user_role === 'pengprov') {
            $where_clause .= " AND k.provinsi_id = " . (int)$user_pengurus_id;
        } elseif ($user_role === 'pengkot') {
            $where_clause .= " AND r.kota_id = " . (int)$user_pengurus_id;
        } elseif ($user_role === 'unit' || $user_role === 'ranting' || $user_role === 'tamu') {
            $user_ranting_id = $_SESSION['ranting_id'] ?? 0;
            if ($user_ranting_id > 0) {
                $where_clause .= " AND r.id = " . (int)$user_ranting_id;
            } else {
                $where_clause .= " AND 1=0";
            }
        }
    }

    $sql = "SELECT r.*, k.nama as nama_kota, p.nama as nama_provinsi, n.nama as nama_negara
            FROM ranting r
            LEFT JOIN kota k ON r.kota_id = k.id
            LEFT JOIN provinsi p ON k.provinsi_id = p.id
            LEFT JOIN negara n ON p.negara_id = n.id
            WHERE $where_clause";

    if ($search) {
        $sql .= " AND (r.nama_ranting LIKE '%$search%' OR r.kode LIKE '%$search%')";
    }
    if ($filter_jenis) {
        $sql .= " AND r.jenis = '$filter_jenis'";
    }
    if ($filter_negara > 0) $sql .= " AND n.id = $filter_negara";
    if ($filter_provinsi > 0) $sql .= " AND p.id = $filter_provinsi";
    if ($filter_kota > 0) $sql .= " AND r.kota_id = $filter_kota";

    $sql .= " ORDER BY r.nama_ranting ASC";
    $res = $conn->query($sql);
    
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'nama_ranting' => htmlspecialchars($row['nama_ranting']),
            'jenis' => $row['jenis'],
            'ketua_nama' => htmlspecialchars($row['ketua_nama'] ?? '-'),
            'alamat' => htmlspecialchars(substr($row['alamat'] ?? '-', 0, 40)),
            'no_kontak' => htmlspecialchars($row['no_kontak'] ?? '-'),
            'nama_kota' => htmlspecialchars($row['nama_kota'] ?? '-')
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

$filter_ranting = isset($_GET['filter_ranting']) ? (int)$_GET['filter_ranting'] : 0;
$filter_jenis = isset($_GET['filter_jenis']) ? $_GET['filter_jenis'] : '';
$filter_negara = isset($_GET['filter_negara']) ? (int)$_GET['filter_negara'] : 0;
$filter_provinsi = isset($_GET['filter_provinsi']) ? (int)$_GET['filter_provinsi'] : 0;
$filter_kota = isset($_GET['filter_kota']) ? (int)$_GET['filter_kota'] : 0;

// Get selected ranting name for display
$selected_ranting_nama = '';
if ($filter_ranting > 0) {
    $ranting_query = $conn->query("SELECT nama_ranting FROM ranting WHERE id = " . intval($filter_ranting));
    if ($ranting_row = $ranting_query->fetch_assoc()) {
        $selected_ranting_nama = $ranting_row['nama_ranting'];
    }
}

$sql = "SELECT r.*, k.nama as nama_kota, p.nama as nama_provinsi, n.nama as nama_negara 
        FROM ranting r 
        LEFT JOIN kota k ON r.kota_id = k.id
        LEFT JOIN provinsi p ON k.provinsi_id = p.id
        LEFT JOIN negara n ON p.negara_id = n.id
        WHERE 1=1";

if ($filter_ranting > 0) {
    $sql .= " AND r.id = " . intval($filter_ranting);
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

// Apply role-based filtering
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

// Build WHERE clause based on role
$where_clause = "1=1";
$filter_kota = isset($_GET['filter_kota']) ? (int)$_GET['filter_kota'] : 0;

if ($user_role === 'negara') {
    // Negara can see all ranting in their country
    $where_clause .= " AND k.provinsi_id IN (SELECT id FROM provinsi WHERE negara_id = " . (int)$user_pengurus_id . ")";
} elseif ($user_role === 'pengprov') {
    // Pengprov can see all ranting in their province
    $where_clause .= " AND k.provinsi_id = " . (int)$user_pengurus_id;
} elseif ($user_role === 'pengkot') {
    // Pengkot can see all ranting in their city
    $where_clause .= " AND r.kota_id = " . (int)$user_pengurus_id;
} elseif ($user_role === 'unit' || $user_role === 'ranting' || $user_role === 'tamu') {
    // Unit/Tamu can only see their own ranting
    $user_ranting_id = $_SESSION['ranting_id'] ?? 0;
    if ($user_ranting_id > 0) {
        $where_clause .= " AND r.id = " . (int)$user_ranting_id;
    } else {
        $where_clause .= " AND 1=0";
    }
}

if ($filter_kota > 0) {
    $where_clause .= " AND r.kota_id = " . $filter_kota;
}

// Show all regional filters for all roles
$show_negara_filter = true;
$show_provinsi_filter = true;
$show_kota_filter = true;

$can_add = ($user_role === 'admin');
$can_edit = ($user_role === 'admin' || $user_role === 'pengkot' || $user_role === 'ranting' || $user_role === 'unit');
$can_delete = ($user_role === 'admin' || $user_role === 'pengkot');
$is_readonly = !$can_add;

// Get user's ranting_id for ownership checking (for ranting/unit roles)
$user_ranting_id = $_SESSION['ranting_id'] ?? 0;
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
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
            text-align: center;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-print { background: #6c757d; color: white; }
        .btn-print:hover { background: #5a6268; }   
        .btn-reset { background: #6c757d; color: white; }
        .btn-reset:hover { background: #5a6268; }
        
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
        
        .select2-container--default .select2-selection--single {
            height: 42px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            padding-left: 0;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #999;
        }

        /* Height alignment for ranting.php */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            padding: 4px 10px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
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
                <p style="color: #666; margin-top: 5px;">Total: <strong id="total-count"><?php echo $total_ranting; ?></strong></p>
            </div>
            <div class="button-group">
                <?php if ($can_add): ?>
                <a href="ranting_tambah.php" class="btn btn-primary">+ Tambah Unit / Ranting</a>
                <a href="ranting_import.php" class="btn btn-success">⬆️ Impor CSV</a>
                <?php endif; ?>
                <button onclick="window.location.href='ranting.php?print=true' + getFilterParams()" class="btn btn-print">🖨️ Cetak</button>
            </div>
        </div>
        
        <!-- Filter Cascade -->
        <div class="search-filter">
            <form method="GET" action="" id="rantingForm" onsubmit="return false;">
                <div class="filter-section-title">🔍 Pencarian & Filter</div>
                <div class="filter-row">
                    <div>
                        <input type="text" id="search_name" placeholder="Cari nama unit/ranting..." autocomplete="off">
                    </div>
                    
                    <div>
                        <select name="filter_jenis" id="filter_jenis" onchange="applyFilters()">
                            <option value="">-- Semua Jenis --</option>
                            <option value="ukm" <?php echo $filter_jenis == 'ukm' ? 'selected' : ''; ?>>UKM</option>
                            <option value="ranting" <?php echo $filter_jenis == 'ranting' ? 'selected' : ''; ?>>Ranting</option>
                            <option value="unit" <?php echo $filter_jenis == 'unit' ? 'selected' : ''; ?>>Unit</option>
                        </select>
                    </div>
                </div>

                <div class="filter-section-title">📋 Filter Regional (Cascade)</div>
                <div class="filter-row">
                    <?php if ($show_negara_filter): ?>
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
                    <?php endif; ?>

                    <?php if ($show_provinsi_filter): ?>
                    <div>
                        <select name="filter_provinsi" id="filter_provinsi" onchange="updateKota()" <?php echo $filter_negara > 0 ? '' : 'disabled'; ?>>
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
                    <?php endif; ?>

                    <?php if ($show_kota_filter): ?>
                    <div>
                        <select name="filter_kota" id="filter_kota" onchange="applyFilters()" <?php echo $filter_provinsi > 0 ? '' : 'disabled'; ?>>
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
                    <?php endif; ?>
                </div>

                <div class="filter-row" style="margin-top: 15px;">
                    <a href="ranting.php" class="btn btn-reset">Reset</a>
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
                <tbody id="ranting-tbody">
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_ranting']); ?></strong></td>
                        <td><span class="badge badge-<?php echo $row['jenis']; ?>"><?php echo strtoupper($row['jenis']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['alamat'] ?? '-', 0, 40)); ?></td>
                        <td><?php echo htmlspecialchars($row['no_kontak'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_kota'] ?? '-'); ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="ranting_detail.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php
                                // Show edit for those with permission
                                // Hierarchy: admin > pengkot > ranting/unit
                                // negara and pengprov cannot edit ranting
                                $show_actions = false;
                                $show_delete = false;
                                
                                if ($user_role === 'admin') {
                                    $show_actions = true;
                                    $show_delete = true;
                                } elseif ($user_role === 'pengkot') {
                                    // Pengkot can edit ranting in their city
                                    $show_actions = ($row['kota_id'] ?? 0) == $user_pengurus_id;
                                    $show_delete = ($row['kota_id'] ?? 0) == $user_pengurus_id;
                                } elseif ($user_role === 'ranting' || $user_role === 'unit') {
                                    // Ranting/unit can only edit their own ranting
                                    $show_actions = ($row['id'] == $user_ranting_id);
                                    $show_delete = false;
                                }
                                
                                if ($show_actions):
                                ?>
                                <a href="ranting_edit.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($show_delete): ?>
                                <a href="ranting_hapus.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        console.log('JavaScript loaded');
        
        $(document).ready(function() {
            console.log('Document ready');
            
            // Check if search element exists
            const searchEl = document.getElementById('search_name');
            console.log('Search element:', searchEl);
            
            // Live search debounce - using vanilla JS
            if (searchEl) {
                let debounceTimer;
                searchEl.addEventListener('input', function() {
                    console.log('Input event fired, value:', this.value);
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        applyFilters();
                    }, 300);
                });
            }
            
            // Bind jenis filter
            const jenisEl = document.getElementById('filter_jenis');
            if (jenisEl) {
                jenisEl.addEventListener('change', function() {
                    console.log('Jenis changed:', this.value);
                    applyFilters();
                });
            }
        });

        function getFilterParams() {
            const search = $('#search_name').val();
            const jenis = $('#filter_jenis').val();
            const negara = $('#filter_negara').val();
            const provinsi = $('#filter_provinsi').val();
            const kota = $('#filter_kota').val();
            
            let params = '';
            if (search) params += `&search=${encodeURIComponent(search)}`;
            if (jenis) params += `&filter_jenis=${jenis}`;
            if (negara) params += `&filter_negara=${negara}`;
            if (provinsi) params += `&filter_provinsi=${provinsi}`;
            if (kota) params += `&filter_kota=${kota}`;
            return params;
        }

        function applyFilters() {
            const params = getFilterParams();
            const url = '?ajax=1' + params;
            
            console.log('Applying filters:', url);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('Response:', data);
                    if (data.success) {
                        updateTable(data.data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateTable(data) {
            const tbody = document.getElementById('ranting-tbody');
            const totalCount = document.getElementById('total-count');
            const isReadonly = <?php echo $is_readonly ? 'true' : 'false'; ?>;
            const userRole = '<?php echo $user_role; ?>';
            const userRantingId = <?php echo (int)$user_ranting_id; ?>;
            const userPengurusId = <?php echo (int)$user_pengurus_id; ?>;
            
            if (!tbody) return;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">🔍 Tidak ada data unit / ranting</td></tr>';
                if (totalCount) totalCount.textContent = '0';
                return;
            }

            if (totalCount) totalCount.textContent = data.length;

            let html = '';
            data.forEach(row => {
                let badgeClass = 'badge-' + row.jenis;
                let actions = `
                    <div class="action-icons">
                        <a href="ranting_detail.php?id=${row.id}" class="icon-btn icon-view" title="Lihat">
                            <i class="fas fa-eye"></i>
                        </a>
                `;
                
                // Permission logic matching PHP:
                // admin: can edit & delete all
                // pengkot: can edit/delete ranting in their kota
                // ranting/unit: can edit their own ranting only, cannot delete
                let showActions = false;
                let showDelete = false;
                
                if (userRole === 'admin') {
                    showActions = true;
                    showDelete = true;
                } else if (userRole === 'pengkot') {
                    // Pengkot can edit/delete ranting in their city
                    showActions = (row.kota_id == userPengurusId);
                    showDelete = (row.kota_id == userPengurusId);
                } else if (userRole === 'ranting' || userRole === 'unit') {
                    // Ranting/unit can only edit their own ranting
                    showActions = (row.id == userRantingId);
                    showDelete = false;
                }
                
                if (showActions) {
                    actions += `
                        <a href="ranting_edit.php?id=${row.id}" class="icon-btn icon-edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    `;
                }
                
                if (showDelete) {
                    actions += `
                        <a href="ranting_hapus.php?id=${row.id}" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    `;
                }
                actions += '</div>';

                html += `
                    <tr>
                        <td><strong>${row.nama_ranting}</strong></td>
                        <td><span class="badge ${badgeClass}">${row.jenis.toUpperCase()}</span></td>
                        <td>${row.ketua_nama}</td>
                        <td>${row.alamat}</td>
                        <td>${row.no_kontak}</td>
                        <td>${row.nama_kota}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

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
                applyFilters();
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
            
            applyFilters();
        }
        
        function updateKota() {
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            
            const provinsiId = provinsiSelect.value;
            
            // Reset and disable dependent dropdown
            kotaSelect.innerHTML = '<option value="">-- Semua Kota --</option>';
            kotaSelect.disabled = true;
            
            if (provinsiId === '') {
                applyFilters();
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
            
            applyFilters();
        }
    </script>
</body>
</html>