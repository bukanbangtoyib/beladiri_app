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

$GLOBALS['permission_manager'] = $permission_manager;

if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Handle AJAX request untuk filter
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $anggota = isset($_GET['anggota']) ? $conn->real_escape_string(trim($_GET['anggota'])) : '';
    $penyelenggara = isset($_GET['penyelenggara']) ? $conn->real_escape_string(trim($_GET['penyelenggara'])) : '';
    $pembuka = isset($_GET['pembuka']) ? $conn->real_escape_string(trim($_GET['pembuka'])) : '';
    
    $sql = "SELECT k.id, k.tanggal_pembukaan, k.lokasi, k.pembuka_nama, k.penyelenggara,
                   a.nama_lengkap, a.no_anggota, t.nama_tingkat, t_pembuka.nama_tingkat as tingkat_pembuka_nama
            FROM kerohanian k
            JOIN anggota a ON k.anggota_id = a.id
            LEFT JOIN tingkatan t ON a.tingkat_id = t.id
            LEFT JOIN tingkatan t_pembuka ON k.tingkat_pembuka_id = t_pembuka.id
            WHERE 1=1";
    
    if ($anggota) {
        $sql .= " AND a.nama_lengkap LIKE '%$anggota%'";
    }
    
    if ($penyelenggara) {
        $sql .= " AND k.penyelenggara LIKE '%$penyelenggara%'";
    }
    
    if ($pembuka) {
        $sql .= " AND k.pembuka_nama LIKE '%$pembuka%'";
    }
    
    $sql .= " ORDER BY k.tanggal_pembukaan DESC";
    
    $result = $conn->query($sql);
    $rows = [];
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'tanggal' => date('d-m-Y', strtotime($row['tanggal_pembukaan'])),
            'no_anggota' => htmlspecialchars($row['no_anggota']),
            'nama_anggota' => htmlspecialchars($row['nama_lengkap']),
            'tingkat' => htmlspecialchars($row['nama_tingkat'] ?? '-'),
            'lokasi' => htmlspecialchars($row['lokasi'] ?? '-'),
            'penyelenggara' => htmlspecialchars($row['penyelenggara'] ?? '-'),
            'pembuka_nama' => htmlspecialchars($row['pembuka_nama'] ?? '-'),
            'tingkat_pembuka' => htmlspecialchars($row['tingkat_pembuka_nama'] ?? '-')
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

$sql = "SELECT k.*, a.nama_lengkap, a.no_anggota, a.tingkat_id, t.nama_tingkat, r.nama_ranting, t_pembuka.nama_tingkat as tingkat_pembuka_nama
        FROM kerohanian k
        JOIN anggota a ON k.anggota_id = a.id
        LEFT JOIN tingkatan t ON a.tingkat_id = t.id
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id
        LEFT JOIN tingkatan t_pembuka ON k.tingkat_pembuka_id = t_pembuka.id
        ORDER BY k.tanggal_pembukaan DESC";

$result = $conn->query($sql);
$total_kerohanian = $result->num_rows;

$is_readonly = $_SESSION['role'] == 'user';

function formatTanggal($date) {
    if (empty($date)) return '-';
    return date('d-m-Y', strtotime($date));
}

function singkatTingkat($nama_tingkat) {
    $singkat = [
        'Dasar I' => 'DI', 'Dasar II' => 'DII', 'Calon Keluarga' => 'Cakel',
        'Putih' => 'P', 'Putih Hijau' => 'PH', 'Hijau' => 'H', 'Hijau Biru' => 'HB',
        'Biru' => 'B', 'Biru Merah' => 'BM', 'Merah' => 'M', 'Merah Kuning' => 'MK',
        'Kuning' => 'K/PM', 'Pendekar' => 'PKE'
    ];
    return isset($singkat[$nama_tingkat]) ? $singkat[$nama_tingkat] : $nama_tingkat;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kerohanian - Sistem Beladiri</title>
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
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 { color: #333; }
        
        .header-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            margin-top: 10px;
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
        
        tr:hover { background: #f9f9f9; }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
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
</head>
<body>
    <?php renderNavbar('🙏 Manajemen Kerohanian'); ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Pembukaan Kerohanian</h1>
                <p style="color: #666;">Total: <strong id="total-count"><?php echo $total_kerohanian; ?></strong> data</p>
            </div>
            <?php if (!$is_readonly): ?>
            <div class="header-right">
                <a href="kerohanian_tambah.php" class="btn btn-primary">+ Tambah Kerohanian</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-container">
            <h3 style="margin-bottom: 15px; color: #333;">🔍 Filter Data</h3>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>Nama Anggota</label>
                    <input type="text" id="filter-anggota" placeholder="Cari nama anggota...">
                </div>
                
                <div class="filter-group">
                    <label>Penyelenggara</label>
                    <input type="text" id="filter-penyelenggara" placeholder="Cari penyelenggara...">
                </div>
                
                <div class="filter-group">
                    <label>Nama Pembuka</label>
                    <input type="text" id="filter-pembuka" placeholder="Cari pembuka...">
                </div>
            </div>
            
            <div class="filter-buttons">
                <button class="btn btn-primary" style="padding: 8px 16px; font-size: 12px;" onclick="applyFilters()">🔎 Terapkan Filter</button>
                <button class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px;" onclick="resetFilters()">🔄 Reset Filter</button>
            </div>
        </div>
        
        <!-- Table Section -->
        <div class="table-container">
            <table id="kerohanian-table">
                <thead>
                    <tr>
                        <th>No. Anggota</th>
                        <th>Nama Anggota</th>
                        <th>Tingkat</th>
                        <th>Tgl Pembukaan</th>
                        <th>Penyelenggara</th>
                        <th>Lokasi</th>
                        <th>Pembuka</th>
                        <th>Tingkat Pembuka</th>
                        <th>Unit/Ranting</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="kerohanian-tbody">
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['no_anggota']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td><span class="badge"><?php echo singkatTingkat($row['nama_tingkat'] ?? '-'); ?></span></td>
                        <td><?php echo formatTanggal($row['tanggal_pembukaan']); ?></td>
                        <td><?php echo htmlspecialchars($row['penyelenggara'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['pembuka_nama'] ?? '-'); ?></td>
                        <td><span class="badge"><?php echo singkatTingkat($row['tingkat_pembuka_nama'] ?? '-'); ?></span></td>
                        <td><?php echo htmlspecialchars($row['nama_ranting'] ?? '-'); ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="kerohanian_detail.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!$is_readonly): ?>
                                <a href="kerohanian_edit.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="kerohanian_hapus.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus data ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="no-data" id="no-data" style="display: none;">🔭 Tidak ada data kerohanian</div>
        </div>
    </div>
    
    <script>
        function applyFilters() {
            const anggota = document.getElementById('filter-anggota').value;
            const penyelenggara = document.getElementById('filter-penyelenggara').value;
            const pembuka = document.getElementById('filter-pembuka').value;
            
            let url = '?ajax=1';
            if (anggota) url += `&anggota=${encodeURIComponent(anggota)}`;
            if (penyelenggara) url += `&penyelenggara=${encodeURIComponent(penyelenggara)}`;
            if (pembuka) url += `&pembuka=${encodeURIComponent(pembuka)}`;
            
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
            const tbody = document.getElementById('kerohanian-tbody');
            const noData = document.getElementById('no-data');
            const totalCount = document.getElementById('total-count');
            
            if (data.length === 0) {
                tbody.innerHTML = '';
                noData.style.display = 'block';
                totalCount.textContent = '0';
                return;
            }
            
            noData.style.display = 'none';
            totalCount.textContent = data.length;
            
            let html = '';
            data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.no_anggota}</td>
                        <td>${row.nama_anggota}</td>
                        <td><span class="badge">${row.tingkat}</span></td>
                        <td>${row.tanggal}</td>
                        <td>${row.penyelenggara}</td>
                        <td>${row.lokasi}</td>
                        <td>${row.pembuka_nama}</td>
                        <td><span class="badge">${row.tingkat_pembuka}</span></td>
                        <td>-</td>
                        <td>
                            <div class="action-icons">
                                <a href="kerohanian_detail.php?id=${row.id}" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="kerohanian_edit.php?id=${row.id}" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="kerohanian_hapus.php?id=${row.id}" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus data ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        function resetFilters() {
            document.getElementById('filter-anggota').value = '';
            document.getElementById('filter-penyelenggara').value = '';
            document.getElementById('filter-pembuka').value = '';
            location.reload();
        }
    </script>
</body>
</html>