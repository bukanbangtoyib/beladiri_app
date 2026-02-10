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

$id = (int)$_GET['id'];

$sql = "SELECT k.*, a.nama_lengkap, a.no_anggota, a.tanggal_lahir, a.jenis_kelamin,
               r.nama_ranting, t.nama_tingkat, t_pembuka.nama_tingkat as tingkat_pembuka_nama
        FROM kerohanian k
        JOIN anggota a ON k.anggota_id = a.id
        LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id
        LEFT JOIN tingkatan t ON a.tingkat_id = t.id
        LEFT JOIN tingkatan t_pembuka ON k.tingkat_pembuka_id = t_pembuka.id
        WHERE k.id = $id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Data kerohanian tidak ditemukan!");
}

$kerohanian = $result->fetch_assoc();

// Hitung umur
$birthDate = new DateTime($kerohanian['tanggal_lahir']);
$today = new DateTime("today");
$age = $birthDate->diff($today)->y;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kerohanian - Sistem Beladiri</title>
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
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        h1 { color: #333; margin-bottom: 20px; }
        
        .info-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 30px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child { border-bottom: none; }
        
        .label { color: #666; font-weight: 600; }
        .value { color: #333; }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-warning { background: #ffc107; color: black; }
        .button-group { margin-top: 20px; }
    </style>
</head>
<body>
    <?php renderNavbar('🙏 Detail Kerohanian'); ?>

    <div class="container">
        <div class="card">
            <h1>Detail Pembukaan Kerohanian</h1>
            
            <div class="info-row">
                <div class="label">No Anggota</div>
                <div class="value"><strong><?php echo $kerohanian['no_anggota']; ?></strong></div>
            </div>
            
            <div class="info-row">
                <div class="label">Nama Anggota</div>
                <div class="value"><strong><?php echo htmlspecialchars($kerohanian['nama_lengkap']); ?></strong></div>
            </div>
            
            <div class="info-row">
                <div class="label">Umur</div>
                <div class="value"><?php echo $age; ?> tahun</div>
            </div>
            
            <div class="info-row">
                <div class="label">Jenis Kelamin</div>
                <div class="value"><?php echo ($kerohanian['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Tingkat Saat Ini</div>
                <div class="value"><span class="badge"><?php echo $kerohanian['nama_tingkat']; ?></span></div>
            </div>

            <div class="info-row">
                <div class="label">Tanggal Pembukaan</div>
                <div class="value"><strong><?php echo date('d M Y', strtotime($kerohanian['tanggal_pembukaan'])); ?></strong></div>
            </div>
            
            <div class="info-row">
                <div class="label">Lokasi Pembukaan</div>
                <div class="value"><?php echo htmlspecialchars($kerohanian['lokasi']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Nama Pembuka</div>
                <div class="value"><?php echo htmlspecialchars($kerohanian['pembuka_nama']); ?></div>
            </div>

            <div class="info-row">
                <div class="label">Penyelenggara</div>
                <div class="value"><?php echo htmlspecialchars($kerohanian['penyelenggara']); ?></div>
            </div>

            <div class="info-row">
                <div class="label">Tingkat Pembuka</div>
                <div class="value"><span class="badge"><?php echo $kerohanian['tingkat_pembuka_nama']; ?></span></div>
            </div>
            
            <div class="info-row">
                <div class="label">Unit/Ranting</div>
                <div class="value"><?php echo htmlspecialchars($kerohanian['nama_ranting'] ?? '-'); ?></div>
            </div>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="button-group">
                <button onclick="window.print()" class="btn btn-warning" style="background: #6c757d;">
                    🖨️ Print Detail
                </button>
                <a href="kerohanian_edit.php?id=<?php echo $id; ?>" class="btn btn-warning">✏️ Edit</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>