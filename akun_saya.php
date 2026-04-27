<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config/database.php';
include 'helpers/navbar.php';

// Load settings for logo
$logo_path = '';
if (file_exists('config/settings.php')) {
    include 'config/settings.php';
    $logo_path = $settings['logo'] ?? '';
}

// Get user data from session
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$nama_lengkap = $_SESSION['nama'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Determine detail link based on role
$detail_link = '';
$detail_params = '';

if (in_array($role, ['negara', 'pengprov', 'pengkot'])) {
    // For Negara, Provinsi, Kota: use pengurus_detail.php
    $jenis_map = [
        'negara' => 'pusat',
        'pengprov' => 'provinsi',
        'pengkot' => 'kota'
    ];
    $jenis = $jenis_map[$role] ?? 'pusat';
    $pengurus_id = $_SESSION['pengurus_id'] ?? 0;
    if ($pengurus_id > 0) {
        $detail_link = 'pages/admin/pengurus_detail.php';
        $detail_params = "?id=" . $pengurus_id . "&jenis=" . $jenis;
    } else {
        // Fallback to dashboard if pengurus_id is not set
        $detail_link = 'index.php';
        $detail_params = '';
    }
} elseif (in_array($role, ['ranting', 'unit'])) {
    // For Ranting/Unit: use ranting_detail.php
    $ranting_id = $_SESSION['ranting_id'] ?? 0;
    if ($ranting_id > 0) {
        $detail_link = 'pages/admin/ranting_detail.php';
        $detail_params = "?id=" . $ranting_id;
    } else {
        $detail_link = 'index.php';
    }
} elseif ($role === 'anggota') {
    // For Anggota: use anggota_detail.php
    $no_anggota = $_SESSION['no_anggota'] ?? '';
    if ($no_anggota !== '') {
        $res = $conn->query("SELECT id FROM anggota WHERE no_anggota = '$no_anggota'");
        if ($row = $res->fetch_assoc()) {
            $detail_link = 'pages/admin/anggota_detail.php';
            $detail_params = "?id=" . $row['id'];
        } else {
            $detail_link = 'index.php';
        }
    } else {
        $detail_link = 'index.php';
    }
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update nama_lengkap
    $new_nama = trim($_POST['nama_lengkap'] ?? '');
    if ($new_nama !== '') {
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?");
        $stmt->bind_param("si", $new_nama, $user_id);
        if ($stmt->execute()) {
            $_SESSION['nama'] = $new_nama; // Update session
            $success = "Nama lengkap berhasil diperbarui!";
        } else {
            $error = "Gagal memperbarui nama lengkap: " . $conn->error;
        }
    }

    // Update password if provided
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
    
    if ($password_baru !== '' || $konfirmasi_password !== '') {
        if ($password_baru === '' || $konfirmasi_password === '') {
            $error = "Both password fields must be filled to change password.";
        } elseif ($password_baru !== $konfirmasi_password) {
            $error = "New password and confirmation do not match.";
        } elseif (strlen($password_baru) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $success .= " Password berhasil diubah!";
            } else {
                $error = "Gagal memperbarui password: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Saya - Sistem Informasi & Manajemen Perisai Diri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .password-field {
            position: relative;
        }
        
        .password-field input {
            padding-right: 40px;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
        
        .form-group input:read-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #c00;
            border-left-color: #dc3545;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #060;
            border-left-color: #28a745;
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
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px auto;
                padding: 0 10px;
            }
            
            .header, .form-card {
                padding: 20px;
            }

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
        }
    </style>
</head>
<body>
    <?php renderSimpleNavbar('Akun Saya - Sistem Informasi & Manajemen Perisai Diri'); ?>
    
    <div style="display: flex; justify-content: center;">
        <div class="container" style="width: 100%;">
            <div class="header">
                <h1>Akun Saya</h1>
                <p>Halaman ini memungkinkan Anda mengubah profil akun Anda.</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <p><strong>ℹ️ Informasi : </strong>Username dan role tidak dapat diubah. Untuk mengubah nama lengkap atau password, silakan isi formulir di bawah ini.</p>
            </div>
            
            <?php if (!in_array($role, ['admin', 'superadmin'])): ?>
                        <div class="info-box">
                            <p><strong>Detail Akun:</strong> 
                                <a href="<?php echo $detail_link . $detail_params; ?>" class="link-nav">Lihat Detail Akun</a>
                            </p>
                        </div>
            <?php endif; ?>
            
            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($role); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($nama_lengkap); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password_baru">Password Baru</label>
                        <div class="password-field">
                            <input type="password" id="password_baru" name="password_baru" placeholder="Isi jika ingin mengganti password">
                            <i class="fa fa-eye password-toggle" onclick="togglePassword('password_baru', this)"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                        <div class="password-field">
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" placeholder="Isi ulang password baru">
                            <i class="fa fa-eye password-toggle" onclick="togglePassword('konfirmasi_password', this)"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Simpan Perubahan</button>
                        <a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date("Y"); ?> Perisai Diri - Tripl3D. All rights reserved.
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>