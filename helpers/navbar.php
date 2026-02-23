<?php
// Load settings for logo
$logo_path = '';
if (file_exists('../../config/settings.php')) {
    include '../../config/settings.php';
    $logo_path = $settings['logo'] ?? '';
}

/**
 * Function untuk render navbar dengan tombol kembali ke parent page
 * @param string $page_title Judul halaman saat ini
 */
function renderNavbar($page_title) {
    global $logo_path;
    
    // Tentukan base path
    $is_admin_page = strpos($_SERVER['PHP_SELF'], '/pages/admin/') !== false;
    $base_path = $is_admin_page ? '../../' : './';
    
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
            <?php if (!empty($logo_path)): ?>
                <img src="<?php echo $base_path . htmlspecialchars($logo_path); ?>" alt="Logo" class="navbar-logo">
            <?php endif; ?>
            <h2><?php echo $page_title; ?></h2>
        </div>
        <div class="navbar-right">
            <div class="navbar-user-info">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role"><?php echo $role_display; ?></span>
            </div>
            <div class="navbar-buttons">                
                <a href="<?php echo $base_path; ?>index.php" class="btn-navbar" title="Home">
                    🏠 Home
                </a>
                <a href="javascript:history.back()" class="btn-navbar" title="Kembali ke halaman sebelumnya">
                    ← Kembali
                </a>
                <a href="<?php echo $base_path; ?>logout.php" class="btn-navbar btn-danger" title="Logout">
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
        
        .navbar-left {
            display: flex;
            align-items: center;
        }
        
        .navbar-left h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
            margin-right: 12px;
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
            
            .navbar-left {
                justify-content: center;
                width: 100%;
            }
            
            .navbar-logo {
                height: 32px;
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
    global $logo_path;
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
    
    // Tentukan base path
    $is_admin_page = strpos($_SERVER['PHP_SELF'], '/pages/admin/') !== false;
    $base_path = $is_admin_page ? '../../' : './';
    
    ?>
    <div class="navbar">
        <div class="navbar-left">
            <?php if (!empty($logo_path)): ?>
                <img src="<?php echo $base_path . htmlspecialchars($logo_path); ?>" alt="Logo" class="navbar-logo">
            <?php endif; ?>
            <h2><?php echo $page_title; ?></h2>
        </div>
        <div class="navbar-right">
            <div class="navbar-user-info">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role"><?php echo $role_display; ?></span>
            </div>
            <div class="navbar-buttons">
                <a href="javascript:history.back()" class="btn-navbar" title="Kembali ke halaman sebelumnya">
                    ← Kembali
                </a>
                <a href="<?php echo $base_path; ?>logout.php" class="btn-navbar btn-danger" title="Logout">
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
        
        .navbar-left {
            display: flex;
            align-items: center;
        }
        
        .navbar-left h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
            margin-right: 12px;
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
            
            .navbar-left {
                justify-content: center;
                width: 100%;
            }
            
            .navbar-logo {
                height: 32px;
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
