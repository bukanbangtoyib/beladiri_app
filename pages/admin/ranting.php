<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_jenis = isset($_GET['filter_jenis']) ? $_GET['filter_jenis'] : '';

$sql = "SELECT r.*, p.nama_pengurus FROM ranting r 
        LEFT JOIN pengurus p ON r.pengurus_kota_id = p.id
        WHERE 1=1";

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (r.nama_ranting LIKE '%$search%' OR r.alamat LIKE '%$search%')";
}

if ($filter_jenis) {
    $filter_jenis = $conn->real_escape_string($filter_jenis);
    $sql .= " AND r.jenis = '$filter_jenis'";
}

$sql .= " ORDER BY r.nama_ranting";

$result = $conn->query($sql);
$total_ranting = $result->num_rows;

$pengurus_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' ORDER BY nama_pengurus");
$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Unit/Ranting - Sistem Beladiri</title>
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
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-ukm { background: #e3f2fd; color: #1976d2; }
        .badge-ranting { background: #f3e5f5; color: #7b1fa2; }
        .badge-unit { background: #fff3e0; color: #e65100; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🏢 Manajemen Unit / Ranting</h2>
        <div>
            <span style="margin-right: 20px;">Halo, <?php echo $_SESSION['nama']; ?></span>
            <a href="../../index.php" style="color: white;">← Kembali</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Unit / Ranting</h1>
                <p style="color: #666;">Total: <strong><?php echo $total_ranting; ?></strong></p>
            </div>
            <?php if (!$is_readonly): ?>
            <a href="ranting_tambah.php" class="btn btn-primary">+ Tambah Unit/Ranting</a>
            <?php endif; ?>
        </div>
        
        <div class="search-filter">
            <form method="GET">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Cari nama atau alamat..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="filter_jenis">
                        <option value="">-- Semua Jenis --</option>
                        <option value="ukm" <?php echo $filter_jenis == 'ukm' ? 'selected' : ''; ?>>UKM</option>
                        <option value="ranting" <?php echo $filter_jenis == 'ranting' ? 'selected' : ''; ?>>Ranting</option>
                        <option value="unit" <?php echo $filter_jenis == 'unit' ? 'selected' : ''; ?>>Unit</option>
                    </select>
                </div>
                
                <div class="filter-row">
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="ranting.php" class="btn" style="background: #6c757d; color: white;">Reset</a>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if ($total_ranting > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Unit/Ranting</th>
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
                        <td>
                            <span class="badge badge-<?php echo $row['jenis']; ?>">
                                <?php echo strtoupper($row['jenis']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['alamat'] ?? '-', 0, 50)); ?></td>
                        <td><?php echo htmlspecialchars($row['no_kontak'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_pengurus'] ?? '-'); ?></td>
                        <td>
                            <a href="ranting_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small">Lihat</a>
                            <?php if (!$is_readonly): ?>
                            <a href="ranting_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                            <a href="ranting_hapus.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Yakin?')">Hapus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">📭 Tidak ada data unit/ranting</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>