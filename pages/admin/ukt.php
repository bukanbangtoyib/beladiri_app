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

// Check if user has any UKT access (Unit and Tamu have no access)
$ukt_filter = $permission_manager->getUKTFilterSQL();
if ($ukt_filter['where'] === '1=0') {
    die("❌ Akses ditolak! Anda tidak memiliki izin untuk mengakses halaman ini.");
}

// Handle AJAX request untuk filter
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $tahun = isset($_GET['tahun']) ? $conn->real_escape_string(trim($_GET['tahun'])) : '';
    $lokasi = isset($_GET['lokasi']) ? $conn->real_escape_string(trim($_GET['lokasi'])) : '';
    $jenis_peny = isset($_GET['jenis_peny']) ? $conn->real_escape_string(trim($_GET['jenis_peny'])) : '';
    $peny_id = isset($_GET['peny_id']) ? (int)$_GET['peny_id'] : 0;
    
    // Get filter based on user role
    $ukt_filter = $permission_manager->getUKTFilterSQL();
    
    $sql = "SELECT u.id, u.tanggal_pelaksanaan, u.lokasi, 
            COALESCE(n.nama, p.nama, k.nama) as nama_penyelenggara,
            COUNT(up.id) as total_peserta,
            SUM(CASE WHEN up.status = 'lulus' THEN 1 ELSE 0 END) as peserta_lulus,
            SUM(CASE WHEN up.status = 'tidak_lulus' THEN 1 ELSE 0 END) as peserta_tidak_lulus
            FROM ukt u
            LEFT JOIN negara n ON u.penyelenggara_id = n.id AND u.jenis_penyelenggara = 'pusat'
            LEFT JOIN provinsi p ON u.penyelenggara_id = p.id AND u.jenis_penyelenggara = 'provinsi'
            LEFT JOIN kota k ON u.penyelenggara_id = k.id AND u.jenis_penyelenggara = 'kota'
            LEFT JOIN ukt_peserta up ON u.id = up.ukt_id
            WHERE {$ukt_filter['where']}";
    
    if ($tahun) {
        $sql .= " AND YEAR(u.tanggal_pelaksanaan) = '$tahun'";
    }
    
    if ($lokasi) {
        $sql .= " AND u.lokasi LIKE '%$lokasi%'";
    }
    
    if ($jenis_peny) {
        $sql .= " AND u.jenis_penyelenggara = '$jenis_peny'";
    }
    
    if ($peny_id > 0) {
        $sql .= " AND u.penyelenggara_id = $peny_id";
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.tanggal_pelaksanaan DESC";
    
    // Execute with params if available
    if (!empty($ukt_filter['params'])) {
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($ukt_filter['params']));
        $stmt->bind_param($types, ...$ukt_filter['params']);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $rows = [];
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'tanggal' => date('d-m-Y', strtotime($row['tanggal_pelaksanaan'])),
            'lokasi' => htmlspecialchars($row['lokasi']),
            'penyelenggara' => htmlspecialchars($row['nama_penyelenggara'] ?? '-'),
            'total_peserta' => (int)($row['total_peserta'] ?? 0),
            'peserta_lulus' => (int)($row['peserta_lulus'] ?? 0),
            'peserta_tidak_lulus' => (int)($row['peserta_tidak_lulus'] ?? 0)
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// Query awal untuk semua data UKT dengan filter berdasarkan role
$ukt_filter = $permission_manager->getUKTFilterSQL();
$sql = "SELECT u.*, COALESCE(n.nama, p.nama, k.nama) as nama_penyelenggara
        FROM ukt u
        LEFT JOIN negara n ON u.penyelenggara_id = n.id AND u.jenis_penyelenggara = 'pusat'
        LEFT JOIN provinsi p ON u.penyelenggara_id = p.id AND u.jenis_penyelenggara = 'provinsi'
        LEFT JOIN kota k ON u.penyelenggara_id = k.id AND u.jenis_penyelenggara = 'kota'
        WHERE {$ukt_filter['where']}
        ORDER BY u.tanggal_pelaksanaan DESC";

// Prepare statement if there are params
if (!empty($ukt_filter['params'])) {
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($ukt_filter['params']));
    $stmt->bind_param($types, ...$ukt_filter['params']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Hitung total
$total_ukt = $result->num_rows;
$result->data_seek(0);

// Ambil data tahun untuk dropdown filter
$tahun_result = $conn->query("SELECT DISTINCT YEAR(tanggal_pelaksanaan) as tahun FROM ukt ORDER BY tahun DESC");

$can_create_info = $permission_manager->canCreateOwnUKT();
$can_create = $can_create_info['can'];
$can_update = $permission_manager->canUpdateOwnUKT();

// Default: read-only unless can_update is true
$is_readonly = !$can_update;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UKT - Sistem Beladiri</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 { color: #333; }
        
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 13px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
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
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
        .btn-small { padding: 6px 12px; font-size: 12px; margin: 2px; }
        
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
            padding: 6px 12px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 600; 
        }
        
        .badge-completed { background: #d4edda; color: #155724; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        .stat-number { font-weight: 700; }
        .stat-lulus { color: #27ae60; }
        .stat-tidak { color: #e74c3c; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        /* Select2 alignment */
        .select2-container--default .select2-selection--single {
            height: 38px;
            padding: 4px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
            padding-left: 0;
            font-size: 13px;
            color: #333;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #999;
        }
    </style>
</head>
<body>
    <?php renderNavbar('📝 Ujian Kenaikan Tingkat (UKT)'); ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Pelaksanaan UKT</h1>
                <p style="color: #666;">Total: <strong id="total-count"><?php echo $total_ukt; ?> pelaksanaan</strong></p>
            </div>
            <?php if ($can_create): ?>
            <a href="ukt_buat.php" class="btn btn-primary">+ Buat UKT Baru</a>
            <?php endif; ?>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-container">
            <h3 style="margin-bottom: 15px; color: #333;">🔍 Filter Data</h3>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>Tahun Pelaksanaan</label>
                    <select id="filter-tahun">
                        <option value="">-- Semua Tahun --</option>
                        <?php while ($row = $tahun_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['tahun']; ?>"><?php echo $row['tahun']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Lokasi (Pencarian Langsung)</label>
                    <input type="text" id="filter-lokasi" placeholder="Ketik untuk mencari lokasi...">
                </div>
                
                <div class="filter-group">
                    <label>Jenis Penyelenggara</label>
                    <select id="filter-jenis-penyelenggara" onchange="handleJenisFilterChange()">
                        <option value="">-- Semua Jenis --</option>
                        <option value="pusat">Pusat (PP)</option>
                        <option value="provinsi">Provinsi (PengProv)</option>
                        <option value="kota">Kota / Kabupaten (PengKot)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Nama Penyelenggara</label>
                    <select id="filter-nama-penyelenggara" disabled>
                        <option value="">-- Pilih Penyelenggara --</option>
                    </select>
                    <div id="loadingFilterPenyelenggara" style="display:none; font-size:10px; color:#999;">Memuat...</div>
                </div>
            </div>
            
            <div class="filter-buttons">
                <button class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px;" onclick="resetFilters()">🔄 Reset Filter</button>
            </div>
        </div>
        
        <!-- Table Section -->
        <div class="table-container">
            <table id="ukt-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Penyelenggara</th>
                        <th>Total Peserta</th>
                        <th>Lulus / Tidak Lulus</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="ukt-tbody">
                    <?php while ($row = $result->fetch_assoc()): 
                        // Ambil statistik untuk setiap row
                        $stat_sql = "SELECT 
                            COUNT(id) as total_peserta,
                            SUM(CASE WHEN status = 'lulus' THEN 1 ELSE 0 END) as peserta_lulus,
                            SUM(CASE WHEN status = 'tidak_lulus' THEN 1 ELSE 0 END) as peserta_tidak_lulus
                            FROM ukt_peserta WHERE ukt_id = " . $row['id'];
                        $stat_result = $conn->query($stat_sql);
                        $stats = $stat_result->fetch_assoc();
                    ?>
                    <tr>
                        <td><strong><?php echo date('d-m-Y', strtotime($row['tanggal_pelaksanaan'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                        <td>
                            <?php if ($row['nama_penyelenggara']): ?>
                                <?php echo htmlspecialchars($row['nama_penyelenggara']); ?>
                            <?php else: ?>
                                <em style="color: #999;">-</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $stats['total_peserta'] ?? 0; ?></td>
                        <td>
                            <span class="stat-number stat-lulus">✓ <?php echo $stats['peserta_lulus'] ?? 0; ?></span> / 
                            <span class="stat-number stat-tidak">✗ <?php echo $stats['peserta_tidak_lulus'] ?? 0; ?></span>
                        </td>
                        <td><span class="badge badge-completed">✓ Selesai</span></td>
                        <td>
                            <div class="action-buttons">
                                <a href="ukt_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small">Lihat</a>
                                <?php 
                                $can_manage_this = $permission_manager->canManageUKT('ukt_update', $row['jenis_penyelenggara'], $row['penyelenggara_id']);
                                if ($can_manage_this): 
                                ?>
                                <a href="ukt_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small" style="background: #ffc107; color: black;">Edit</a>
                                <a href="ukt_hapus.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small" style="background: #dc3545;" onclick="return confirm('Yakin hapus UKT ini?')">Hapus</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="no-data" id="no-data" style="display: none;">📭 Tidak ada data UKT yang sesuai</div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Init Select2 for Year
            $('#filter-tahun').select2({
                placeholder: "-- Semua Tahun --",
                allowClear: true,
                width: '100%'
            }).on('change', applyFilters);

            // Init Select2 for Organizer Name
            $('#filter-nama-penyelenggara').select2({
                placeholder: "-- Pilih Penyelenggara --",
                allowClear: true,
                width: '100%'
            }).on('change', applyFilters);

            // Focus search on open
            $('#filter-tahun, #filter-nama-penyelenggara').on('select2:open', function() {
                const searchField = document.querySelector('.select2-search__field');
                if (searchField) searchField.focus();
            });

            // Live search for Location (with debounce)
            let searchTimeout;
            $('#filter-lokasi').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(applyFilters, 400);
            });
            
            // Apply initial filters if any or just trigger AJAX
            // applyFilters();
        });

        function handleJenisFilterChange() {
            const jenis = document.getElementById('filter-jenis-penyelenggara').value;
            const namaSelect = document.getElementById('filter-nama-penyelenggara');
            const loading = document.getElementById('loadingFilterPenyelenggara');
            
            namaSelect.innerHTML = '<option value="">-- Semua Penyelenggara --</option>';
            namaSelect.disabled = true;
            
            if (!jenis) {
                $(namaSelect).trigger('change');
                return;
            }
            
            loading.style.display = 'block';
            fetch(`../../api/get_penyelenggara.php?jenis_pengurus=${encodeURIComponent(jenis)}`)
                .then(r => r.json())
                .then(data => {
                    loading.style.display = 'none';
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.nama;
                            namaSelect.appendChild(opt);
                        });
                        namaSelect.disabled = false;
                    }
                    $(namaSelect).trigger('change');
                });
        }

        function applyFilters() {
            const tahun = document.getElementById('filter-tahun').value;
            const lokasi = document.getElementById('filter-lokasi').value;
            const jenisPeny = document.getElementById('filter-jenis-penyelenggara').value;
            const penyId = document.getElementById('filter-nama-penyelenggara').value;
            
            let url = '?ajax=1';
            if (tahun) url += `&tahun=${encodeURIComponent(tahun)}`;
            if (lokasi) url += `&lokasi=${encodeURIComponent(lokasi)}`;
            if (jenisPeny) url += `&jenis_peny=${encodeURIComponent(jenisPeny)}`;
            if (penyId) url += `&peny_id=${encodeURIComponent(penyId)}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTable(data.data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function updateTable(data) {
            const tbody = document.getElementById('ukt-tbody');
            const noData = document.getElementById('no-data');
            const totalCount = document.getElementById('total-count');
            
            if (data.length === 0) {
                tbody.innerHTML = '';
                noData.style.display = 'block';
                totalCount.textContent = '0 pelaksanaan';
                return;
            }
            
            noData.style.display = 'none';
            totalCount.textContent = data.length + ' pelaksanaan';
            
            let html = '';
            data.forEach(row => {
                html += `
                    <tr>
                        <td><strong>${row.tanggal}</strong></td>
                        <td>${row.lokasi}</td>
                        <td>${row.penyelenggara}</td>
                        <td>${row.total_peserta}</td>
                        <td>
                            <span class="stat-number stat-lulus">✓ ${row.peserta_lulus}</span> / 
                            <span class="stat-number stat-tidak">✗ ${row.peserta_tidak_lulus}</span>
                        </td>
                        <td><span class="badge badge-completed">✓ Selesai</span></td>
                        <td>
                            <div class="action-buttons">
                                <a href="ukt_detail.php?id=${row.id}" class="btn btn-info btn-small">Lihat</a>
                                <a href="ukt_edit.php?id=${row.id}" class="btn btn-info btn-small" style="background: #ffc107; color: black;">Edit</a>
                                <a href="ukt_hapus.php?id=${row.id}" class="btn btn-info btn-small" style="background: #dc3545;" onclick="return confirm('Yakin hapus UKT ini?')">Hapus</a>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        function resetFilters() {
            $('#filter-tahun').val('').trigger('change.select2');
            document.getElementById('filter-lokasi').value = '';
            document.getElementById('filter-jenis-penyelenggara').value = '';
            $('#filter-nama-penyelenggara').html('<option value="">-- Pilih Penyelenggara --</option>').val('').trigger('change.select2').prop('disabled', true);
            applyFilters();
        }
    </script>
</body>
</html>