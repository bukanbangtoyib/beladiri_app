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

$sql = "SELECT p.*, 
        (SELECT nama_pengurus FROM pengurus p2 WHERE p2.id = p.pengurus_induk_id) as pengurus_induk
        FROM pengurus p
        WHERE p.id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Pengurus tidak ditemukan!");
}

$pengurus = $result->fetch_assoc();

// Ambil anak pengurus (jika ada)
$anak_sql = "SELECT id, nama_pengurus, jenis_pengurus FROM pengurus WHERE pengurus_induk_id = $id ORDER BY nama_pengurus";
$anak_result = $conn->query($anak_sql);

// Label jenis
$label_jenis = [
    'pusat' => 'Pengurus Pusat',
    'provinsi' => 'Pengurus Provinsi',
    'kota' => 'Pengurus Kota'
];

// Hitung ranting di pengurus kota ini
$ranting_count = 0;
$ranting_result = NULL;
if ($pengurus['jenis_pengurus'] == 'kota') {
    $r = $conn->query("SELECT COUNT(*) as count FROM ranting WHERE pengurus_kota_id = $id")->fetch_assoc();
    $ranting_count = $r['count'];
    
    // Ambil daftar ranting
    $ranting_result = $conn->query("SELECT id, nama_ranting, jenis, ketua_nama FROM ranting WHERE pengurus_kota_id = $id ORDER BY nama_ranting");
}

// Cek status aktif/tidak aktif
$status = (strtotime($pengurus['periode_akhir']) >= strtotime(date('Y-m-d'))) ? 'Aktif' : 'Tidak Aktif';
$status_class = $status == 'Aktif' ? 'status-aktif' : 'status-tidak';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?> - Sistem Beladiri</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
        }
        
        .container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .breadcrumb {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .header-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .header-info h1 {
            color: #333;
            margin-bottom: 12px;
            font-size: 28px;
        }
        
        .header-info p {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
            margin-right: 10px;
        }
        
        .badge-pusat {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            color: #667eea;
            border: 1px solid #667eea;
        }
        
        .badge-provinsi {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.2) 0%, rgba(245, 87, 108, 0.2) 100%);
            color: #f5576c;
            border: 1px solid #f5576c;
        }
        
        .badge-kota {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.2) 0%, rgba(0, 242, 254, 0.2) 100%);
            color: #4facfe;
            border: 1px solid #4facfe;
        }
        
        .status-aktif {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-tidak {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .info-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        h3 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            font-size: 18px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .label {
            color: #666;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .value {
            color: #333;
            font-size: 15px;
        }
        
        .value-highlight {
            color: #667eea;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #f8f9fa;
            padding: 14px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        
        .btn-warning:hover {
            background: #ffb300;
            transform: translateY(-2px);
        }
        
        .button-group {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .link-nav {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .link-nav:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php renderNavbar('📋 Detail ' . $label_jenis[$pengurus['jenis_pengurus']]); ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="pengurus.php">Pengurus</a> > 
            <a href="pengurus_list.php?jenis=<?php echo $pengurus['jenis_pengurus']; ?>"><?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?></a> > 
            <strong><?php echo htmlspecialchars($pengurus['nama_pengurus']); ?></strong>
        </div>
        
        <!-- Header Card -->
        <div class="header-card">
            <div class="header-info">
                <h1><?php echo htmlspecialchars($pengurus['nama_pengurus']); ?></h1>
                <p style="margin-bottom: 15px;">
                    <span class="badge badge-<?php echo $pengurus['jenis_pengurus']; ?>">
                        <?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?>
                    </span>
                    <span class="<?php echo $status_class; ?>">● <?php echo $status; ?></span>
                </p>
                <p><strong>Ketua:</strong> <?php echo htmlspecialchars($pengurus['ketua_nama'] ?? '-'); ?></p>
            </div>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div>
                <a href="pengurus_edit.php?id=<?php echo $id; ?>" class="btn btn-warning">✏️ Edit</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Informasi Dasar -->
        <div class="info-card">
            <h3>📌 Informasi Dasar</h3>
            
            <div class="info-row">
                <div class="label">Jenis Pengurus</div>
                <div class="value"><?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">No SK</div>
                <div class="value"><?php echo htmlspecialchars($pengurus['sk_kepengurusan'] ?? '-'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Periode</div>
                <div class="value"><?php echo date('d M Y', strtotime($pengurus['periode_mulai'])); ?> - <?php echo date('d M Y', strtotime($pengurus['periode_akhir'])); ?></div>
            </div>
            
            <?php if ($pengurus['pengurus_induk']): ?>
            <div class="info-row">
                <div class="label">Pengurus Induk</div>
                <div class="value">
                    <a href="pengurus_detail.php?id=<?php echo $pengurus['pengurus_induk_id']; ?>" class="link-nav">
                        <?php echo htmlspecialchars($pengurus['pengurus_induk']); ?> →
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Struktur Organisasi -->
        <div class="info-card">
            <h3>👤 Struktur Organisasi</h3>
            
            <div class="info-row">
                <div class="label">Ketua</div>
                <div class="value"><?php echo htmlspecialchars($pengurus['ketua_nama'] ?? '-'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Alamat Sekretariat</div>
                <div class="value"><?php echo nl2br(htmlspecialchars($pengurus['alamat_sekretariat'] ?? '-')); ?></div>
            </div>
        </div>
        
        <!-- Struktur yang Dinaungi -->
        <?php if ($anak_result->num_rows > 0): ?>
        <div class="info-card">
            <h3>📊 Struktur yang Dinaungi</h3>
            
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $anak_result->num_rows; ?></div>
                    <div class="stat-label">
                        <?php 
                        if ($pengurus['jenis_pengurus'] == 'pusat') {
                            echo 'Pengurus Provinsi';
                        } else {
                            echo 'Pengurus Kota';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th>Ketua</th>
                        <th>Periode</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $anak_result->data_seek(0);
                    while ($row = $anak_result->fetch_assoc()): 
                        // Ambil detail pengurus anak
                        $child_detail = $conn->query("SELECT ketua_nama, periode_mulai, periode_akhir FROM pengurus WHERE id = " . $row['id'])->fetch_assoc();
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_pengurus']); ?></strong></td>
                        <td><?php echo $label_jenis[$row['jenis_pengurus']]; ?></td>
                        <td><?php echo htmlspecialchars($child_detail['ketua_nama'] ?? '-'); ?></td>
                        <td><?php echo date('Y', strtotime($child_detail['periode_mulai'])); ?> - <?php echo date('Y', strtotime($child_detail['periode_akhir'])); ?></td>
                        <td>
                            <a href="pengurus_detail.php?id=<?php echo $row['id']; ?>" class="link-nav">Lihat →</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Unit/Ranting yang Dinaungi (khusus Pengurus Kota) -->
        <?php if ($pengurus['jenis_pengurus'] == 'kota'): ?>
        <div class="info-card">
            <h3>🏢 Unit / Ranting yang Dinaungi</h3>
            
            <?php if ($ranting_count > 0): ?>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $ranting_count; ?></div>
                    <div class="stat-label">Unit / Ranting Aktif</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nama Unit/Ranting</th>
                        <th>Jenis</th>
                        <th>Ketua</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $ranting_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_ranting']); ?></strong></td>
                        <td><?php echo ucfirst($row['jenis']); ?></td>
                        <td><?php echo htmlspecialchars($row['ketua_nama'] ?? '-'); ?></td>
                        <td>
                            <a href="ranting_detail.php?id=<?php echo $row['id']; ?>" class="link-nav">Lihat →</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🏢</div>
                <p>Belum ada unit/ranting yang dinaungi</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>