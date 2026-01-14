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

$ranting_id = isset($_GET['ranting_id']) ? (int)$_GET['ranting_id'] : 0;
$error = '';
$success = '';

// Proses tambah/edit jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'add';
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    if (empty($ranting_id)) {
        $error = "Pilih unit/ranting terlebih dahulu!";
    } else {
        if ($action == 'add') {
            $sql = "INSERT INTO jadwal_latihan (ranting_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $ranting_id, $hari, $jam_mulai, $jam_selesai);
            
            if ($stmt->execute()) {
                $success = "Jadwal latihan berhasil ditambahkan!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        } elseif ($action == 'delete') {
            $jadwal_id = (int)$_POST['jadwal_id'];
            $conn->query("DELETE FROM jadwal_latihan WHERE id = $jadwal_id");
            $success = "Jadwal latihan berhasil dihapus!";
        }
    }
}

// Ambil daftar ranting
$ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting ORDER BY nama_ranting");

// Ambil jadwal untuk ranting yang dipilih
$jadwal_result = null;
if ($ranting_id > 0) {
    $jadwal_result = $conn->query("SELECT * FROM jadwal_latihan WHERE ranting_id = $ranting_id ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')");
}

$hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Latihan - Sistem Beladiri</title>
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
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        h1 { color: #333; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select, input { padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; width: 100%; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: 600; }
        td { padding: 12px; border: 1px solid #ddd; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
    </style>
</head>
<body>
    <?php renderNavbar('⏰ Jadwal Latihan'); ?>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Form Input Jadwal -->
        <div class="card">
            <h2>Kelola Jadwal Latihan Unit/Ranting</h2>
            
            <form method="GET" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label>Pilih Unit/Ranting</label>
                    <select name="ranting_id" onchange="this.form.submit()" required>
                        <option value="">-- Pilih Unit/Ranting --</option>
                        <?php 
                        $ranting_result->data_seek(0);
                        while ($row = $ranting_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($ranting_id == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['nama_ranting']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($ranting_id > 0 && !$is_readonly): ?>
            <form method="POST" style="margin-bottom: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                <h3 style="margin-bottom: 15px;">Tambah Jadwal Baru</h3>
                
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Hari</label>
                        <select name="hari" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($hari_options as $h): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Jam Mulai</label>
                        <input type="time" name="jam_mulai" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jam Selesai</label>
                        <input type="time" name="jam_selesai" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 10px;">➕ Tambah Jadwal</button>
            </form>
            <?php endif; ?>
        </div>
        
        <!-- Daftar Jadwal -->
        <?php if ($ranting_id > 0): ?>
        <div class="card">
            <h2>Jadwal Latihan</h2>
            
            <?php if ($jadwal_result && $jadwal_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th style="width: 100px;">Durasi</th>
                        <?php if (!$is_readonly): ?><th style="width: 80px;">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $jadwal_result->fetch_assoc()): 
                        $mulai = strtotime($row['jam_mulai']);
                        $selesai = strtotime($row['jam_selesai']);
                        $durasi = round(($selesai - $mulai) / 3600);
                    ?>
                    <tr>
                        <td><strong><?php echo $row['hari']; ?></strong></td>
                        <td><?php echo date('H:i', $mulai); ?></td>
                        <td><?php echo date('H:i', $selesai); ?></td>
                        <td><?php echo $durasi; ?> jam</td>
                        <?php if (!$is_readonly): ?>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="jadwal_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Hapus jadwal ini?')">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>📭 Belum ada jadwal latihan</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>