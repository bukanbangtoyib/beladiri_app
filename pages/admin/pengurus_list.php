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

$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'pusat';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Filter parameters
$filter_negara = isset($_GET['filter_negara']) ? (int)$_GET['filter_negara'] : 0;
$filter_provinsi = isset($_GET['filter_provinsi']) ? (int)$_GET['filter_provinsi'] : 0;

// Validasi jenis - map old types to new table names
if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

// Map jenis to table and column names
$table_map = [
    'pusat' => ['table' => 'negara', 'label' => 'Negara'],
    'provinsi' => ['table' => 'provinsi', 'label' => 'Provinsi'],
    'kota' => ['table' => 'kota', 'label' => 'Kota/Kabupaten']
];

$table_info = $table_map[$jenis];
$table = $table_info['table'];
$label_jenis = $table_info['label'];

// Query from new tables - for kota, need to JOIN with provinces to get negara_id
if ($jenis == 'kota') {
    $sql = "SELECT k.*, p.negara_id FROM kota k LEFT JOIN provinsi p ON k.provinsi_id = p.id WHERE 1=1";
} else {
    $sql = "SELECT * FROM $table WHERE 1=1";
}

// Apply filters based on jenis
if ($jenis == 'provinsi' && $filter_negara > 0) {
    $sql .= " AND negara_id = " . $filter_negara;
}
if ($jenis == 'kota') {
    if ($filter_provinsi > 0) {
        $sql .= " AND k.provinsi_id = " . $filter_provinsi;
    } elseif ($filter_negara > 0) {
        $sql .= " AND p.negara_id = " . $filter_negara;
    }
}

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND nama LIKE '%$search%'";
}

$sql .= " ORDER BY nama ASC";

$result = $conn->query($sql);
$total = $result->num_rows;

// Get data for filters
$negara_list = [];
$negara_result = $conn->query("SELECT id, nama FROM negara ORDER BY nama");
while ($row = $negara_result->fetch_assoc()) {
    $negara_list[] = $row;
}

$provinsi_list = [];
if ($jenis == 'provinsi' || $jenis == 'kota') {
    $provinsi_where = $filter_negara > 0 ? "WHERE negara_id = " . $filter_negara : "";
    $provinsi_result = $conn->query("SELECT id, nama, negara_id FROM provinsi $provinsi_where ORDER BY nama");
    while ($row = $provinsi_result->fetch_assoc()) {
        $provinsi_list[] = $row;
    }
}

$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $label_jenis; ?> - Sistem Beladiri</title>
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
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { color: #333; }
        
        .breadcrumb { color: #666; margin-bottom: 20px; }
        .breadcrumb a { color: #667eea; text-decoration: none; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-small { padding: 6px 12px; font-size: 12px; margin: 2px; }
        
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        input[type="text"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            flex: 1;
        }
        
        select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        .status-aktif { color: #27ae60; }
        .status-tidak { color: #e74c3c; }
    </style>
</head>
<body>
    <?php renderNavbar('📋 ' . $label_jenis); ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="pengurus.php">Pengurus</a> > <strong><?php echo $label_jenis; ?></strong>
        </div>
        
        <div class="header">
            <div>
                <h1><?php echo $label_jenis; ?></h1>
                <p style="color: #666;">Total: <strong><?php echo $total; ?></strong></p>
            </div>
            <?php if (!$is_readonly): ?>
            <a href="pengurus_tambah.php?jenis=<?php echo $jenis; ?>" class="btn btn-primary">+ Tambah <?php echo $label_jenis; ?></a>
            <?php endif; ?>
        </div>
        
        <div class="search-filter">
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="jenis" value="<?php echo $jenis; ?>">
                
                <?php if ($jenis == 'provinsi' || $jenis == 'kota'): ?>
                <select name="filter_negara" id="filter_negara">
                    <option value="">-- Semua Negara --</option>
                    <?php foreach ($negara_list as $n): ?>
                        <option value="<?php echo $n['id']; ?>" <?php echo $filter_negara == $n['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($n['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <?php if ($jenis == 'kota'): ?>
                <select name="filter_provinsi" id="filter_provinsi">
                    <option value="">-- Semua Provinsi --</option>
                    <?php foreach ($provinsi_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filter_provinsi == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <input type="text" name="search" placeholder="Cari nama..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">🔍 Cari</button>
                <a href="pengurus_list.php?jenis=<?php echo $jenis; ?>" class="btn" style="background: #6c757d; color: white;">Reset</a>
            </form>
        </div>
        
        <div class="table-container">
            <?php if ($total > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Ketua</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): 
                        // Calculate status based on period dates (same logic as detail page)
                        $periode_akhir = $row['periode_akhir'] ?? null;
                        $is_active = !empty($periode_akhir) && strtotime($periode_akhir) >= strtotime(date('Y-m-d'));
                        $status = $is_active ? 'Aktif' : 'Tidak Aktif';
                        $status_class = $is_active ? 'status-aktif' : 'status-tidak';
                        
                        // Get negara_id and provinsi_id for filtering
                        $row_negara_id = $row['negara_id'] ?? 0;
                        $row_provinsi_id = $row['provinsi_id'] ?? 0;
                    ?>
                    <tr data-negara="<?php echo $row_negara_id; ?>" data-provinsi="<?php echo $row_provinsi_id; ?>" data-nama="<?php echo strtolower(htmlspecialchars($row['nama'])); ?>">
                        <td><strong><?php echo htmlspecialchars($row['kode'] ?? '-'); ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td><?php echo date('Y', strtotime($row['periode_mulai'] ?? '2000-01-01')); ?> - <?php echo date('Y', strtotime($row['periode_akhir'] ?? '2000-01-01')); ?></td>
                        <td><span class="<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                        <td>
                            <a href="pengurus_detail.php?id=<?php echo $row['id']; ?>&jenis=<?php echo $jenis; ?>" class="btn btn-info btn-small">Lihat</a>
                            <?php if (!$is_readonly): ?>
                            <a href="pengurus_edit.php?id=<?php echo $row['id']; ?>&jenis=<?php echo $jenis; ?>" class="btn btn-warning btn-small">Edit</a>
                            <a href="pengurus_hapus.php?id=<?php echo $row['id']; ?>&jenis=<?php echo $jenis; ?>" class="btn btn-danger btn-small" onclick="return confirm('Yakin?')">Hapus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">📭 Tidak ada data <?php echo strtolower($label_jenis); ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const negaraSelect = document.getElementById('filter_negara');
        const provinsiSelect = document.getElementById('filter_provinsi');
        const searchInput = document.querySelector('input[name="search"]');
        
        function filterTable() {
            const negaraId = negaraSelect ? negaraSelect.value : '';
            const provinsiId = provinsiSelect ? provinsiSelect.value : '';
            const searchText = searchInput ? searchInput.value.toLowerCase() : '';
            
            console.log('=== FILTER DEBUG ===');
            console.log('negaraId:', negaraId, '| provinsiId:', provinsiId, '| searchText:', searchText);
            
            // Filter table rows
            let visibleCount = 0;
            const rows = document.querySelectorAll('tbody tr');
            console.log('Total rows:', rows.length);
            
            rows.forEach(row => {
                const rowNegara = row.dataset.negara || '';
                const rowProvinsi = row.dataset.provinsi || '';
                const rowNama = row.dataset.nama || '';
                
                console.log('Row - negara:', rowNegara, '| provinsi:', rowProvinsi, '| nama:', rowNama.substring(0, 20));
                
                let show = true;
                
                // Check negara filter
                if (negaraId && rowNegara && rowNegara !== negaraId) {
                    console.log('  -> HIDE: negara mismatch');
                    show = false;
                }
                
                // Check provinsi filter - only if province is specifically selected
                if (show && provinsiId && rowProvinsi && rowProvinsi !== provinsiId) {
                    console.log('  -> HIDE: provinsi mismatch');
                    show = false;
                }
                
                // Check search
                if (show && searchText && !rowNama.includes(searchText)) {
                    console.log('  -> HIDE: search mismatch');
                    show = false;
                }
                
                if (show) {
                    visibleCount++;
                }
                row.style.display = show ? '' : 'none';
            });
            
            console.log('Visible count:', visibleCount);
            console.log('===================');
            
            // Update count
            const totalEl = document.querySelector('.header p strong');
            if (totalEl) {
                totalEl.textContent = visibleCount;
            }
        }
        
        // Update provinces dropdown based on selected negara
        function updateProvinsi() {
            const negaraId = negaraSelect ? negaraSelect.value : '';
            
            // Reset province dropdown to default
            if (provinsiSelect) {
                provinsiSelect.innerHTML = '<option value="">-- Semua Provinsi --</option>';
            }
            
            // Reset search input when negara changes
            if (searchInput) {
                searchInput.value = '';
            }
            
            if (!negaraId || !provinsiSelect) {
                filterTable();
                return;
            }
            
            // Fetch provinces by negara
            fetch('../../api/manage_provinsi.php?action=get_by_negara&id_negara=' + negaraId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(prov => {
                            const option = document.createElement('option');
                            option.value = prov.id;
                            option.textContent = prov.nama;
                            provinsiSelect.appendChild(option);
                        });
                    }
                    filterTable();
                })
                .catch(error => {
                    console.error('Error fetching provinces:', error);
                    filterTable();
                });
        }
        
        // Auto-filter when negara changes
        if (negaraSelect) {
            negaraSelect.addEventListener('change', function() {
                // For kota: update provinces (which will then filter table)
                if (provinsiSelect) {
                    updateProvinsi();
                }
                // For provinsi: just filter directly
                if (!provinsiSelect) {
                    filterTable();
                }
            });
        }
        
        // Auto-filter when provinsi changes
        if (provinsiSelect) {
            provinsiSelect.addEventListener('change', filterTable);
        }
        
        // Auto-filter when search changes (with debounce)
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(filterTable, 300);
            });
        }
    });
    </script>
</body>
</html>
