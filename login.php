<?php
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

// Load settings for logo
$logo_path = '';
if (file_exists('config/settings.php')) {
    include 'config/settings.php';
    $logo_path = $settings['logo'] ?? '';
}

// Jika form di-submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'config/database.php';
    
    // Validasi Turnstile token
    $turnstile_token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($turnstile_token)) {
        $error = "Validasi keamanan gagal. Silakan coba lagi.";
    } else {
        // Verify Turnstile token dengan Cloudflare
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $settings['turnstile_secret_key'] ?? '',
            'response' => $turnstile_token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $turnstile_result = curl_exec($ch);
        curl_close($ch);
        
        $turnstile_data = json_decode($turnstile_result, true);
        if (!$turnstile_data['success']) {
            $error = "Validasi keamanan gagal. Silakan coba lagi.";
        } else {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            // Query cari user
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Cek password
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['pengurus_id'] = $user['pengurus_id'];
                    $_SESSION['ranting_id'] = $user['ranting_id'];
                    $_SESSION['no_anggota'] = $user['no_anggota'] ?? null;
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Username atau password salah!";
                }
            } else {
                $error = "Username tidak ditemukan!";
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
    <title>Login - Sistem Informasi & Manajemen Perisai DIri</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 440px;
            padding: 40px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-logo-img {
            max-width: 120px;
            max-height: 120px;
            object-fit: contain;
        }
        
        .login-icon {
            font-size: 64px;
            line-height: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .error {
            background-color: #fee;
            color: #c00;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }
        
        .info {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <?php if (!empty($logo_path)): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="login-logo-img">
            <?php else: ?>
                <div class="login-icon">🥋</div>
            <?php endif; ?>
        </div>
        <h1>Sistem Informasi & Manajemen Perisai Diri</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                    <i class="fa fa-eye password-toggle" onclick="togglePassword('password', this)"></i>
                </div>
            </div>
            
            <!-- Cloudflare Turnstile Widget -->
            <div class="form-group">
                <div class="cf-turnstile" data-sitekey="<?php echo $settings['turnstile_site_key'] ?? ''; ?>"></div>
            </div>
            
            <button type="submit">Login</button>
        </form>
        <div class="info">
            &copy; <?php echo date("Y"); ?> Perisai Diri - Tripl3D. All rights reserved.
        </div>
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