<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config/database.php';
include 'helpers/navbar.php';

// Fungsi untuk sanitasi HTML yang mengizinkan tag aman saja
function sanitizeHtml($html) {
    $allowed_tags = ['<b>', '<i>', '<u>', '<a>', '</b>', '</i>', '</u>', '</a>', '<br>'];
    $html = strip_tags($html, '<b><i><u><a><br>');
    return $html;
}

// Load settings for logo and info-box message
$logo_path = '';
$info_box_message = 'Data di bawah ini diperbarui secara real-time dari database.';
$footer_text = '';
$footer_creator = '';
$footer_tahun = '';
if (file_exists('config/settings.php')) {
    include 'config/settings.php';
    $logo_path = $settings['logo'] ?? '';
    $info_box_message = $settings['info_box_dashboard'] ?? 'Data di bawah ini diperbarui secara real-time dari database.';
    $footer_text = $settings['footer_text'] ?? '';
    $footer_creator = $settings['footer_creator'] ?? '';
    $footer_tahun = $settings['footer_tahun'] ?? '';
}

// Hitung total anggota
$total_anggota = $conn->query("SELECT COUNT(*) as count FROM anggota")->fetch_assoc()['count'];

// Hitung total unit/ranting
$total_ranting = $conn->query("SELECT COUNT(*) as count FROM ranting")->fetch_assoc()['count'];

// Hitung total negara
$total_negara = $conn->query("SELECT COUNT(*) as count FROM negara")->fetch_assoc()['count'];

// Hitung total provinsi
$total_prov = $conn->query("SELECT COUNT(*) as count FROM provinsi")->fetch_assoc()['count'];

// Hitung total kota/kabupaten
$total_kota = $conn->query("SELECT COUNT(*) as count FROM kota")->fetch_assoc()['count'];

// Hitung total peserta kerohanian
$total_kerohanian = $conn->query("SELECT COUNT(*) as count FROM kerohanian")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Informasi & Manajemen Perisai Diri</title>
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-left {
            display: flex;
            align-items: center;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
            margin-right: 15px;
        }
        
        .navbar h1 {
            font-size: 24px;
            color: white;
            margin: 0;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .logout-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-weight: 600;
            white-space: nowrap;
        }
        
        .logout-btn:hover {
            background: rgba(220, 53, 69, 0.8);
            border-color: #dc3545;
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
        
        .sidebar hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #ddd;
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
            border-left: 3px solid #667eea;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #333;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        .footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-align: center;
            font-size: 13px;
        }
        
        .footer a {
            color: #fff;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .navbar h1 {
                width: 100%;
                font-size: 20px;
            }
            
            .navbar-left {
                justify-content: center;
                width: 100%;
            }
            
            .navbar-logo {
                height: 32px;
            }
            
            .navbar-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .container {
                flex-direction: column;
                height: auto;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .content {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <?php renderSimpleNavbar('Dashboard - Sistem Informasi & Manajemen Perisai Diri'); ?>
    
    <div class="container">
        <div class="sidebar">
            <a href="index.php" class="active">📊 Dashboard</a>
            <a href="pages/admin/pengurus.php">📋 Kepengurusan</a>
            <a href="pages/admin/ranting.php">🌳 Unit / Ranting</a>
            <a href="pages/admin/anggota.php">👥 Manajemen Anggota</a>
            <?php if (in_array($_SESSION['role'], ['admin', 'superadmin', 'negara', 'pengprov', 'pengkot'])): ?>
            <a href="pages/admin/ukt.php">🏆 Ujian Kenaikan Tingkat</a>
            <?php endif; ?>
            <a href="pages/admin/kerohanian.php">🙏 Kerohanian</a>                        
            <a href="pages/admin/jadwal_latihan.php">⏰ Jadwal Latihan</a>
             
            <?php if (in_array($_SESSION['role'], ['negara', 'pengprov', 'pengkot', 'unit', 'anggota', 'admin', 'superadmin'])): ?>
            <hr>
            <a href="akun_saya.php">👤 Akun Saya</a>
            <?php endif; ?>
             
            <?php if (in_array($_SESSION['role'], ['admin', 'superadmin'])): ?>
            <a href="pages/admin/settings.php">⚙️ Settings</a>
            <a href="pages/admin/user_management.php">👤 Kelola User</a>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <h1>Dashboard</h1>
            <p class="subtitle">Ringkasan Informasi Organisasi Perisai Diri</p>
            
            <div class="info-box">
                <p><strong>ℹ️ Informasi : </strong></p>
                <p align="left" style="margin-left: 25px;"><?php echo nl2br(sanitizeHtml($info_box_message)); ?></p>
            </div>
            
            <div class="dashboard-grid">
                <!-- Pengurus Negara -->
                <a href="pages/admin/pengurus_list.php?jenis=pusat" style="text-decoration: none;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🌍</div>
                        <div class="card-title">Pengurus Negara</div>
                    </div>
                    <div class="card-number"><?php echo $total_negara; ?></div>
                    <div class="card-footer">Struktur aktif</div>
                </div>
                </a>
                
                <!-- Pengurus Provinsi -->
                <a href="pages/admin/pengurus_list.php?jenis=provinsi" style="text-decoration: none;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🏛️</div>
                        <div class="card-title">Pengurus Provinsi</div>
                    </div>
                    <div class="card-number"><?php echo $total_prov; ?></div>
                    <div class="card-footer">Struktur aktif</div>
                </div>
                </a>
                
                <!-- Pengurus Kota/Kabupaten -->
                <a href="pages/admin/pengurus_list.php?jenis=kota" style="text-decoration: none;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🏛️</div>
                        <div class="card-title">Pengurus Kota / Kabupaten</div>
                    </div>
                    <div class="card-number"><?php echo $total_kota; ?></div>
                    <div class="card-footer">Struktur aktif</div>
                </div>
                </a>
                
                <!-- Unit/Ranting -->
                <a href="pages/admin/ranting.php" style="text-decoration: none;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">🌳</div>
                        <div class="card-title">Total Unit / Ranting</div>
                    </div>
                    <div class="card-number"><?php echo $total_ranting; ?></div>
                    <div class="card-footer">UKM, Ranting, Unit</div>
                </div>
                <!-- Anggota -->
                <a href="pages/admin/anggota.php" style="text-decoration: none;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">👥</div>
                        <div class="card-title">Total Anggota</div>
                    </div>
                    <div class="card-number"><?php echo $total_anggota; ?></div>
                    <div class="card-footer">Murid, Pelatih, Pelatih Unit</div>
                </div>                
                
                <!-- Peserta Kerohanian Total -->
                <a href="pages/admin/kerohanian.php" style="text-decoration: none;">
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
    
    <?php if ($footer_text || $footer_creator || $footer_tahun): ?>
    <div class="footer">
        <?php echo nl2br(sanitizeHtml($footer_text)); ?>
        <?php if ($footer_creator): ?><br><?php echo htmlspecialchars($footer_creator); ?><?php endif; ?>
        <?php if ($footer_tahun): ?> - <?php echo htmlspecialchars($footer_tahun); ?><?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>