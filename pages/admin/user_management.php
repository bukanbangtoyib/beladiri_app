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
    $_SESSION['ranting_id'] ?? null
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
        $pengurus_id = !empty($_POST['pengurus_id']) ? (int)$_POST['pengurus_id'] : NULL;
        $ranting_id = !empty($_POST['ranting_id']) ? (int)$_POST['ranting_id'] : NULL;
        
        // Check username sudah ada
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $error = "Username sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO users (username, password, nama_lengkap, role, pengurus_id, ranting_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $username, $hashed_password, $nama_lengkap, $role, $pengurus_id, $ranting_id);
            
            if ($stmt->execute()) {
                $success = "User berhasil ditambahkan!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    } elseif ($_POST['action_type'] == 'edit') {
        $edit_id = (int)$_POST['user_id'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        $pengurus_id = !empty($_POST['pengurus_id']) ? (int)$_POST['pengurus_id'] : NULL;
        $ranting_id = !empty($_POST['ranting_id']) ? (int)$_POST['ranting_id'] : NULL;
        
        $sql = "UPDATE users SET nama_lengkap = ?, role = ?, pengurus_id = ?, ranting_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $nama_lengkap, $role, $pengurus_id, $ranting_id, $edit_id);
        
        if ($stmt->execute()) {
            $success = "User berhasil diupdate!";
        } else {
            $error = "Error: " . $stmt->error;
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
        label { display: block; margin-bottom: 8px; font-weight: 600; }
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
        .role-pengprov { background: #f093fb; }
        .role-pengkot { background: #4facfe; }
        .role-unit { background: #43e97b; }
        .role-tamu { background: #6c757d; }
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
            
            <form method="POST">
                <input type="hidden" name="action_type" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="role_add" onchange="updateRoleFields(this, 'add')" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="admin">Admin (Full Access)</option>
                            <option value="pengprov">Pengurus Provinsi</option>
                            <option value="pengkot">Pengurus Kota / Kabupaten</option>
                            <option value="unit">Unit / Ranting</option>
                            <option value="tamu">Tamu (Read Only)</option>
                        </select>
                    </div>
                </div>
                
                <div id="pengurus_field_add" style="display: none;" class="form-group">
                    <label>Pengurus</label>
                    <select name="pengurus_id">
                        <option value="">-- Pilih Pengurus (Opsional) --</option>
                        <?php 
                        $pengurus_result->data_seek(0);
                        while ($row = $pengurus_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_pengurus']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div id="ranting_field_add" style="display: none;" class="form-group">
                    <label>Unit / Ranting</label>
                    <select name="ranting_id">
                        <option value="">-- Pilih Unit/Ranting (Opsional) --</option>
                        <?php 
                        $ranting_result->data_seek(0);
                        while ($row = $ranting_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_ranting']); ?></option>
                        <?php endwhile; ?>
                    </select>
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
                        <th>Aksi</th>
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
                                    'pengprov' => 'PengProv',
                                    'pengkot' => 'PengKot',
                                    'unit' => 'Unit',
                                    'tamu' => 'Tamu'
                                ];
                                echo $role_labels[$row['role']] ?? ucfirst($row['role']);
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($pengurus_info ?: '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="#" onclick="editUser(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap']); ?>', '<?php echo $row['role']; ?>', '<?php echo $row['pengurus_id'] ?? ''; ?>', '<?php echo $row['ranting_id'] ?? ''; ?>')" style="color: #667eea; text-decoration: none; font-weight: 600; margin-right: 10px;">Edit</a>
                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <a href="#" onclick="resetPassword(<?php echo $row['id']; ?>)" style="color: #ffc107; text-decoration: none; font-weight: 600; margin-right: 10px;">Reset Pass</a>
                            <a href="user_management.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Yakin hapus?')" style="color: #dc3545; text-decoration: none; font-weight: 600;">Hapus</a>
                            <?php else: ?>
                            <span style="color: #999; font-size: 12px;">(Akun Anda)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function updateRoleFields(selectElement, prefix = '') {
            const role = selectElement.value;
            const pengurusField = document.getElementById('pengurus_field_' + prefix);
            const rantingField = document.getElementById('ranting_field_' + prefix);
            
            // Sembunyikan kedua field terlebih dahulu
            pengurusField.style.display = 'none';
            rantingField.style.display = 'none';
            
            // Tampilkan field sesuai role
            if (role === 'pengprov' || role === 'pengkot') {
                pengurusField.style.display = 'block';
            } else if (role === 'unit') {
                rantingField.style.display = 'block';
            }
        }
        
        function editUser(id, nama, role, pengurus_id, ranting_id) {
            let new_nama = prompt("Nama Lengkap:", nama);
            if (new_nama) {
                let new_role = prompt("Role:\n- admin\n- pengprov\n- pengkot\n- unit\n- tamu", role);
                if (new_role && ['admin', 'pengprov', 'pengkot', 'unit', 'tamu'].includes(new_role)) {
                    let new_pengurus = pengurus_id;
                    let new_ranting = ranting_id;
                    
                    if (new_role === 'pengprov' || new_role === 'pengkot') {
                        new_pengurus = prompt("ID Pengurus (atau kosongkan):", pengurus_id || '');
                    } else if (new_role === 'unit') {
                        new_ranting = prompt("ID Unit/Ranting (atau kosongkan):", ranting_id || '');
                    }
                    
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action_type" value="edit">
                        <input type="hidden" name="user_id" value="${id}">
                        <input type="hidden" name="nama_lengkap" value="${new_nama}">
                        <input type="hidden" name="role" value="${new_role}">
                        <input type="hidden" name="pengurus_id" value="${new_pengurus}">
                        <input type="hidden" name="ranting_id" value="${new_ranting}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
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
    </script>
</body>
</html>