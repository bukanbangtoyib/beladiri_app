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

$id = (int)$_GET['id'];

// Ambil data UKT
$ukt_result = $conn->query("SELECT * FROM ukt WHERE id = $id");
if ($ukt_result->num_rows == 0) {
    die("UKT tidak ditemukan!");
}

$ukt = $ukt_result->fetch_assoc();

// Ambil data peserta UKT
$peserta_sql = "SELECT up.*, a.nama_lengkap, a.no_anggota, t1.nama_tingkat as tingkat_dari, t2.nama_tingkat as tingkat_ke
                FROM ukt_peserta up
                JOIN anggota a ON up.anggota_id = a.id
                LEFT JOIN tingkatan t1 ON up.tingkat_dari_id = t1.id
                LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
                WHERE up.ukt_id = $id
                ORDER BY a.nama_lengkap";

$peserta_result = $conn->query($peserta_sql);
$total_peserta = $peserta_result->num_rows;

// Hitung statistik
$stat_lulus = $conn->query("SELECT COUNT(*) as count FROM ukt_peserta WHERE ukt_id = $id AND status = 'lulus'")->fetch_assoc();
$stat_tidak = $conn->query("SELECT COUNT(*) as count FROM ukt_peserta WHERE ukt_id = $id AND status = 'tidak_lulus'")->fetch_assoc();

$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail UKT - Sistem Beladiri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 20px; }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .label { color: #666; font-weight: 600; }
        .value { color: #333; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number { font-size: 28px; font-weight: 700; color: #667eea; }
        .stat-label { color: #666; margin-top: 10px; }
        
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
        
        .status-lulus { color: #27ae60; font-weight: 600; }
        .status-tidak { color: #e74c3c; font-weight: 600; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <?php renderNavbar('📋 Detail Pelaksanaan UKT'); ?>
    
    <div class="container">
        <div class="info-card">
            <h3 style="color: #333; margin-bottom: 20px;">Informasi UKT</h3>
            
            <div class="info-row">
                <div class="label">Tanggal Pelaksanaan</div>
                <div class="value"><strong><?php echo date('d M Y', strtotime($ukt['tanggal_pelaksanaan'])); ?></strong></div>
            </div>
            
            <div class="info-row">
                <div class="label">Lokasi</div>
                <div class="value"><?php echo htmlspecialchars($ukt['lokasi']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Dibuat Pada</div>
                <div class="value"><?php echo date('d M Y H:i', strtotime($ukt['created_at'])); ?></div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_peserta; ?></div>
                <div class="stat-label">Total Peserta</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #27ae60;"><?php echo $stat_lulus['count']; ?></div>
                <div class="stat-label">Lulus</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #e74c3c;"><?php echo $stat_tidak['count']; ?></div>
                <div class="stat-label">Tidak Lulus</div>
            </div>
        </div>
        
        <div class="info-card">
            <h3 style="color: #333; margin-bottom: 20px;">Daftar Peserta UKT</h3>
            
            <?php if (!$is_readonly): ?>
            <a href="ukt_tambah_peserta.php?id=<?php echo $id; ?>" class="btn btn-primary" style="margin-bottom: 20px;">+ Tambah Peserta</a>
            <a href="ukt_input_nilai.php?id=<?php echo $id; ?>" class="btn btn-warning" style="margin-bottom: 20px;">📝 Input Nilai</a>
            <?php endif; ?>
            
            <?php if ($total_peserta > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>No Anggota</th>
                        <th>Nama Anggota</th>
                        <th>Dari Tingkat</th>
                        <th>Ke Tingkat</th>
                        <th>Nilai</th>
                        <th>Status</th>
                        <?php if (!$is_readonly): ?>
                        <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $peserta_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['no_anggota']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td><?php echo $row['tingkat_dari'] ?? '-'; ?></td>
                        <td><?php echo $row['tingkat_ke'] ?? '-'; ?></td>
                        <td><?php echo $row['nilai'] ?? '-'; ?></td>
                        <td>
                            <?php 
                            if ($row['status'] == 'lulus') echo '<span class="status-lulus">✓ LULUS</span>';
                            else if ($row['status'] == 'tidak_lulus') echo '<span class="status-tidak">✗ TIDAK LULUS</span>';
                            else echo '<span style="color: #3498db;">• PESERTA</span>';
                            ?>
                        </td>
                        <?php if (!$is_readonly): ?>
                        <td>
                            <a href="ukt_hapus_peserta.php?id=<?php echo $row['id']; ?>&ukt_id=<?php echo $id; ?>" 
                               class="btn btn-primary btn-small" onclick="return confirm('Hapus peserta?')">Hapus</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">📭 Belum ada peserta UKT</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>