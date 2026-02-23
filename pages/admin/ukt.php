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

// Handle AJAX request untuk filter
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $tahun = isset($_GET['tahun']) ? $conn->real_escape_string(trim($_GET['tahun'])) : '';
    $lokasi = isset($_GET['lokasi']) ? $conn->real_escape_string(trim($_GET['lokasi'])) : '';
    $penyelenggara = isset($_GET['penyelenggara']) ? $conn->real_escape_string(trim($_GET['penyelenggara'])) : '';
    
    $sql = "SELECT u.id, u.tanggal_pelaksanaan, u.lokasi, p.nama as nama_penyelenggara,
            COUNT(up.id) as total_peserta,
            SUM(CASE WHEN up.status = 'lulus' THEN 1 ELSE 0 END) as peserta_lulus,
            SUM(CASE WHEN up.status = 'tidak_lulus' THEN 1 ELSE 0 END) as peserta_tidak_lulus
            FROM ukt u
            LEFT JOIN kota p ON u.penyelenggara_id = p.id
            LEFT JOIN ukt_peserta up ON u.id = up.ukt_id
            WHERE 1=1";
    
    if ($tahun) {
        $sql .= " AND YEAR(u.tanggal_pelaksanaan) = '$tahun'";
    }
    
    if ($lokasi) {
        $sql .= " AND u.lokasi LIKE '%$lokasi%'";
    }
    
    if ($penyelenggara) {
        $sql .= " AND p.nama LIKE '%$penyelenggara%'";
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.tanggal_pelaksanaan DESC";
    
    $result = $conn->query($sql);
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

// Query awal untuk semua data UKT
$sql = "SELECT u.*, p.nama as nama_penyelenggara
        FROM ukt u
        LEFT JOIN kota p ON u.penyelenggara_id = p.id
        ORDER BY u.tanggal_pelaksanaan DESC";
$result = $conn->query($sql);

// Hitung total
$total_ukt = $result->num_rows;
$result->data_seek(0);

// Ambil data tahun untuk dropdown filter
$tahun_result = $conn->query("SELECT DISTINCT YEAR(tanggal_pelaksanaan) as tahun FROM ukt ORDER BY tahun DESC");

$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UKT - Sistem Beladiri</title>
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
            <?php if (!$is_readonly): ?>
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
                    <label>Lokasi</label>
                    <input type="text" id="filter-lokasi" placeholder="Cari lokasi...">
                </div>
                
                <div class="filter-group">
                    <label>Penyelenggara</label>
                    <input type="text" id="filter-penyelenggara" placeholder="Cari penyelenggara...">
                </div>
            </div>
            
            <div class="filter-buttons">
                <button class="btn btn-primary" onclick="applyFilters()">🔎 Terapkan Filter</button>
                <button class="btn btn-secondary" onclick="resetFilters()">🔄 Reset Filter</button>
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
                                <?php if (!$is_readonly && $_SESSION['role'] == 'admin'): ?>
                                <a href="ukt_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small" style="background: #ffc107; color: black;">Edit</a>
                                <a href="ukt_hapus.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small" style="background: #dc3545;" onclick="return confirm('Yakin hapus UKT ini?')">Hapus</a>
                                <?php elseif (!$is_readonly): ?>
                                <a href="ukt_input_nilai.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-small">Input Nilai</a>
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
    
    <script>
        function applyFilters() {
            const tahun = document.getElementById('filter-tahun').value;
            const lokasi = document.getElementById('filter-lokasi').value;
            const penyelenggara = document.getElementById('filter-penyelenggara').value;
            
            let url = '?ajax=1';
            if (tahun) url += `&tahun=${encodeURIComponent(tahun)}`;
            if (lokasi) url += `&lokasi=${encodeURIComponent(lokasi)}`;
            if (penyelenggara) url += `&penyelenggara=${encodeURIComponent(penyelenggara)}`;
            
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
            document.getElementById('filter-tahun').value = '';
            document.getElementById('filter-lokasi').value = '';
            document.getElementById('filter-penyelenggara').value = '';
            location.reload();
        }
    </script>
</body>
</html>