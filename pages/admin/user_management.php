<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

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
        
        // Check username sudah ada
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $error = "Username sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $hashed_password, $nama_lengkap, $role);
            
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
        
        $sql = "UPDATE users SET nama_lengkap = ?, role = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nama_lengkap, $role, $edit_id);
        
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
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        
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
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        
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
        input:focus, select:focus { outline: none; border-color: #667eea; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .button-group { display: flex; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>👤 Kelola User</h2>
        <a href="../../index.php" style="color: white;">← Kembali</a>
    </div>
    
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
                        <select name="role" required>
                            <option value="user">User (Readonly)</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                    </div>
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
                        <th>Terdaftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $users_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td>
                            <span style="background: <?php echo ($row['role'] == 'admin' ? '#667eea' : '#6c757d'); ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">
                                <?php echo ucfirst($row['role']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="#" onclick="editUser(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap']); ?>', '<?php echo $row['role']; ?>')" style="color: #667eea; text-decoration: none; font-weight: 600;">Edit</a> |
                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <a href="#" onclick="resetPassword(<?php echo $row['id']; ?>)" style="color: #ffc107; text-decoration: none; font-weight: 600;">Reset Pass</a> |
                            <a href="user_management.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Yakin hapus?')" style="color: #dc3545; text-decoration: none; font-weight: 600;">Hapus</a>
                            <?php else: ?>
                            <span style="color: #999;">(Akun Anda)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function editUser(id, nama, role) {
            let new_nama = prompt("Nama Lengkap:", nama);
            if (new_nama) {
                let new_role = prompt("Role (admin/user):", role);
                if (new_role && (new_role == 'admin' || new_role == 'user')) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action_type" value="edit">
                        <input type="hidden" name="user_id" value="${id}">
                        <input type="hidden" name="nama_lengkap" value="${new_nama}">
                        <input type="hidden" name="role" value="${new_role}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        function resetPassword(id) {
            let new_pass = prompt("Password baru:");
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