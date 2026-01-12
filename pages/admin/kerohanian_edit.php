<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];
$error = '';
$success = '';

$result = $conn->query("SELECT * FROM kerohanian WHERE id = $id");
if ($result->num_rows == 0) {
    die("Data kerohanian tidak ditemukan!");
}

$kerohanian = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal_pembukaan = $_POST['tanggal_pembukaan'];
    $lokasi = $conn->real_escape_string($_POST['lokasi']);
    $pembuka_nama = $conn->real_escape_string($_POST['pembuka_nama']);
    $ranting_id = !empty($_POST['ranting_id']) ? (int)$_POST['ranting_id'] : NULL;
    
    $sql = "UPDATE kerohanian SET tanggal_pembukaan = ?, lokasi = ?, pembuka_nama = ?, ranting_id = ? WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $tanggal_pembukaan, $lokasi, $pembuka_nama, $ranting_id, $id);
    
    if ($stmt->execute()) {
        $success = "Data kerohanian berhasil diupdate!";
        header("refresh:2;url=kerohanian_detail.php?id=$id");
    } else {
        $error = "Error: " . $stmt->error;
    }
}

// Ambil daftar ranting
$ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting ORDER BY nama_ranting");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kerohanian - Sistem Beladiri</title>
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
        
        input[type="text"], input[type="date"], select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .required { color: #dc3545; }
        
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
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 15px; margin-top: 35px; }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>✏️ Edit Kerohanian</h2>
        <a href="kerohanian_detail.php?id=<?php echo $id; ?>" style="color: white;">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h1>Edit Data Kerohanian</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Pembukaan <span class="required">*</span></label>
                        <input type="date" name="tanggal_pembukaan" value="<?php echo $kerohanian['tanggal_pembukaan']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Unit/Ranting</label>
                        <select name="ranting_id">
                            <option value="">-- Pilih Unit/Ranting --</option>
                            <?php while ($row = $ranting_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $kerohanian['ranting_id'] == $row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nama_ranting']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Lokasi Pembukaan <span class="required">*</span></label>
                    <input type="text" name="lokasi" value="<?php echo htmlspecialchars($kerohanian['lokasi']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Nama Pembuka<span class="required">*</span></label>
                    <input type="text" name="pembuka_nama" value="<?php echo htmlspecialchars($kerohanian['pembuka_nama']); ?>" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="kerohanian_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>