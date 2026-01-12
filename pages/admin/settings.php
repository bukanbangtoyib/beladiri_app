<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$error = '';
$success = '';

// Load settings (dari file config atau bisa dari database)
$settings_file = '../../config/settings.php';

$default_settings = [
    'nama_organisasi' => 'Lembaga Beladiri',
    'alamat' => 'Jl. Contoh No. 123',
    'no_telp' => '(031) 123-4567',
    'email' => 'info@beladiri.com',
    'tahun_berdiri' => '2020'
];

// Load existing settings
if (file_exists($settings_file)) {
    include $settings_file;
    if (!isset($settings)) {
        $settings = $default_settings;
    }
} else {
    $settings = $default_settings;
}

// Process update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $settings['nama_organisasi'] = $_POST['nama_organisasi'];
    $settings['alamat'] = $_POST['alamat'];
    $settings['no_telp'] = $_POST['no_telp'];
    $settings['email'] = $_POST['email'];
    $settings['tahun_berdiri'] = $_POST['tahun_berdiri'];
    
    // Save to file
    $content = "<?php\n\$settings = " . var_export($settings, true) . ";\n?>";
    
    if (file_put_contents($settings_file, $content)) {
        $success = "Pengaturan berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan pengaturan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Sistem Beladiri</title>
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
        
        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 22px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea { resize: vertical; min-height: 80px; }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background: #667eea; color: white; }
        
        .button-group { margin-top: 35px; }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>⚙️ Pengaturan Sistem</h2>
        <a href="../../index.php" style="color: white;">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h1>Pengaturan Sistem</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>ℹ️ Informasi:</strong> Edit data organisasi Anda di sini. Data ini akan muncul di laporan dan dokumen resmi.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Nama Organisasi / Lembaga</label>
                    <input type="text" name="nama_organisasi" value="<?php echo htmlspecialchars($settings['nama_organisasi']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Alamat Kantor Pusat</label>
                    <textarea name="alamat" required><?php echo htmlspecialchars($settings['alamat']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Nomor Telepon</label>
                    <input type="text" name="no_telp" value="<?php echo htmlspecialchars($settings['no_telp']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Tahun Berdiri</label>
                    <input type="text" name="tahun_berdiri" value="<?php echo htmlspecialchars($settings['tahun_berdiri']); ?>" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>