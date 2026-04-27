<?php
/**
 * Icon-only sidebar for space saving
 */
if (defined('ICON_SIDEBAR_RENDERED')) return;
define('ICON_SIDEBAR_RENDERED', true);

$is_admin_page = strpos($_SERVER['PHP_SELF'], '/pages/admin/') !== false;
$base_path = $is_admin_page ? '../../' : './';
$current_page = basename($_SERVER['PHP_SELF']);

// Only show if not on index.php
if ($current_page !== 'index.php'):
?>
<div class="icon-sidebar">
    <a href="<?php echo $base_path; ?>index.php" title="Dashboard" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
        📊
    </a>
    <a href="<?php echo $base_path; ?>pages/admin/pengurus.php" title="Kepengurusan" class="<?php echo ($current_page == 'pengurus.php' || strpos($current_page, 'pengurus_') === 0 || strpos($current_page, 'pengurus_list') === 0) ? 'active' : ''; ?>">
        📋
    </a>
    <a href="<?php echo $base_path; ?>pages/admin/ranting.php" title="Unit / Ranting" class="<?php echo ($current_page == 'ranting.php' || strpos($current_page, 'ranting_') === 0) ? 'active' : ''; ?>">
        🌳
    </a>
    <a href="<?php echo $base_path; ?>pages/admin/anggota.php" title="Manajemen Anggota" class="<?php echo ($current_page == 'anggota.php' || strpos($current_page, 'anggota_') === 0) ? 'active' : ''; ?>">
        👥
    </a>
    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'superadmin', 'negara', 'pengprov', 'pengkot'])): ?>
    <a href="<?php echo $base_path; ?>pages/admin/ukt.php" title="Ujian Kenaikan Tingkat" class="<?php echo ($current_page == 'ukt.php' || strpos($current_page, 'ukt_') === 0) ? 'active' : ''; ?>">
        🏆
    </a>
    <?php endif; ?>
    <a href="<?php echo $base_path; ?>pages/admin/kerohanian.php" title="Kerohanian" class="<?php echo ($current_page == 'kerohanian.php' || strpos($current_page, 'kerohanian_') === 0) ? 'active' : ''; ?>">
        🙏
    </a>
    <a href="<?php echo $base_path; ?>pages/admin/jadwal_latihan.php" title="Jadwal Latihan" class="<?php echo $current_page == 'jadwal_latihan.php' ? 'active' : ''; ?>">
        ⏰
    </a>
    <hr class="sidebar-divider">
    <?php if (in_array($_SESSION['role'] ?? '', ['negara', 'pengprov', 'pengkot', 'unit', 'anggota', 'admin', 'superadmin'])): ?>
    <a href="<?php echo $base_path; ?>akun_saya.php" title="Akun Saya" class="<?php echo $current_page == 'akun_saya.php' ? 'active' : ''; ?>">
        👤
    </a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'])): ?>
    <a href="<?php echo $base_path; ?>pages/admin/settings.php" title="Settings" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
        ⚙️
    </a>
    <a href="<?php echo $base_path; ?>pages/admin/user_management.php" title="Kelola User" class="<?php echo $current_page == 'user_management.php' ? 'active' : ''; ?>">
        👥
    </a>
    <?php endif; ?>
</div>

<style>
.icon-sidebar {
    position: fixed;
    left: 0;
    top: 66px; /* Start below navbar */
    bottom: 0;
    width: 60px;
    background: #ffffff;
    border-right: 1px solid #e0e0e0;
    padding: 20px 0;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    z-index: 999; /* Below navbar z-index if possible, or just below */
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
}

.icon-sidebar a {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    color: #555;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 20px;
    background: transparent;
}

.icon-sidebar a:hover {
    background: #f0f2f5;
    color: #667eea;
    transform: translateY(-2px);
}

.icon-sidebar a.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.sidebar-divider {
    width: 30px;
    border: none;
    border-top: 1px solid #eee;
    margin: 8px 0;
}

/* Page layout adjustment */
body.has-mini-sidebar {
    padding-left: 0 !important;
    padding-top: 0 !important;
}

/* Shift all top-level content except navbar and sidebar */
body.has-mini-sidebar > div:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > section:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > main:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > table:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > form:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > header:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > footer:not(.navbar):not(.icon-sidebar),
body.has-mini-sidebar > .container {
    margin-left: 60px !important;
    width: calc(100% - 60px) !important;
}

/* Ensure navbar is ALWAYS full width at the top */
.navbar {
    width: 100% !important;
    left: 0 !important;
    right: 0 !important;
    margin-left: 0 !important;
    z-index: 1002 !important; /* Above sidebar */
}

@media (max-width: 768px) {
    .icon-sidebar {
        display: none;
    }
    body.has-mini-sidebar > div:not(.navbar):not(.icon-sidebar),
    body.has-mini-sidebar > .container {
        margin-left: 0 !important;
        width: 100% !important;
    }
}
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.classList.add('has-mini-sidebar');
    });
</script>
<?php endif; ?>