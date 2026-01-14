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

// Ambil data UKT dengan statistik
$sql = "SELECT u.*, 
        COUNT(up.id) as total_peserta,
        SUM(CASE WHEN up.status = 'lulus' THEN 1 ELSE 0 END) as peserta_lulus,
        SUM(CASE WHEN up.status = 'tidak_lulus' THEN 1 ELSE 0 END) as peserta_tidak_lulus
        FROM ukt u
        LEFT JOIN ukt_peserta up ON u.id = up.ukt_id
        GROUP BY u.id
        ORDER BY u.tanggal_pelaksanaan DESC";

$result = $conn->query($sql);
$total_ukt = $result->num_rows;

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
        .btn-success { background: #28a745; color: white; }
        .btn-small { padding: 6px 12px; font-size: 12px; margin: 2px; }
        
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
        
        .badge { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-completed { background: #d4edda; color: #155724; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        .stat-number { font-weight: 700; }
        .stat-lulus { color: #27ae60; }
        .stat-tidak { color: #e74c3c; }
    </style>
</head>
<body>
    <?php renderNavbar('📝 Ujian Kenaikan Tingkat (UKT'); ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Pelaksanaan UKT</h1>
                <p style="color: #666;">Total: <strong><?php echo $total_ukt; ?> pelaksanaan</strong></p>
            </div>
            <?php if (!$is_readonly): ?>
            <a href="ukt_buat.php" class="btn btn-primary">+ Buat UKT Baru</a>
            <?php endif; ?>
        </div>
        
        <div class="table-container">
            <?php if ($total_ukt > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Total Peserta</th>
                        <th>Lulus / Tidak Lulus</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo date('d-m-Y', strtotime($row['tanggal_pelaksanaan'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                        <td><?php echo $row['total_peserta'] ?? 0; ?></td>
                        <td>
                            <span class="stat-number stat-lulus">✓ <?php echo $row['peserta_lulus'] ?? 0; ?></span> / 
                            <span class="stat-number stat-tidak">✗ <?php echo $row['peserta_tidak_lulus'] ?? 0; ?></span>
                        </td>
                        <td><span class="badge badge-completed">✓ Selesai</span></td>
                        <td>
                            <a href="ukt_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-small">Lihat</a>
                            <?php if (!$is_readonly): ?>
                            <a href="ukt_input_nilai.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-small">Input Nilai</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">📭 Belum ada data UKT</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>