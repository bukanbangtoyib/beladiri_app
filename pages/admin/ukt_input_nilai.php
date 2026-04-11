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

// Pastikan kolom untuk nilai per materi dan rata-rata ada di tabel ukt_peserta
$required_columns = ['nilai_a','nilai_b','nilai_c','nilai_d','nilai_e','nilai_f','nilai_g','nilai_h','nilai_i','nilai_j','rata_rata'];
foreach ($required_columns as $col) {
    $col_check = $conn->query("SHOW COLUMNS FROM ukt_peserta LIKE '$col'");
    if ($col_check->num_rows == 0) {
        $conn->query("ALTER TABLE ukt_peserta ADD COLUMN `$col` DOUBLE DEFAULT NULL");
    }
}

$id = (int)$_GET['id'];
$error = '';
$success = '';

// Cek UKT ada
$ukt_check = $conn->query("SELECT * FROM ukt WHERE id = $id");
if ($ukt_check->num_rows == 0) {
    die("UKT tidak ditemukan!");
}
$ukt = $ukt_check->fetch_assoc();

// Check if user can manage this UKT - special handling for pengkot
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

$can_manage = false;

if ($user_role === 'pengkot') {
    // Pengkot can only manage their own city UKT
    $can_manage = ($ukt['jenis_penyelenggara'] === 'kota' && (int)$ukt['penyelenggara_id'] === (int)$user_pengurus_id);
} elseif ($user_role === 'admin' || $user_role === 'negara' || $user_role === 'pengprov') {
    $can_manage = $permission_manager->canManageUKT('ukt_update', $ukt['jenis_penyelenggara'], $ukt['penyelenggara_id']);
}

if (!$can_manage) {
    die("❌ Akses ditolak! Anda tidak memiliki izin untuk menginput nilai UKT ini.");
}

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST['peserta'] as $peserta_id => $data) {
        $peserta_id = (int)$peserta_id;

        // Ambil nilai materi A-J
        $letters = ['a','b','c','d','e','f','g','h','i','j'];
        $sum = 0;
        $count = 0;
        $vals = [];
        
        foreach ($letters as $l) {
            $v = isset($data[$l]) && $data[$l] !== '' ? (float)$data[$l] : null;
            $vals[$l] = $v;
            if ($v !== null) {
                $sum += $v;
                $count++;
            }
        }

        // Rata-rata hanya dihitung dari kolom yang ada isinya
        $avg = $count > 0 ? ($sum / $count) : null;
        $status = 'peserta';
        
        if ($avg !== null) {
            if ($avg >= 60) {
                $status = 'lulus';
            } else {
                $status = 'tidak_lulus';
            }
        }

        // Simpan semua nilai dan rata-rata
        $sql = "UPDATE ukt_peserta SET nilai_a = ?, nilai_b = ?, nilai_c = ?, nilai_d = ?, 
                nilai_e = ?, nilai_f = ?, nilai_g = ?, nilai_h = ?, nilai_i = ?, nilai_j = ?, 
                rata_rata = ?, status = ? 
                WHERE id = ? AND ukt_id = ?";
        $stmt = $conn->prepare($sql);
        
        $avg_for_db = $avg;
        $stmt->bind_param("dddddddddddsii",
            $vals['a'], $vals['b'], $vals['c'], $vals['d'], $vals['e'], 
            $vals['f'], $vals['g'], $vals['h'], $vals['i'], $vals['j'],
            $avg_for_db,
            $status,
            $peserta_id,
            $id
        );

        if (!$stmt->execute()) {
            $error = "Error: " . $stmt->error;
            break;
        } else {
            // Jika lulus, naikkan tingkat anggota otomatis
            if ($status == 'lulus') {
                // Get anggota_id dari ukt_peserta
                $peserta_data = $conn->query("SELECT anggota_id FROM ukt_peserta WHERE id = $peserta_id")->fetch_assoc();
                $anggota_id = $peserta_data['anggota_id'];
                
                // Update ukt_terakhir ke tanggal hari ini
                $today = date('Y-m-d');
                $conn->query("UPDATE anggota SET ukt_terakhir = '$today' WHERE id = $anggota_id");
                
                // Get tingkat_id saat ini
                $anggota_data = $conn->query("SELECT tingkat_id FROM anggota WHERE id = $anggota_id")->fetch_assoc();
                $current_tingkat = $anggota_data['tingkat_id'];  // Ini sekarang URUTAN (1-13), bukan ID
                
                if (!empty($current_tingkat)) {
                    // Cari tingkat berikutnya berdasarkan urutan
                    // PENTING: current_tingkat sekarang adalah urutan, bukan id!
                    $next_tingkat_query = $conn->query("
                        SELECT t2.urutan FROM tingkatan t1
                        JOIN tingkatan t2 ON t2.urutan = t1.urutan + 1
                        WHERE t1.urutan = $current_tingkat
                        LIMIT 1
                    ");
                    
                    if ($next_tingkat_query->num_rows > 0) {
                        $next_tingkat = $next_tingkat_query->fetch_assoc();
                        $next_tingkat_urutan = $next_tingkat['urutan'];  // Ambil urutan, bukan id
                        
                        // Update tingkat anggota dengan urutan
                        $conn->query("UPDATE anggota SET tingkat_id = $next_tingkat_urutan WHERE id = $anggota_id");
                    }
                }
            }
        }
    }

    if (!$error) {
        $success = "Nilai peserta berhasil disimpan!";
    }
}

// Ambil data peserta UKT
$peserta_sql = "SELECT up.id, up.anggota_id, up.status, up.nilai_a, up.nilai_b, up.nilai_c, up.nilai_d, 
                up.nilai_e, up.nilai_f, up.nilai_g, up.nilai_h, up.nilai_i, up.nilai_j, up.rata_rata, 
                a.nama_lengkap, a.no_anggota
                FROM ukt_peserta up
                JOIN anggota a ON up.anggota_id = a.id
                WHERE up.ukt_id = $id
                ORDER BY a.nama_lengkap";

$peserta_result = $conn->query($peserta_sql);
$total_peserta = $peserta_result->num_rows;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai UKT - Sistem Beladiri</title>
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
        
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 10px; }     
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table { 
            width: auto; 
            border-collapse: collapse; 
            margin-top: 0;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
            min-width: 40px;
        }
        
        td { 
            padding: 12px; 
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        th:nth-child(1), th:nth-child(2) {
            text-align: left;
        }
        
        td:nth-child(1), td:nth-child(2) {
            text-align: left;
        }
        
        tr:hover { background: #f9f9f9; }
        
        /* PERBAIKAN 1: Kolom isian diperlebar */
        input[type="text"] {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            width: 100%;
            text-align: center;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Style untuk status dengan background warna */
        .status-display {
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            min-width: 90px;
            text-align: center;
            white-space: nowrap;
        }
        
        .status-display.lulus {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-display.tidak_lulus {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-display.peserta {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        
        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .info-box strong {
            color: #667eea;
        }
        
        .rata-rata-cell {
            font-weight: 600;
            background: #f8f9fa;
            text-align: center;
            min-width: 80px;
        }
    </style>
</head>
<body>
    <?php renderNavbar('📊 Input Nilai UKT'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Input Nilai Peserta UKT</h1>
            <p style="color: #666; font-size: 14px; margin-bottom: 25px;">UKT: <strong><?php echo date('d M Y', strtotime($ukt['tanggal_pelaksanaan'])); ?> - <?php echo htmlspecialchars($ukt['lokasi']); ?></strong></p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>ℹ️ Catatan:</strong> Rata-rata dihitung hanya dari kolom yang ada nilai. Peserta dinyatakan LULUS jika rata-rata ≥ 60. Jika LULUS, tingkat anggota otomatis naik 1 level dan ukt_terakhir terupdate.
            </div>
            
            <!-- Action buttons untuk import CSV -->
            <div class="action-buttons">
                <a href="ukt_import_nilai.php?ukt_id=<?php echo $id; ?>" class="btn btn-success">
                    📥 Import CSV
                </a>
            </div>
            
            
            <!-- Form Input Manual -->
            <div id="manual-form" style="display: block;">
                <?php if ($total_peserta > 0): ?>
                <form method="POST">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th style="text-align: left; width: 180px;">No Anggota</th>
                                    <th style="text-align: left; width: 200px;">Nama Anggota</th>
                                    <th>A</th>
                                    <th>B</th>
                                    <th>C</th>
                                    <th>D</th>
                                    <th>E</th>
                                    <th>F</th>
                                    <th>G</th>
                                    <th>H</th>
                                    <th>I</th>
                                    <th>J</th>
                                    <th style="width: 80px;">Rata-rata</th>
                                    <th style="width: 100px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $peserta_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo formatNoAnggotaDisplay($row['no_anggota'], $pengaturan_nomor); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>

                                    <?php $letters = ['a','b','c','d','e','f','g','h','i','j']; ?>
                                    <?php foreach ($letters as $l): ?>
                                        <td>
                                            <input type="text" inputmode="decimal" pattern="^[0-9]+(\.[0-9]+)?$" 
                                                   name="peserta[<?php echo $row['id']; ?>][<?php echo $l; ?>]" 
                                                   class="materi" data-id="<?php echo $row['id']; ?>"
                                                   value="<?php echo isset($row['nilai_'.$l]) && $row['nilai_'.$l] !== null ? $row['nilai_'.$l] : ''; ?>">
                                        </td>
                                    <?php endforeach; ?>

                                    <td class="rata-rata-cell">
                                        <span class="rata" id="rata-<?php echo $row['id']; ?>">
                                            <?php echo isset($row['rata_rata']) && $row['rata_rata'] !== null ? round($row['rata_rata'], 2) : '-'; ?>
                                        </span>
                                    </td>

                                    <td style="text-align: center;">
                                        <input type="hidden" name="peserta[<?php echo $row['id']; ?>][status]" 
                                               id="status-input-<?php echo $row['id']; ?>" 
                                               value="<?php echo $row['status']; ?>">
                                        <span class="status-display status-<?php echo $row['id']; ?> <?php echo strtolower($row['status']); ?>" 
                                              id="status-<?php echo $row['id']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">💾 Simpan Nilai</button>
                        <a href="ukt_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
                <?php else: ?>
                <div class="no-data">🔭 Belum ada peserta UKT</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleForm(type) {
            const manualForm = document.getElementById('manual-form');
            const csvForm = document.getElementById('csv-form');
            
            if (type === 'manual') {
                manualForm.style.display = 'block';
                csvForm.style.display = 'none';
            } else {
                manualForm.style.display = 'none';
                csvForm.style.display = 'block';
            }
        }
        
        // Compute rata-rata hanya dari kolom yang ada isinya
        function computeRow(id) {
            const letters = ['a','b','c','d','e','f','g','h','i','j'];
            let sum = 0;
            let count = 0;
            
            letters.forEach(l => {
                const el = document.querySelector('input[name="peserta['+id+']['+l+']"]');
                if (el) {
                    const v = parseFloat(el.value);
                    if (!isNaN(v) && el.value !== '') {
                        sum += v;
                        count++;
                    }
                }
            });
            
            const rataEl = document.getElementById('rata-'+id);
            const statusInput = document.getElementById('status-input-'+id);
            const statusDisplay = document.getElementById('status-'+id);

            if (count > 0) {
                const avg = sum / count;
                rataEl.textContent = avg.toFixed(2);
                
                let status, statusClass;
                if (avg >= 60) {
                    status = 'lulus';
                    statusClass = 'lulus';
                } else {
                    status = 'tidak_lulus';
                    statusClass = 'tidak_lulus';
                }
                
                statusInput.value = status;
                statusDisplay.textContent = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
                statusDisplay.className = 'status-display status-' + id + ' ' + statusClass;
            } else {
                rataEl.textContent = '-';
                statusInput.value = 'peserta';
                statusDisplay.textContent = 'Peserta';
                statusDisplay.className = 'status-display status-' + id + ' peserta';
            }
        }

        // Event listener untuk perubahan input
        document.querySelectorAll('.materi').forEach(el => {
            const id = el.getAttribute('data-id');
            el.addEventListener('input', (e) => {
                // Hanya izinkan angka dan titik
                let val = e.target.value;
                val = val.replace(/[^0-9.]/g, '');
                
                // Pastikan hanya satu titik
                if ((val.match(/\./g) || []).length > 1) {
                    val = val.substring(0, val.lastIndexOf('.'));
                }
                
                e.target.value = val;
                computeRow(id);
            });
        });

        // Initialize pada load
        (function() {
            const ids = new Set();
            document.querySelectorAll('.materi').forEach(el => ids.add(el.getAttribute('data-id')));
            ids.forEach(id => computeRow(id));
        })();
        
        function showCSVTemplate(e) {
            e.preventDefault();
            alert('Format CSV:\n\nNo Anggota,Nilai A,Nilai B,Nilai C,Nilai D,Nilai E,Nilai F,Nilai G,Nilai H,Nilai I,Nilai J\n12345,75,80,85,70,65,90,88,75,82,79\n12346,60,65,70,75,80,85,90,75,70,65');
        }
    </script>
</body>
</html>