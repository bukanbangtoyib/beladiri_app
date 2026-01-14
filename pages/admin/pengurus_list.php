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

// Validasi jenis
if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

// Ambil label jenis
$label_jenis = [
    'pusat' => 'Pengurus Pusat',
    'provinsi' => 'Pengurus Provinsi',
    'kota' => 'Pengurus Kota'
];

$sql = "SELECT * FROM pengurus WHERE jenis_pengurus = '$jenis'";

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (nama_pengurus LIKE '%$search%' OR ketua_nama LIKE '%$search%')";
}

$sql .= " ORDER BY periode_akhir DESC, nama_pengurus";

$result = $conn->query($sql);
$total = $result->num_rows;

$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $label_jenis[$jenis]; ?> - Sistem Beladiri</title>
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
    <?php renderNavbar('📋 ' . $label_jenis[$jenis]); ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="pengurus.php">Pengurus</a> > <strong><?php echo $label_jenis[$jenis]; ?></strong>
        </div>
        
        <div class="header">
            <div>
                <h1><?php echo $label_jenis[$jenis]; ?></h1>
                <p style="color: #666;">Total: <strong><?php echo $total; ?></strong></p>
            </div>
            <?php if (!$is_readonly): ?>
            <a href="pengurus_tambah.php?jenis=<?php echo $jenis; ?>" class="btn btn-primary">+ Tambah <?php echo $label_jenis[$jenis]; ?></a>
            <?php endif; ?>
        </div>
        
        <div class="search-filter">
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="jenis" value="<?php echo $jenis; ?>">
                <input type="text" name="search" placeholder="Cari nama pengurus atau ketua..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">🔍 Cari</button>
                <a href="pengurus_list.php?jenis=<?php echo $jenis; ?>" class="btn" style="background: #6c757d; color: white;">Reset</a>
            </form>
        </div>
        
        <div class="table-container">
            <?php if ($total > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Pengurus</th>
                        <th>Ketua</th>
                        <th>Periode</th>
                        <th>SK No</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): 
                        $status = (strtotime($row['periode_akhir']) >= strtotime(date('Y-m-d'))) ? 'Aktif' : 'Tidak Aktif';
                        $status_class = $status == 'Aktif' ? 'status-aktif' : 'status-tidak';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_pengurus']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td><?php echo date('Y', strtotime($row['periode_mulai'])); ?> - <?php echo date('Y', strtotime($row['periode_akhir'])); ?></td>
                        <td><?php echo htmlspecialchars($row['sk_kepengurusan'] ?? '-'); ?></td>
                        <td><span class="<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                        <td>
                            <a href="pengurus_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small">Lihat</a>
                            <?php if (!$is_readonly): ?>
                            <a href="pengurus_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                            <a href="pengurus_hapus.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Yakin?')">Hapus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">📭 Tidak ada data <?php echo strtolower($label_jenis[$jenis]); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>