<?php
/**
 * Inisialisasi riwayat navigasi
 * Panggil fungsi ini di awal setiap halaman
 */
function initNavigationHistory() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $current_page = $_SERVER['REQUEST_URI'];
    $current_page = parse_url($current_page, PHP_URL_PATH);
    
    // Inisialisasi riwayat jika belum ada
    if (!isset($_SESSION['nav_history'])) {
        $_SESSION['nav_history'] = [];
    }
    
    // Hapus halaman saat ini dari riwayat jika sudah ada (untuk mencegah duplikat berurutan)
    $history = $_SESSION['nav_history'];
    if (!empty($history) && end($history) === $current_page) {
        // Sudah di halaman ini, tidak perlu tambah lagi
    } else {
        // Tambah halaman ke riwayat
        $_SESSION['nav_history'][] = $current_page;
        
        // Batasi riwayat maksimal 6 halaman (5 sebelumnya + saat ini)
        if (count($_SESSION['nav_history']) > 6) {
            array_shift($_SESSION['nav_history']);
        }
    }
}

/**
 * Function untuk auto-detect URL kembali berdasarkan nama file
 * @param string $current_file Nama file saat ini
 * @return string URL untuk kembali
 */
function autoDetectBackUrl($current_file) {
    // Hapus ekstensi .php
    $filename = str_replace('.php', '', $current_file);
    
    // Pattern suffix yang menandakan halaman child
    $suffixes = ['_detail', '_edit', '_tambah', '_buat', '_import', '_hapus', '_input', '_peserta'];
    
    foreach ($suffixes as $suffix) {
        if (strpos($filename, $suffix) !== false) {
            // Ambil bagian sebelum suffix (prefix)
            $prefix = strtok($filename, '_');
            
            // Jika prefix adalah 2-3 huruf (seperti ukt, ppt, dkk), cari parent
            if (preg_match('/^[a-z]+$/', $prefix)) {
                // Prefix adalah kategori, parent adalah prefix + .php
                return $prefix . '.php';
            }
            
            // Untuk kasus seperti "ukt_detail_peserta", extract "ukt"
            $parts = explode('_', $filename);
            if (count($parts) >= 2) {
                // Ambil kata pertama sebagai parent
                return $parts[0] . '.php';
            }
        }
    }
    
    // Default: kembali ke index.php
    return '../../index.php';
}

/**
 * Function untuk render navbar dengan tombol kembali ke parent page
 * @param string $page_title Judul halaman saat ini
 */
function renderNavbar($page_title) {
    $current_file = basename($_SERVER['PHP_SELF']);
    $back_url = autoDetectBackUrl($current_file);
    
    $username = htmlspecialchars($_SESSION['nama'] ?? 'User');
    $role_label = isset($GLOBALS['permission_manager']) 
        ? $GLOBALS['permission_manager']->getRoleName()
        : ($_SESSION['role'] ?? 'User');
    
    // Mapping untuk role label yang lebih readable
    $role_map = [
        'admin' => 'Administrator',
        'pengprov' => 'Pengurus Provinsi',
        'pengkot' => 'Pengurus Kota',
        'unit' => 'Unit / Ranting',
        'tamu' => 'Tamu (Read Only)'
    ];
    
    $role_display = $role_map[$_SESSION['role'] ?? ''] ?? $role_label;
    
    ?>
    <div class="navbar">
        <div class="navbar-left">
            <h2><?php echo $page_title; ?></h2>
        </div>
        <div class="navbar-right">
            <div class="navbar-user-info">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role"><?php echo $role_display; ?></span>
            </div>
            <div class="navbar-buttons">                
                <a href="../../index.php" class="btn-navbar" title="Home">
                    🏠 Home
                </a>
                <a href="<?php echo htmlspecialchars($back_url); ?>" 
                   class="btn-navbar" title="Kembali ke halaman sebelumnya">
                    ← Kembali
                </a>
                <a href="../../logout.php" class="btn-navbar btn-danger" title="Logout">
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>
    
    <style>
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-wrap: wrap;
            gap: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-left h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .navbar-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 13px;
            min-width: 150px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            opacity: 0.9;
            font-size: 11px;
        }
        
        .navbar-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-navbar {
            padding: 8px 14px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
            white-space: nowrap;
        }
        
        .btn-navbar:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }
        
        .btn-navbar.btn-danger:hover {
            background: rgba(220,53,69,0.8);
            border-color: #dc3545;
        }
        
        @media print {
            .navbar { display: none; }
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
            }
            
            .navbar-left h2 {
                font-size: 18px;
            }
            
            .navbar-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .navbar-buttons {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
    <?php
}

/**
 * Function untuk render navbar tanpa tombol Home dan Kembali
 * @param string $page_title Judul halaman saat ini
 */
function renderSimpleNavbar($page_title) {
    $username = htmlspecialchars($_SESSION['nama'] ?? 'User');
    $role_label = isset($GLOBALS['permission_manager']) 
        ? $GLOBALS['permission_manager']->getRoleName()
        : ($_SESSION['role'] ?? 'User');
    
    // Mapping untuk role label yang lebih readable
    $role_map = [
        'admin' => 'Administrator',
        'pengprov' => 'Pengurus Provinsi',
        'pengkot' => 'Pengurus Kota',
        'unit' => 'Unit / Ranting',
        'tamu' => 'Tamu (Read Only)'
    ];
    
    $role_display = $role_map[$_SESSION['role'] ?? ''] ?? $role_label;
    
    ?>
    <div class="navbar">
        <div class="navbar-left">
            <h2><?php echo $page_title; ?></h2>
        </div>
        <div class="navbar-right">
            <div class="navbar-user-info">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role"><?php echo $role_display; ?></span>
            </div>
            <div class="navbar-buttons">                
                <a href="../../logout.php" class="btn-navbar btn-danger" title="Logout">
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>
    
    <style>
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-wrap: wrap;
            gap: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-left h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .navbar-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 13px;
            min-width: 150px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            opacity: 0.9;
            font-size: 11px;
        }
        
        .navbar-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-navbar {
            padding: 8px 14px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
            white-space: nowrap;
        }
        
        .btn-navbar:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }
        
        .btn-navbar.btn-danger:hover {
            background: rgba(220,53,69,0.8);
            border-color: #dc3545;
        }
        
        @media print {
            .navbar { display: none; }
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
            }
            
            .navbar-left h2 {
                font-size: 18px;
            }
            
            .navbar-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .navbar-buttons {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
    <?php
}
?>

