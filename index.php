<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config/database.php';

// Hitung total anggota
$total_anggota = $conn->query("SELECT COUNT(*) as count FROM anggota")->fetch_assoc()['count'];

// Hitung total unit/ranting
$total_ranting = $conn->query("SELECT COUNT(*) as count FROM ranting")->fetch_assoc()['count'];

// Hitung total pengurus provinsi
$total_prov = $conn->query("SELECT COUNT(*) as count FROM pengurus WHERE jenis_pengurus = 'provinsi'")->fetch_assoc()['count'];

// Hitung total pengurus kota/kabupaten
$total_kota = $conn->query("SELECT COUNT(*) as count FROM pengurus WHERE jenis_pengurus = 'kota'")->fetch_assoc()['count'];

// Hitung total peserta kerohanian
$total_kerohanian = $conn->query("SELECT COUNT(*) as count FROM kerohanian")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Beladiri</title>
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
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar .user-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .navbar a:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            display: flex;
            height: calc(100vh - 60px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #ddd;
            padding: 20px 0;
            overflow-y: auto;
        }
        
        .sidebar a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar a:hover {
            background: #f5f5f5;
            border-left-color: #667eea;
        }
        
        .sidebar a.active {
            background: #f0f0f0;
            border-left-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .card-icon {
            font-size: 28px;
        }
        
        .card-title {
            color: #666;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .card-number {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin-top: 10px;
        }
        
        .card-subtitle {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .card-footer {
            font-size: 12px;
            color: #999;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        
        .info-box strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🥋 Sistem Manajemen Lembaga Beladiri</h1>
        <div class="user-info">
            <span>Halo, <?php echo $_SESSION['nama']; ?>!</span>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="index.php" class="active">📊 Dashboard</a>
            <a href="pages/admin/pengurus.php">📋 Kepengurusan</a>
            <a href="pages/admin/ranting.php">🏢 Unit / Ranting</a>
            <a href="pages/admin/anggota.php">👥 Manajemen Anggota</a>
            <a href="pages/admin/ukt.php">🏆 Ujian Kenaikan Tingkat</a>
            <a href="pages/admin/kerohanian.php">🙏 Kerohanian</a>                        
            <a href="pages/admin/jadwal_latihan.php">⏰ Jadwal Latihan</a>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
            <a href="pages/admin/settings.php">⚙️ Settings</a>
            <a href="pages/admin/user_management.php">👤 Kelola User</a>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <h1>Dashboard</h1>
            <p class="subtitle">Ringkasan data lembaga beladiri</p>
            
            <div class="info-box">
                <strong>ℹ️ Informasi:</strong> Data di bawah ini diperbarui secara real-time dari database.
            </div>
            
            <div class="dashboard-grid">
                <!-- Anggota -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">👥</div>
                        <div class="card-title">Total Anggota</div>
                    </div>
                    <div class="card-number"><?php echo $total_anggota; ?></div>
                    <div class="card-footer">Murid, Pelatih, Pelatih Unit</div>
                </div>
                
                <!-- Unit/Ranting -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🏢</div>
                        <div class="card-title">Total Unit / Ranting</div>
                    </div>
                    <div class="card-number"><?php echo $total_ranting; ?></div>
                    <div class="card-footer">UKM, Ranting, Unit</div>
                </div>
                
                <!-- Pengurus Provinsi -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🏛️</div>
                        <div class="card-title">Pengurus Provinsi</div>
                    </div>
                    <div class="card-number"><?php echo $total_prov; ?></div>
                    <div class="card-footer">Struktur aktif</div>
                </div>
                
                <!-- Pengurus Kota/Kabupaten -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🏙️</div>
                        <div class="card-title">Pengurus Kota / Kabupaten</div>
                    </div>
                    <div class="card-number"><?php echo $total_kota; ?></div>
                    <div class="card-footer">Struktur aktif</div>
                </div>
                
                <!-- Peserta Kerohanian Total -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🙏</div>
                        <div class="card-title">Peserta Kerohanian</div>
                    </div>
                    <div class="card-number"><?php echo $total_kerohanian; ?></div>
                    <div class="card-footer">Pembukaan kerohanian</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>