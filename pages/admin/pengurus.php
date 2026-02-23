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

// Hitung jumlah dari 4 tabel baru
$pusat = $conn->query("SELECT COUNT(*) as count FROM negara")->fetch_assoc();
$provinsi = $conn->query("SELECT COUNT(*) as count FROM provinsi")->fetch_assoc();
$kota = $conn->query("SELECT COUNT(*) as count FROM kota")->fetch_assoc();
$ranting = $conn->query("SELECT COUNT(*) as count FROM ranting")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengurus - Perisai Diri</title>
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
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 20px; }
        
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }

        .button-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            padding: 25px;
            color: white;
            text-align: center;
        }
        
        .card-header.pusat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-header.provinsi {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .card-header.kota {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .card-icon { font-size: 48px; margin-bottom: 15px; }
        .card-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .card-desc { font-size: 13px; opacity: 0.9; }
        
        .card-body {
            padding: 25px;
            text-align: center;
        }
        
        .card-number {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .card-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        
        .info-box strong { color: #667eea; }
    </style>
</head>
<body>
    <?php renderNavbar('📋 Manajemen Pengurus'); ?>
    
    <div class="container">
        <h1>Manajemen Struktur Kepengurusan</h1>
        <p class="subtitle">Kelola data pengurus negara, provinsi, dan kota/kabupaten</p>
        
        <div class="info-box">
            <strong>ℹ️ Informasi : </strong> Pilih salah satu jenis kepengurusan di bawah untuk mengelola data struktur organisasi.
            <a href="pengurus_import.php" class="btn btn-success">⬆️ Import CSV</a>
        </div>
        
        <div class="cards-grid">
            <!-- Card Pengurus Negara -->
            <a href="pengurus_list.php?jenis=pusat" class="card">
                <div class="card-header pusat">
                    <div class="card-icon">🏛️</div>
                    <div class="card-title">Pengurus Negara</div>
                    <div class="card-desc">Tingkat Negara</div>
                </div>
                <div class="card-body">
                    <div class="card-number"><?php echo $pusat['count']; ?></div>
                    <div class="card-label">Struktur Aktif</div>
                </div>
            </a>
            
            <!-- Card Pengurus Provinsi -->
            <a href="pengurus_list.php?jenis=provinsi" class="card">
                <div class="card-header provinsi">
                    <div class="card-icon">🏢</div>
                    <div class="card-title">Pengurus Provinsi</div>
                    <div class="card-desc">Tingkat Provinsi</div>
                </div>
                <div class="card-body">
                    <div class="card-number"><?php echo $provinsi['count']; ?></div>
                    <div class="card-label">Struktur Aktif</div>
                </div>
            </a>
            
            <!-- Card Pengurus Kota -->
            <a href="pengurus_list.php?jenis=kota" class="card">
                <div class="card-header kota">
                    <div class="card-icon">🏙️</div>
                    <div class="card-title">Pengurus Kota</div>
                    <div class="card-desc">Tingkat Kota/Kabupaten</div>
                </div>
                <div class="card-body">
                    <div class="card-number"><?php echo $kota['count']; ?></div>
                    <div class="card-label">Struktur Aktif</div>
                </div>
            </a>
        </div>
    </div>
</body>
</html>