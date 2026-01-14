<?php
/**
 * Function untuk render navbar dengan home button
 * @param string $page_title Judul halaman saat ini
 * @param string $back_url URL untuk tombol kembali (opsional)
 */
function renderNavbar($page_title, $back_url = null) {
    $username = htmlspecialchars($_SESSION['nama'] ?? 'User');
    $role_label = isset($GLOBALS['permission_manager']) 
        ? $GLOBALS['permission_manager']->getRoleName()
        : ($_SESSION['role'] ?? 'User');
    
    ?>
    <div class="navbar">
        <div class="navbar-left">
            <h2><?php echo $page_title; ?></h2>
        </div>
        <div class="navbar-right">
            <div class="navbar-user-info">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role"><?php echo $role_label; ?></span>
            </div>
            <div class="navbar-buttons">
                <a href="<?php echo isset($back_url) ? $back_url : '../../index.php'; ?>" class="btn-navbar" title="Home">
                    🏠 Home
                </a>
                <?php if ($back_url): ?>
                <a href="javascript:history.back()" class="btn-navbar btn-secondary" title="Kembali">
                    ← Kembali
                </a>
                <?php endif; ?>
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
        }
        
        .navbar-left h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .navbar-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 13px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            opacity: 0.9;
            font-size: 12px;
        }
        
        .navbar-buttons {
            display: flex;
            gap: 8px;
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
    </style>
    <?php
}
?>
