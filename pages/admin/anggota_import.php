<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (strtolower($file_ext) != 'csv') {
        $error = "Hanya format CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        $row_num = 1;
        $imported = 0;
        $errors = [];
        
        while ($row = fgetcsv($handle)) {
            $row_num++;
            if (count($row) < 7) continue;
            
            $no_anggota = $row[0];
            $nama_lengkap = $row[1];
            $tempat_lahir = $row[2];
            $tanggal_lahir = $row[3];
            $jenis_kelamin = $row[4];
            $jenis_anggota = $row[5];
            $tingkat_id = (int)($row[6] ?? 0);
            $ranting_saat_ini_id = isset($row[7]) ? (int)$row[7] : NULL;
            
            $check = $conn->query("SELECT id FROM anggota WHERE no_anggota = '$no_anggota'");
            if ($check->num_rows > 0) {
                $errors[] = "Baris $row_num: No Anggota sudah ada!";
                continue;
            }
            
            $sql = "INSERT INTO anggota (no_anggota, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, tingkat_id, ranting_saat_ini_id, jenis_anggota) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssiis", $no_anggota, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $tingkat_id, $ranting_saat_ini_id, $jenis_anggota);
            
            if ($stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Baris $row_num: Error";
            }
        }
        
        fclose($handle);
        $success = "Import selesai! $imported data berhasil ditambahkan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Anggota - Sistem Beladiri</title>
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
        
        .container { max-width: 600px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { margin-bottom: 10px; color: #333; }
        .description { color: #666; margin-bottom: 30px; line-height: 1.6; }
        
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="file"] {
            padding: 10px;
            border: 2px dashed #667eea;
            border-radius: 5px;
            width: 100%;
        }
        
        .template-info {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .template-info h4 { color: #667eea; margin-bottom: 10px; }
        .template-info p { font-size: 13px; color: #333; margin-bottom: 8px; font-family: monospace; overflow-wrap: anywhere; word-break: break-word; white-space: normal; }
        .description, .alert, .template-info { overflow-wrap: anywhere; word-break: break-word; white-space: normal; }
        .button-group { display: flex; gap: 10px; margin-top: 30px; flex-wrap: wrap; }
        .container { max-width: 600px; margin: 20px auto; padding: 0 20px; box-sizing: border-box; }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 10px; margin-top: 30px; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error { background: #fee; color: #c00; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #060; border: 1px solid #cfc; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>⬆️ Import Data Anggota</h2>
        <a href="anggota.php" style="color: white;">← Kembali</a>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Data Anggota</h1>
            <p class="description">Upload file CSV berisi data anggota baru.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="template-info">
                <h4>📋 Format CSV</h4>
                <p><strong>Header:</strong> no_anggota,nama_lengkap,tempat_lahir,tanggal_lahir,jenis_kelamin,jenis_anggota,tingkat_id,ranting_saat_ini_id</p>
                <p><strong>Contoh:</strong></p>
                <p>AGT-001,Budi Santoso,Jakarta,1990-05-15,L,murid,1,1</p>
                <p>AGT-002,Siti Nurhaliza,Bandung,1992-03-20,P,pelatih,2,2</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file_excel">Pilih File CSV</label>
                    <input type="file" id="file_excel" name="file_excel" accept=".csv" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">⬆️ Upload</button>
                    <a href="anggota.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>