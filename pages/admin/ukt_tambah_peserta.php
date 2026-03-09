<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include 'ukt_helper.php';

include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';
include '../../config/settings.php';


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

// For pengkot role on UKT pages, use custom permission check instead of general permission
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'pengkot' || $user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    // Continue to UKT-specific permission check later
} else {
    if (!$permission_manager->can('anggota_read')) {
        die("❌ Akses ditolak!");
    }
}

include '../../config/settings.php';

$id = (int)$_GET['id'];
$error = '';
$success = '';

// Handle messages from redirection
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $success = "Peserta berhasil dihapus!";
}

// Cek UKT ada
$ukt_check = $conn->query("SELECT * FROM ukt WHERE id = $id");
if ($ukt_check->num_rows == 0) {
    die("UKT tidak ditemukan!");
}

$ukt = $ukt_check->fetch_assoc();

// Get user role and pengurus_id
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

// Check if user can manage this UKT - special handling for pengkot
$can_manage = false;

if ($user_role === 'pengkot') {
    // Pengkot can only manage their own city UKT
    $can_manage = ($ukt['jenis_penyelenggara'] === 'kota' && (int)$ukt['penyelenggara_id'] === (int)$user_pengurus_id);
} elseif ($user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    $can_manage = $permission_manager->canManageUKT('ukt_update', $ukt['jenis_penyelenggara'], $ukt['penyelenggara_id']);
}

if (!$can_manage) {
    die("❌ Akses ditolak! Anda tidak memiliki izin untuk mengelola peserta UKT ini.");
}

// Proses form submit (single add)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['anggota_id'])) {
    $anggota_id = (int)$_POST['anggota_id'];
    // Ambil tingkat anggota langsung dari tabel anggota (kolom 'Tingkat' di form dihapus)
    $anggota_row = $conn->query("SELECT tingkat_id FROM anggota WHERE id = $anggota_id")->fetch_assoc();
    $tingkat_dari_id = isset($anggota_row['tingkat_id']) ? (int)$anggota_row['tingkat_id'] : 0;
    
    // Cek apakah anggota sudah terdaftar di UKT ini
    $check = $conn->query("SELECT id FROM ukt_peserta WHERE ukt_id = $id AND anggota_id = $anggota_id");
    if ($check->num_rows > 0) {
        $error = "Anggota sudah terdaftar di UKT ini!";
    } else {
        // Cari tingkat ke (next level)
        $current_tingkat = $conn->query("SELECT urutan FROM tingkatan WHERE id = $tingkat_dari_id")->fetch_assoc();
        $tingkat_ke_id = null;
        
        if ($current_tingkat) {
            $next_tingkat = $conn->query("SELECT id FROM tingkatan WHERE urutan = " . ($current_tingkat['urutan'] + 1) . " LIMIT 1");
            if ($next_tingkat->num_rows > 0) {
                $next_data = $next_tingkat->fetch_assoc();
                $tingkat_ke_id = $next_data['id'];
            }
        }
        
        $sql = "INSERT INTO ukt_peserta (ukt_id, anggota_id, tingkat_dari_id, tingkat_ke_id, status) 
                VALUES (?, ?, ?, ?, 'peserta')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $id, $anggota_id, $tingkat_dari_id, $tingkat_ke_id);
        
        if ($stmt->execute()) {
            $success = "Peserta berhasil ditambahkan!";
            // Clear form
            $_POST = array();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

// Ambil data peserta UKT untuk tabel bawah
$peserta_sql = "SELECT up.*, a.nama_lengkap, a.no_anggota, t1.nama_tingkat as tingkat_dari, t2.nama_tingkat as tingkat_ke
                FROM ukt_peserta up
                JOIN anggota a ON up.anggota_id = a.id
                LEFT JOIN tingkatan t1 ON up.tingkat_dari_id = t1.id
                LEFT JOIN tingkatan t2 ON up.tingkat_ke_id = t2.id
                WHERE up.ukt_id = $id
                ORDER BY up.id DESC"; // Terkini di atas

$peserta_result = $conn->query($peserta_sql);

// Ambil daftar anggota - Hapus query lama karena menggunakan AJAX Select2
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Peserta UKT - Sistem Beladiri</title>
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
        }
        
        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 10px; }
                
        .form-group { margin-bottom: 20px; }
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
            font-weight: 600; 
        }
        
        input[type="file"] {
            padding: 10px;
            border: 2px dashed #667eea;
            border-radius: 5px;
            width: 100%;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 5px; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .info-box strong { color: #667eea; }
        
        .anggota-info {
            background: white;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-top: 5px;
            font-size: 13px;
            display: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .anggota-info.show { display: block; }

        /* Ranting.php style consistency */
        .search-filter {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        
        .filter-section-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        /* Select2 alignment */
        .select2-container--default .select2-selection--single {
            height: 41px;
            padding: 6px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
            padding-left: 0;
            font-size: 14px;
            color: #333;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #999;
        }

        /* Table styles */
        .table-container {
            margin-top: 30px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eee;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 12px;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        /* Icon Button Styles */
        .action-icons {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
            color: white;
        }
        .icon-delete { background: #e74c3c; }
        .icon-delete:hover { background: #c0392b; }
        
        .no-peserta {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php renderNavbar('➕ Tambah Peserta UKT'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Tambah Peserta UKT</h1>
            <?php
            $wilayah_nama = '-';
            if ($ukt['jenis_penyelenggara'] == 'pusat') {
                $wilayah_res = $conn->query("SELECT nama FROM negara WHERE id = " . (int)$ukt['penyelenggara_id']);
                $wilayah_nama = $wilayah_res->fetch_assoc()['nama'] ?? '-';
            } elseif ($ukt['jenis_penyelenggara'] == 'provinsi') {
                $wilayah_res = $conn->query("SELECT nama FROM provinsi WHERE id = " . (int)$ukt['penyelenggara_id']);
                $wilayah_nama = $wilayah_res->fetch_assoc()['nama'] ?? '-';
            } elseif ($ukt['jenis_penyelenggara'] == 'kota') {
                $wilayah_res = $conn->query("SELECT nama FROM kota WHERE id = " . (int)$ukt['penyelenggara_id']);
                $wilayah_nama = $wilayah_res->fetch_assoc()['nama'] ?? '-';
            }
            ?>
            <p style="font-size:14px;color:#666;margin-bottom:25px;">
                <strong>UKT: <?php echo date('d M Y', strtotime($ukt['tanggal_pelaksanaan'])); ?> - <?php echo htmlspecialchars($ukt['lokasi']); ?> - <?php echo htmlspecialchars($wilayah_nama); ?></strong>
            </p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>ℹ️ Informasi:</strong> Pilih anggota. Tingkat target akan otomatis naik 1 level dari tingkat saat ini. 
                Sesuai dengan level UKT ini, Anda hanya dapat menambahkan anggota dengan range tingkat tertentu.
            </div>

            <form method="POST">
                <div class="search-filter">
                    <div class="filter-section-title">
                        <i class="fas fa-user-check"></i> Pilih Calon Peserta
                    </div>
                    
                    <div class="filter-row" style="grid-template-columns: 1fr;">
                        <div>
                            <label style="font-size: 11px; color: #666; font-weight: 500;">NAMA ANGGOTA <span class="required">*</span></label>
                            <select name="anggota_id" id="anggota_select" required>
                                <option value="">-- Pilih Anggota --</option>
                            </select>
                            <div class="form-hint" style="font-size:11px; margin-top: 8px;">
                                <i class="fas fa-info-circle"></i> Hanya menampilkan anggota di wilayah: <strong><?php echo htmlspecialchars($wilayah_nama); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <div id="anggota-info" class="anggota-info">
                        <div style="display: flex; gap: 20px;">
                            <div><strong>Tingkat Saat Ini:</strong> <span id="tingkat-saat-ini">-</span></div>
                            <div><strong>Tingkat Target:</strong> <span id="tingkat-target">-</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">+ Tambah Peserta</button>
                    <a href="ukt_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>

            <div class="table-container">
                <div style="padding: 15px; border-bottom: 1px solid #eee; background: #fafafa; font-weight: 600; font-size: 14px; color: #333;">
                    👥 Peserta yang Sudah Ditambahkan
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>No Anggota</th>
                            <th>Nama Anggota</th>
                            <th>Tingkat</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($peserta_result->num_rows > 0): ?>
                            <?php while ($row = $peserta_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo formatNoAnggotaDisplay($row['no_anggota'], $pengaturan_nomor); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                <td><span style="font-size: 11px; color: #666;"><?php echo htmlspecialchars($row['tingkat_dari']); ?></span> → <strong><?php echo htmlspecialchars($row['tingkat_ke']); ?></strong></td>
                                <td style="text-align: center;">
                                    <div class="action-icons" style="justify-content: center;">
                                        <a href="ukt_hapus_peserta.php?id=<?php echo $row['id']; ?>&ukt_id=<?php echo $id; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                           class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Hapus peserta ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="no-peserta">Belum ada peserta yang ditambahkan</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 25px; text-align: center;">
                <a href="ukt_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">← Kembali ke Detail UKT</a>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const anggotaSelect = $('#anggota_select');
        const anggotaInfo = document.getElementById('anggota-info');
        const tingkatSaatIni = document.getElementById('tingkat-saat-ini');
        const tingkatTarget = document.getElementById('tingkat-target');
        
        $(document).ready(function() {
            anggotaSelect.select2({
                placeholder: "-- Pilih Anggota --",
                allowClear: true,
                width: '100%',
                ajax: {
                    url: '../../api/get_anggota.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term,
                            jenis_peny: '<?php echo $ukt['jenis_penyelenggara']; ?>',
                            peny_id: '<?php echo $ukt['penyelenggara_id']; ?>'
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.display,
                                    no_anggota: item.no_anggota,
                                    nama: item.nama_lengkap,
                                    ranting: item.ranting
                                };
                            })
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            }).on('select2:open', function() {
                const searchField = document.querySelector('.select2-search__field');
                if (searchField) searchField.focus();
            }).on('change', function() {
                updateTingkat();
            });
        });

        function updateTingkat() {
            const anggotaId = anggotaSelect.val();
            
            if (anggotaId) {
                // Fetch info anggota untuk mendapatkan tingkat_id
                fetch('../../api/get_anggota.php?id=' + anggotaId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const tingkatId = data.data.tingkat_id;
                            const tingkatNama = data.data.nama_tingkat;
                            
                            anggotaInfo.classList.add('show');
                            tingkatSaatIni.textContent = tingkatNama;
                            
                            // Load tingkat target (next level)
                            fetch('get_next_tingkat.php?tingkat_id=' + tingkatId)
                                .then(response => response.json())
                                .then(nextData => {
                                    if (nextData.success && nextData.next_tingkat) {
                                        tingkatTarget.textContent = nextData.next_tingkat.nama_tingkat;
                                    } else {
                                        tingkatTarget.textContent = 'Pendekar (Tingkat Tertinggi)';
                                    }
                                });
                        }
                    });
            } else {
                anggotaInfo.classList.remove('show');
            }
        }
    </script>
</body>
</html>
