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

$result = $conn->query("SELECT * FROM pengurus WHERE id = $id");
if ($result->num_rows == 0) {
    die("Pengurus tidak ditemukan!");
}

$pengurus = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_pengurus = $conn->real_escape_string($_POST['nama_pengurus']);
    $ketua_nama = $conn->real_escape_string($_POST['ketua_nama']);
    $sk_kepengurusan = $conn->real_escape_string($_POST['sk_kepengurusan']);
    $periode_mulai = $_POST['periode_mulai'];
    $periode_akhir = $_POST['periode_akhir'];
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $pengurus_induk_id = $_POST['pengurus_induk_id'] ?: NULL;
    
    $sql = "UPDATE pengurus SET 
            nama_pengurus = ?, ketua_nama = ?, sk_kepengurusan = ?,
            periode_mulai = ?, periode_akhir = ?, alamat_sekretariat = ?,
            pengurus_induk_id = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssii", $nama_pengurus, $ketua_nama, $sk_kepengurusan,
                     $periode_mulai, $periode_akhir, $alamat, $pengurus_induk_id, $id);
    
    if ($stmt->execute()) {
        $success = "Data berhasil diupdate!";
        header("refresh:2;url=pengurus_detail.php?id=$id");
    } else {
        $error = "Error: " . $stmt->error;
    }
}

// Ambil pengurus induk
$pengurus_induk = [];
if ($pengurus['jenis_pengurus'] == 'provinsi') {
    $result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'pusat' ORDER BY nama_pengurus");
    while ($row = $result->fetch_assoc()) {
        $pengurus_induk[] = $row;
    }
} elseif ($pengurus['jenis_pengurus'] == 'kota') {
    $result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'provinsi' ORDER BY nama_pengurus");
    while ($row = $result->fetch_assoc()) {
        $pengurus_induk[] = $row;
    }
}

$label_jenis = [
    'pusat' => 'Pengurus Pusat',
    'provinsi' => 'Pengurus Provinsi',
    'kota' => 'Pengurus Kota'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?> - Sistem Beladiri</title>
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
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
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
        
        input[type="text"], input[type="date"], select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea { resize: vertical; min-height: 100px; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-row.full { grid-template-columns: 1fr; }
        
        .required { color: #dc3545; }
        
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; text-decoration: none; }
        
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
        <h2>✏️ Edit <?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?></h2>
        <a href="pengurus_detail.php?id=<?php echo $id; ?>" style="color: white;">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h1>Edit Data <?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?></h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Nama <?php echo $label_jenis[$pengurus['jenis_pengurus']]; ?> <span class="required">*</span></label>
                    <input type="text" name="nama_pengurus" value="<?php echo htmlspecialchars($pengurus['nama_pengurus']); ?>" required>
                </div>
                
                <?php if (count($pengurus_induk) > 0): ?>
                <div class="form-group">
                    <label><?php echo $pengurus['jenis_pengurus'] == 'provinsi' ? 'Pengurus Pusat yang Menaungi' : 'Pengurus Provinsi yang Menaungi'; ?> <span class="required">*</span></label>
                    <select name="pengurus_induk_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($pengurus_induk as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $pengurus['pengurus_induk_id'] == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nama_pengurus']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Ketua <span class="required">*</span></label>
                        <input type="text" name="ketua_nama" value="<?php echo htmlspecialchars($pengurus['ketua_nama'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>No SK Kepengurusan <span class="required">*</span></label>
                        <input type="text" name="sk_kepengurusan" value="<?php echo htmlspecialchars($pengurus['sk_kepengurusan'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Periode Mulai <span class="required">*</span></label>
                        <input type="date" name="periode_mulai" value="<?php echo $pengurus['periode_mulai']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Periode Akhir <span class="required">*</span></label>
                        <input type="date" name="periode_akhir" value="<?php echo $pengurus['periode_akhir']; ?>" required>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Alamat Sekretariat <span class="required">*</span></label>
                        <textarea name="alamat" required><?php echo htmlspecialchars($pengurus['alamat_sekretariat'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="pengurus_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>