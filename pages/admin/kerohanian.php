<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT k.*, a.nama_lengkap, a.no_anggota, r.nama_ranting
        FROM kerohanian k
        JOIN anggota a ON k.anggota_id = a.id
        LEFT JOIN ranting r ON k.ranting_id = r.id
        WHERE 1=1";

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (a.nama_lengkap LIKE '%$search%' OR a.no_anggota LIKE '%$search%')";
}

$sql .= " ORDER BY k.tanggal_pembukaan DESC";

$result = $conn->query($sql);
$total_kerohanian = $result->num_rows;

$is_readonly = $_SESSION['role'] == 'user';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kerohanian - Sistem Beladiri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-warning {
            background: #ffc107;
            color: black;
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        input[type="text"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            flex: 1;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .action-icons {
            display: flex;
            gap: 8px;
        }
        
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            color: white;
        }
        
        .icon-view {
            background: #3498db;
        }
        
        .icon-view:hover {
            background: #2980b9;
        }
        
        .icon-edit {
            background: #f39c12;
        }
        
        .icon-edit:hover {
            background: #d68910;
        }
        
        .icon-delete {
            background: #e74c3c;
        }
        
        .icon-delete:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🙏 Manajemen Kerohanian</h2>
        <div>
            <span style="margin-right: 20px;">Halo, <?php echo $_SESSION['nama']; ?></span>
            <a href="../../index.php">← Kembali</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Kerohanian</h1>
                <p style="color: #666; margin-top: 5px;">Total: <strong><?php echo $total_kerohanian; ?> pembukaan</strong></p>
            </div>
            <?php if (!$is_readonly): ?>
            <a href="kerohanian_tambah.php" class="btn btn-primary">+ Tambah Kerohanian</a>
            <?php endif; ?>
        </div>
        
        <div class="search-filter">
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="Cari nama anggota atau no anggota..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">🔍 Cari</button>
                <a href="kerohanian.php" class="btn" style="background: #6c757d; color: white;">Reset</a>
            </form>
        </div>
        
        <div class="table-container">
            <?php if ($total_kerohanian > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>No Anggota</th>
                        <th>Nama Anggota</th>
                        <th>Unit/Ranting</th>
                        <th>Tanggal Pembukaan</th>
                        <th>Lokasi</th>
                        <th>Pembuka</th>                        
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo $row['no_anggota']; ?></strong></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_ranting'] ?? '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['tanggal_pembukaan'])); ?></td>
                        <td><?php echo htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['pembuka_nama'] ?? '-'); ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="kerohanian_detail.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!$is_readonly): ?>
                                <a href="kerohanian_edit.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="kerohanian_hapus.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus data ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>🔍 Tidak ada data kerohanian</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>