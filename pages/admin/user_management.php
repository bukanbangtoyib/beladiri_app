<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Proses tambah user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    if ($_POST['action_type'] == 'add') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        $pengurus_id = NULL;
        $ranting_id = NULL;
        
        // Validate and set pengurus_id based on role - REQUIRED for negara/pengprov/pengkot
        if (in_array($role, ['negara', 'pengprov', 'pengkot'])) {
            // Get the appropriate value based on role
            $pengurus_id = 0;
            
            if ($role === 'negara') {
                $pengurus_id = (int)($_POST['negara_id'] ?? 0);
            } elseif ($role === 'pengprov') {
                $pengurus_id = (int)($_POST['provinsi_id'] ?? 0);
            } elseif ($role === 'pengkot') {
                $pengurus_id = (int)($_POST['kota_id'] ?? 0);
            }
            
            if (empty($pengurus_id)) {
                $error = "Error: Untuk role " . ucfirst($role) . ", Anda harus memilih organisasi dari dropdown!";
            } else {
                // Validate the pengurus_id exists in appropriate table
                if ($role === 'negara') {
                    $check = $conn->query("SELECT id FROM negara WHERE id = $pengurus_id");
                    if ($check->num_rows === 0) {
                        $error = "Error: Negara yang dipilih tidak valid!";
                        $pengurus_id = 0;
                    }
                } elseif ($role === 'pengprov') {
                    $check = $conn->query("SELECT id FROM provinsi WHERE id = $pengurus_id");
                    if ($check->num_rows === 0) {
                        $error = "Error: Provinsi yang dipilih tidak valid!";
                        $pengurus_id = 0;
                    }
                } elseif ($role === 'pengkot') {
                    $check = $conn->query("SELECT id FROM kota WHERE id = $pengurus_id");
                    if ($check->num_rows === 0) {
                        $error = "Error: Kota yang dipilih tidak valid!";
                        $pengurus_id = 0;
                    }
                }
            }
        }
        
        // Set to null if 0
        if (empty($pengurus_id)) {
            $pengurus_id = NULL;
        }
        
        // Validate and set ranting_id for unit role
        if ($role === 'unit' && !empty($_POST['ranting_id'])) {
            $ranting_id = (int)$_POST['ranting_id'];
            $check = $conn->query("SELECT id FROM ranting WHERE id = $ranting_id");
            if ($check->num_rows === 0) $ranting_id = NULL;
        }
        
        // Check if organization already has a user (1 organisasi = 1 user)
        $org_user_error = '';
        if (in_array($role, ['negara', 'pengprov', 'pengkot']) && !empty($pengurus_id)) {
            $org_check = $conn->query("SELECT id FROM users WHERE role = '$role' AND pengurus_id = $pengurus_id");
            if ($org_check->num_rows > 0) {
                $org_user_error = "Error: Organisasi ini sudah memiliki user! (1 organisasi = 1 user)";
            }
        } elseif ($role === 'unit' && !empty($ranting_id)) {
            $org_check = $conn->query("SELECT id FROM users WHERE role = 'unit' AND ranting_id = $ranting_id");
            if ($org_check->num_rows > 0) {
                $org_user_error = "Error: Unit/Ranting ini sudah memiliki user! (1 organisasi = 1 user)";
            }
        }
        
        if (!empty($org_user_error)) {
            $error = $org_user_error;
        } else {
            // Check username sudah ada
            $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
            if ($check->num_rows > 0) {
            $error = "Username sudah terdaftar!";
        } else {
            if (!empty($error)) {
                // Skip insert if there was an error with organisasi selection
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $sql = "INSERT INTO users (username, password, nama_lengkap, role, pengurus_id, ranting_id) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssii", $username, $hashed_password, $nama_lengkap, $role, $pengurus_id, $ranting_id);
                
                if ($stmt->execute()) {
                    $success = "User berhasil ditambahkan!";
                    header("Location: user_management.php");
                    exit();
                } else {
                    $error = "Error: " . $stmt->error;
                }
            }
        }
        }
    } elseif ($_POST['action_type'] == 'edit') {
        $edit_id = (int)$_POST['user_id'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        $pengurus_id = NULL;
        $ranting_id = NULL;
        $edit_error = '';
        
        // Validate and set pengurus_id based on role - REQUIRED for negara/pengprov/pengkot
        if (in_array($role, ['negara', 'pengprov', 'pengkot'])) {
            if (empty($_POST['pengurus_id'])) {
                $edit_error = "Error: Untuk role " . ucfirst($role) . ", Anda harus memilih organisasi dari dropdown!";
            } else {
                $pengurus_id = (int)$_POST['pengurus_id'];
                
                // Validate the pengurus_id exists in appropriate table
                if ($role === 'negara') {
                    $check = $conn->query("SELECT id FROM negara WHERE id = $pengurus_id");
                    if ($check->num_rows === 0) {
                        $edit_error = "Error: Negara yang dipilih tidak valid!";
                        $pengurus_id = NULL;
                    }
                } elseif ($role === 'pengprov') {
                    $check = $conn->query("SELECT id FROM provinsi WHERE id = $pengurus_id");
                    if ($check->num_rows === 0) {
                        $edit_error = "Error: Provinsi yang dipilih tidak valid!";
                        $pengurus_id = NULL;
                    }
                } elseif ($role === 'pengkot') {
                    $check = $conn->query("SELECT id FROM kota WHERE id = $pengurus_id");
                    if ($check->num_rows === 0) {
                        $edit_error = "Error: Kota yang dipilih tidak valid!";
                        $pengurus_id = NULL;
                    }
                }
            }
        }
        
        // Validate and set ranting_id for unit role
        if ($role === 'unit' && !empty($_POST['ranting_id'])) {
            $ranting_id = (int)$_POST['ranting_id'];
            $check = $conn->query("SELECT id FROM ranting WHERE id = $ranting_id");
            if ($check->num_rows === 0) $ranting_id = NULL;
        }
        
        if (!empty($edit_error)) {
            $error = $edit_error;
        } else {
            $sql = "UPDATE users SET nama_lengkap = ?, role = ?, pengurus_id = ?, ranting_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $nama_lengkap, $role, $pengurus_id, $ranting_id, $edit_id);
            
            if ($stmt->execute()) {
                $success = "User berhasil diupdate!";
                header("Location: user_management.php");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    } elseif ($_POST['action_type'] == 'reset_password') {
        $reset_id = (int)$_POST['user_id'];
        $new_password = $_POST['password'];
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed, $reset_id);
        
        if ($stmt->execute()) {
            $success = "Password berhasil direset!";
            header("Location: user_management.php");
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

// Hapus user
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    // Jangan hapus user sendiri
    if ($del_id == $_SESSION['user_id']) {
        $error = "Anda tidak bisa menghapus akun sendiri!";
    } else {
        $conn->query("DELETE FROM users WHERE id = $del_id");
        $success = "User berhasil dihapus!";
        header("Location: user_management.php");
        exit();
    }
}

// Ambil data semua user
$users_result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Ambil daftar (gabungkan negara, provinsi, kota)
$all_orgs = [];

// Get negara
$negara_result = $conn->query("SELECT id, nama, 'pusat' as jenis FROM negara ORDER BY nama");
while ($row = $negara_result->fetch_assoc()) {
    $all_orgs[] = $row;
}

// Get provinsi
$provinsi_result = $conn->query("SELECT id, nama, 'provinsi' as jenis FROM provinsi ORDER BY nama");
while ($row = $provinsi_result->fetch_assoc()) {
    $all_orgs[] = $row;
}

// Get kota
$kota_result = $conn->query("SELECT id, nama, 'kota' as jenis FROM kota ORDER BY nama");
while ($row = $kota_result->fetch_assoc()) {
    $all_orgs[] = $row;
}

// Ambil daftar ranting
$ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting ORDER BY nama_ranting");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Sistem Beladiri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; font-size: 13px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #dc3545; color: white; padding: 6px 12px; font-size: 12px; }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 13px; }
        
        .form-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .password-field { position: relative; }
        .password-field input { padding-right: 40px; }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .button-group { display: flex; gap: 10px; margin-top: 20px; }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .role-admin { background: #667eea; }
        .role-negara { background: #764ba2; }
        .role-pengprov { background: #f093fb; }
        .role-pengkot { background: #4facfe; }
        .role-unit { background: #43e97b; }
        .role-tamu { background: #6c757d; }
        
        .action-icons { display: flex; gap: 8px; }
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 13px;
        }
        .icon-edit { background-color: #f39c12; }
        .icon-edit:hover { background-color: #d68910; }
        .icon-reset { background-color: #f1c40f; }
        .icon-reset:hover { background-color: #f39c12; }
        .icon-delete { background-color: #e74c3c; }
        .icon-delete:hover { background-color: #c0392b; }
        .required { color: #dc3545; }
        
        /* Select2 alignment */
        .select2-container--default .select2-selection--single {
            height: 38px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
            padding-left: 10px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #999;
        }
        .select2-container {
            width: 100% !important;
        }
        .loading-text {
            font-size: 11px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php renderNavbar('👤 Kelola User'); ?>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Form Tambah User -->
        <div class="form-container">
            <h3>➕ Tambah User Baru</h3>
            
            <form method="POST" onsubmit="return validateUserForm()">
                <input type="hidden" name="action_type" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-field">
                            <input type="password" name="password" id="password_add" required>
                            <i class="fa fa-eye password-toggle" onclick="togglePassword('password_add', this)"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role" id="role_add" onchange="updateRoleFields(this, 'add')">
                            <option value="">-- Pilih Role --</option>
                            <option value="admin">Admin (Full Access)</option>
                            <option value="negara">Pengurus Pusat (Negara)</option>
                            <option value="pengprov">Pengurus Provinsi</option>
                            <option value="pengkot">Pengurus Kota / Kabupaten</option>
                            <option value="unit">Unit / Ranting</option>
                            <option value="tamu">Tamu (Read Only)</option>
                        </select>
                    </div>
                </div>
                
                <div id="negara_field_add" style="display: none;" class="form-group">
                    <label>Negara (Pusat) <span class="required">*</span></label>
                    <select name="negara_id" id="negara_select_add" class="dynamic-select">
                        <option value="">-- Pilih Negara --</option>
                    </select>
                    <div id="loading_negara_add" class="loading-text" style="display:none;">Memuat data...</div>
                </div>
                
                <div id="provinsi_field_add" style="display: none;" class="form-group">
                    <label>Provinsi <span class="required">*</span></label>
                    <select name="provinsi_id" id="provinsi_select_add" class="dynamic-select">
                        <option value="">-- Pilih Provinsi --</option>
                    </select>
                    <div id="loading_provinsi_add" class="loading-text" style="display:none;">Memuat data...</div>
                </div>
                
                <div id="kota_field_add" style="display: none;" class="form-group">
                    <label>Kota / Kabupaten <span class="required">*</span></label>
                    <select name="kota_id" id="kota_select_add" class="dynamic-select">
                        <option value="">-- Pilih Kota --</option>
                    </select>
                    <div id="loading_kota_add" class="loading-text" style="display:none;">Memuat data...</div>
                </div>
                
                <div id="ranting_field_add" style="display: none;" class="form-group">
                    <label>Unit / Ranting <span class="required">*</span></label>
                    <select name="ranting_id" id="ranting_select_add" class="dynamic-select">
                        <option value="">-- Pilih Unit/Ranting --</option>
                    </select>
                    <div id="loading_ranting_add" class="loading-text" style="display:none;">Memuat data...</div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">➕ Tambah User</button>
                </div>
            </form>
        </div>
        
        <!-- Daftar User -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Organisasi</th>
                        <th>Terdaftar</th>
                        <th style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $users_result->fetch_assoc()): 
                        // Ambil nama pengurus dan ranting
                        $pengurus_info = '';
                        if ($row['pengurus_id']) {
                            // Try to find in negara, provinsi, or kota
                            $org = $conn->query("SELECT nama FROM negara WHERE id = " . $row['pengurus_id'])->fetch_assoc();
                            if (!$org) $org = $conn->query("SELECT nama FROM provinsi WHERE id = " . $row['pengurus_id'])->fetch_assoc();
                            if (!$org) $org = $conn->query("SELECT nama FROM kota WHERE id = " . $row['pengurus_id'])->fetch_assoc();
                            if ($org) $pengurus_info = $org['nama'];
                        }
                        
                        if ($row['ranting_id']) {
                            $org = $conn->query("SELECT nama_ranting FROM ranting WHERE id = " . $row['ranting_id'])->fetch_assoc();
                            if ($org) {
                                $pengurus_info = ($pengurus_info ? $pengurus_info . ' - ' : '') . $org['nama_ranting'];
                            }
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td>
                            <span class="role-badge role-<?php echo $row['role']; ?>">
                                <?php 
                                $role_labels = [
                                    'admin' => 'Admin',
                                    'negara' => 'Negara',
                                    'pengprov' => 'Provinsi',
                                    'pengkot' => 'Kota',
                                    'unit' => 'Unit/Ranting',
                                    'tamu' => 'Tamu'
                                ];
                                echo $role_labels[$row['role']] ?? ucfirst($row['role']);
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($pengurus_info ?: '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="javascript:void(0)" onclick="editUser(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap']); ?>', '<?php echo $row['role']; ?>', '<?php echo $row['pengurus_id'] ?? ''; ?>', '<?php echo $row['ranting_id'] ?? ''; ?>')" class="icon-btn icon-edit" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                <a href="javascript:void(0)" onclick="resetPassword(<?php echo $row['id']; ?>)" class="icon-btn icon-reset" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </a>
                                <a href="user_management.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Yakin hapus?')" class="icon-btn icon-delete" title="Hapus User">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php else: ?>
                                <span style="color: #999; font-size: 11px;">(Self)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for all dynamic dropdowns
            $('.dynamic-select').select2({
                placeholder: "-- Pilih --",
                allowClear: true,
                width: '100%'
            });
            
            // Focus search box when dropdown opens
            $('.dynamic-select').on('select2:open', function() {
                const searchField = document.querySelector('.select2-search__field');
                if (searchField) {
                    searchField.focus();
                }
            });
        });
        
        // Cache for dropdown data
        let negaraData = [];
        let provinsiData = [];
        let kotaData = [];
        let rantingData = [];
        let dataLoaded = {
            negara: false,
            provinsi: false,
            kota: false,
            ranting: false
        };
        
        // Load data from API
        async function loadNegara() {
            if (dataLoaded.negara) return;
            const selectEl = document.getElementById('negara_select_add');
            if (!selectEl) return; // Element not found, field not visible
            const loadingEl = document.getElementById('loading_negara_add');
            if (loadingEl) loadingEl.style.display = 'block';
            try {
                const response = await fetch('../../api/get_negara.php');
                const result = await response.json();
                console.log('negara API result:', result);
                if (result.success && result.data.length > 0) {
                    negaraData = result.data;
                    dataLoaded.negara = true;
                    populateNegara('negara_select_add');
                } else {
                    console.error('No negara data or error:', result);
                }
            } catch (e) { console.error('Error loading negara:', e); }
            if (loadingEl) loadingEl.style.display = 'none';
        }
        
        async function loadProvinsi() {
            if (dataLoaded.provinsi) return;
            const selectEl = document.getElementById('provinsi_select_add');
            if (!selectEl) return;
            const loadingEl = document.getElementById('loading_provinsi_add');
            if (loadingEl) loadingEl.style.display = 'block';
            try {
                const response = await fetch('../../api/get_provinsi.php');
                const result = await response.json();
                console.log('provinsi API result:', result);
                if (result.success && result.data.length > 0) {
                    provinsiData = result.data;
                    dataLoaded.provinsi = true;
                    populateProvinsi('provinsi_select_add');
                } else {
                    console.error('No provinsi data or error:', result);
                }
            } catch (e) { console.error('Error loading provinsi:', e); }
            if (loadingEl) loadingEl.style.display = 'none';
        }
        
        async function loadKota() {
            if (dataLoaded.kota) return;
            const selectEl = document.getElementById('kota_select_add');
            if (!selectEl) return;
            const loadingEl = document.getElementById('loading_kota_add');
            if (loadingEl) loadingEl.style.display = 'block';
            try {
                const response = await fetch('../../api/get_kota.php');
                const result = await response.json();
                console.log('kota API result:', result);
                if (result.success && result.data.length > 0) {
                    kotaData = result.data;
                    dataLoaded.kota = true;
                    populateKota('kota_select_add');
                } else {
                    console.error('No kota data or error:', result);
                }
            } catch (e) { console.error('Error loading kota:', e); }
            if (loadingEl) loadingEl.style.display = 'none';
        }
        
        async function loadRanting() {
            if (dataLoaded.ranting) return;
            const selectEl = document.getElementById('ranting_select_add');
            if (!selectEl) return;
            const loadingEl = document.getElementById('loading_ranting_add');
            if (loadingEl) loadingEl.style.display = 'block';
            try {
                const response = await fetch('../../api/get_ranting.php');
                const result = await response.json();
                console.log('ranting API result:', result);
                if (result.success && result.data.length > 0) {
                    rantingData = result.data;
                    dataLoaded.ranting = true;
                    populateRanting('ranting_select_add');
                } else {
                    console.error('No ranting data or error:', result);
                }
            } catch (e) { console.error('Error loading ranting:', e); }
            if (loadingEl) loadingEl.style.display = 'none';
        }
        
        // Populate dropdown options
        function populateNegara(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Pilih Negara --</option>';
            negaraData.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.nama;
                select.appendChild(option);
            });
            console.log('Populated negara with', negaraData.length, 'items');
        }
        
        function populateProvinsi(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Pilih Provinsi --</option>';
            provinsiData.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.display || item.nama;
                select.appendChild(option);
            });
            console.log('Populated provinsi with', provinsiData.length, 'items');
        }
        
        function populateKota(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Pilih Kota --</option>';
            kotaData.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.display || item.nama;
                select.appendChild(option);
            });
            console.log('Populated kota with', kotaData.length, 'items');
        }
        
        function populateRanting(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
            rantingData.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.display || item.nama_ranting;
                select.appendChild(option);
            });
            console.log('Populated ranting with', rantingData.length, 'items');
        }
        
        function updateRoleFields(selectElement, prefix = '') {
            const role = selectElement.value;
            const negaraField = document.getElementById('negara_field_' + prefix);
            const provinsiField = document.getElementById('provinsi_field_' + prefix);
            const kotaField = document.getElementById('kota_field_' + prefix);
            const rantingField = document.getElementById('ranting_field_' + prefix);
            
            // Hide all fields first
            if (negaraField) negaraField.style.display = 'none';
            if (provinsiField) provinsiField.style.display = 'none';
            if (kotaField) kotaField.style.display = 'none';
            if (rantingField) rantingField.style.display = 'none';
            
            // Show appropriate field based on role and load data
            if (role === 'negara') {
                if (negaraField) {
                    negaraField.style.display = 'block';
                    loadNegara();
                }
            } else if (role === 'pengprov') {
                if (provinsiField) {
                    provinsiField.style.display = 'block';
                    loadProvinsi();
                }
            } else if (role === 'pengkot') {
                if (kotaField) {
                    kotaField.style.display = 'block';
                    loadKota();
                }
            } else if (role === 'unit') {
                if (rantingField) {
                    rantingField.style.display = 'block';
                    loadRanting();
                }
            }
        }
        
        function editUser(id, nama, role, pengurus_id, ranting_id) {
            let new_nama = prompt("Nama Lengkap:", nama);
            if (new_nama && new_nama.trim() !== '') {
                let form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action_type" value="edit">
                    <input type="hidden" name="user_id" value="${id}">
                    <input type="hidden" name="nama_lengkap" value="${new_nama}">
                    <input type="hidden" name="role" value="${role}">
                    <input type="hidden" name="pengurus_id" value="${pengurus_id || ''}">
                    <input type="hidden" name="ranting_id" value="${ranting_id || ''}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetPassword(id) {
            let new_pass = prompt("Password baru (min 6 karakter):");
            if (new_pass && new_pass.length >= 6) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action_type" value="reset_password">
                    <input type="hidden" name="user_id" value="${id}">
                    <input type="hidden" name="password" value="${new_pass}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Password minimal 6 karakter!');
            }
        }
        
        function validateForm() {
            const role = document.getElementById('role_add').value;
            
            if (role === 'negara') {
                const negaraValue = $('#negara_select_add').val();
                if (!negaraValue) {
                    alert('Mohon pilih Negara (Pusat)!');
                    $('#negara_select_add').focus();
                    return false;
                }
            } else if (role === 'pengprov') {
                const provinsiValue = $('#provinsi_select_add').val();
                if (!provinsiValue) {
                    alert('Mohon pilih Provinsi!');
                    $('#provinsi_select_add').focus();
                    return false;
                }
            } else if (role === 'pengkot') {
                const kotaValue = $('#kota_select_add').val();
                if (!kotaValue) {
                    alert('Mohon pilih Kota / Kabupaten!');
                    $('#kota_select_add').focus();
                    return false;
                }
            } else if (role === 'unit') {
                const rantingValue = $('#ranting_select_add').val();
                if (!rantingValue) {
                    alert('Mohon pilih Unit / Ranting!');
                    $('#ranting_select_add').focus();
                    return false;
                }
            }
            
            return true;
        }
        
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
        
        function validateUserForm() {
            // Check username
            const username = document.querySelector('input[name="username"]');
            if (!username.value.trim()) {
                alert('Mohon isi Username!');
                username.focus();
                return false;
            }
            
            // Check password
            const password = document.querySelector('input[name="password"]');
            if (!password.value.trim()) {
                alert('Mohon isi Password!');
                password.focus();
                return false;
            }
            
            // Check nama lengkap
            const namaLengkap = document.querySelector('input[name="nama_lengkap"]');
            if (!namaLengkap.value.trim()) {
                alert('Mohon isi Nama Lengkap!');
                namaLengkap.focus();
                return false;
            }
            
            // Check role
            const role = document.getElementById('role_add').value;
            if (!role) {
                alert('Mohon pilih Role!');
                document.getElementById('role_add').focus();
                return false;
            }
            
            // Check organization based on role
            if (role === 'negara') {
                const negaraSelect = document.getElementById('negara_select_add');
                const negaraValue = negaraSelect ? negaraSelect.value : '';
                console.log('negaraValue:', negaraValue);
                if (!negaraValue) {
                    alert('Mohon pilih Negara (Pusat)!');
                    return false;
                }
            } else if (role === 'pengprov') {
                const provinsiSelect = document.getElementById('provinsi_select_add');
                const provinsiValue = provinsiSelect ? provinsiSelect.value : '';
                console.log('provinsiValue:', provinsiValue);
                if (!provinsiValue) {
                    alert('Mohon pilih Provinsi!');
                    return false;
                }
            } else if (role === 'pengkot') {
                const kotaSelect = document.getElementById('kota_select_add');
                const kotaValue = kotaSelect ? kotaSelect.value : '';
                console.log('kotaValue:', kotaValue);
                if (!kotaValue) {
                    alert('Mohon pilih Kota / Kabupaten!');
                    return false;
                }
            } else if (role === 'unit') {
                const rantingSelect = document.getElementById('ranting_select_add');
                const rantingValue = rantingSelect ? rantingSelect.value : '';
                console.log('rantingValue:', rantingValue);
                if (!rantingValue) {
                    alert('Mohon pilih Unit / Ranting!');
                    return false;
                }
            }
            
            console.log('Form validation passed, submitting...');
            
            return true;
        }
    </script>
</body>
</html>