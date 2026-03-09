<?php
session_start();

if (!isset($_SESSION['user_id'])) {
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

// Get user info for permission checks
$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;

$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'pusat';
if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

// Permission check: Ensure user can add data based on their role
if ($user_role === 'negara') {
    // Negara can only add province and kota, not themselves
    if ($jenis === 'pusat') {
        die("❌ Anda tidak dapat menambahkan negara!");
    }
} elseif ($user_role === 'pengprov') {
    // Pengprov can only add kota, not province
    if ($jenis === 'provinsi') {
        die("❌ Anda tidak dapat menambahkan provinsi!");
    }
    if ($jenis === 'pusat') {
        die("❌ Akses ditolak!");
    }
} elseif ($user_role === 'pengkot') {
    // Pengkot can only add kota (ranting is handled in another page)
    if ($jenis !== 'kota') {
        die("❌ Anda tidak dapat menambahkan data ini!");
    }
} elseif ($user_role !== 'admin') {
    die("❌ Akses ditolak!");
}

// Map jenis to table and column names
$table_map = [
    'pusat' => ['table' => 'negara', 'label' => 'Negara'],
    'provinsi' => ['table' => 'provinsi', 'label' => 'Provinsi'],
    'kota' => ['table' => 'kota', 'label' => 'Kota/Kabupaten']
];

$table_info = $table_map[$jenis];
$table = $table_info['table'];
$label_jenis = $table_info['label'];

$error = '';
$success = '';

// Get parent data for dropdowns
$negara_list = [];
$provinsi_list = [];
$auto_selected_negara = 0;
$auto_selected_provinsi = 0;
$auto_selected_kota = 0;

if ($jenis == 'provinsi' || $jenis == 'kota') {
    if ($user_role === 'negara') {
        // Negara can only add to their own negara - auto-select
        $auto_selected_negara = $user_pengurus_id;
        $negara_result = $conn->query("SELECT id, kode, nama FROM negara WHERE id = " . (int)$auto_selected_negara);
    } else {
        $negara_result = $conn->query("SELECT id, kode, nama FROM negara ORDER BY nama");
    }
    while ($row = $negara_result->fetch_assoc()) {
        $negara_list[] = $row;
    }
}

if ($jenis == 'kota') {
    if ($user_role === 'negara') {
        // Get provinces in their negara
        $provinsi_result = $conn->query("SELECT id, kode, nama, negara_id FROM provinsi WHERE negara_id = " . (int)$user_pengurus_id . " ORDER BY nama");
    } elseif ($user_role === 'pengprov') {
        // Pengprov can only add to their own province - auto-select
        $auto_selected_provinsi = $user_pengurus_id;
        // Get their negara too
        $prov_check = $conn->query("SELECT negara_id FROM provinsi WHERE id = " . (int)$user_pengurus_id);
        if ($prov_check && $pc = $prov_check->fetch_assoc()) {
            $auto_selected_negara = $pc['negara_id'];
        }
        $provinsi_result = $conn->query("SELECT id, kode, nama, negara_id FROM provinsi WHERE id = " . (int)$user_pengurus_id);
    } elseif ($user_role === 'pengkot') {
        // Pengkot can only add ranting under their kota - auto-select all
        $auto_selected_provinsi = 0; // Will be fetched
        $auto_selected_kota = $user_pengurus_id;
        // Get their provinsi and negara
        $kota_check = $conn->query("SELECT negara_id, provinsi_id FROM kota WHERE id = " . (int)$user_pengurus_id);
        if ($kota_check && $kc = $kota_check->fetch_assoc()) {
            $auto_selected_negara = $kc['negara_id'];
            $auto_selected_provinsi = $kc['provinsi_id'];
        }
        $provinsi_result = $conn->query("SELECT id, kode, nama, negara_id FROM provinsi WHERE id = " . (int)$auto_selected_provinsi);
    } else {
        $provinsi_result = $conn->query("SELECT id, kode, nama, negara_id FROM provinsi ORDER BY nama");
    }
    while ($row = $provinsi_result->fetch_assoc()) {
        $provinsi_list[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $conn->real_escape_string($_POST['nama'] ?? '');
    $kode = isset($_POST['kode']) ? strtoupper($conn->real_escape_string($_POST['kode'])) : '';
    $kode_otomatis = !empty($_POST['kode_otomatis']) ? $conn->real_escape_string($_POST['kode_otomatis']) : '';
    $ketua_nama = $conn->real_escape_string($_POST['ketua_nama'] ?? '');
    $sk_kepengurusan = $conn->real_escape_string($_POST['sk_kepengurusan'] ?? '');
    $periode_mulai = !empty($_POST['periode_mulai']) ? $conn->real_escape_string($_POST['periode_mulai']) : NULL;
    $periode_akhir = !empty($_POST['periode_akhir']) ? $conn->real_escape_string($_POST['periode_akhir']) : NULL;
    $alamat = $conn->real_escape_string($_POST['alamat'] ?? '');
    
    // For pusat, use manual kode; for others use auto-generated kode
    $final_kode = ($jenis == 'pusat') ? $kode : $kode_otomatis;

// Auto-generate code if not provided
if (empty($final_kode) && $jenis == 'provinsi') {
    $nid = (int)($_POST['negara_id'] ?? 0);
    $r = $conn->query("SELECT kode FROM provinsi WHERE negara_id = $nid ORDER BY kode DESC LIMIT 1");
    $final_kode = ($r && $row = $r->fetch_assoc()) ? str_pad((int)$row['kode'] + 1, 3, '0', STR_PAD_LEFT) : '001';
}
if (empty($final_kode) && $jenis == 'kota') {
    $pid = (int)($_POST['provinsi_id'] ?? 0);
    $r = $conn->query("SELECT kode FROM kota WHERE provinsi_id = $pid ORDER BY kode DESC LIMIT 1");
    $final_kode = ($r && $row = $r->fetch_assoc()) ? str_pad((int)$row['kode'] + 1, 3, '0', STR_PAD_LEFT) : '001';
}
    
    if (empty($nama)) {
        $error = "Nama tidak boleh kosong!";
    } elseif (empty($final_kode)) {
        $error = "Kode tidak boleh kosong!";
    } else {
        if ($jenis == 'pusat') {
            // Insert into negara
            $conn->query("INSERT INTO negara (kode, nama, ketua_nama, sk_kepengurusan, periode_mulai, periode_akhir, alamat_sekretariat, aktif) 
                          VALUES ('$final_kode', '$nama', '$ketua_nama', '$sk_kepengurusan', " . ($periode_mulai ? "'$periode_mulai'" : "NULL") . ", " . ($periode_akhir ? "'$periode_akhir'" : "NULL") . ", '$alamat', 1)");
            $success = "Negara berhasil ditambahkan!";
        } elseif ($jenis == 'provinsi') {
            $negara_id = (int)$_POST['negara_id'];
            if (empty($negara_id)) {
                $error = "Harap pilih negara!";
            } else {
                $conn->query("INSERT INTO provinsi (kode, nama, ketua_nama, negara_id, sk_kepengurusan, periode_mulai, periode_akhir, alamat_sekretariat, aktif) 
                              VALUES ('$final_kode', '$nama', '$ketua_nama', $negara_id, '$sk_kepengurusan', " . ($periode_mulai ? "'$periode_mulai'" : "NULL") . ", " . ($periode_akhir ? "'$periode_akhir'" : "NULL") . ", '$alamat', 1)");
                $success = "Provinsi berhasil ditambahkan!";
            }
        } elseif ($jenis == 'kota') {
            $provinsi_id = (int)$_POST['provinsi_id'];
            if (empty($provinsi_id)) {
                $error = "Harap pilih provinsi!";
            } else {
                $conn->query("INSERT INTO kota (kode, nama, ketua_nama, provinsi_id, sk_kepengurusan, periode_mulai, periode_akhir, alamat_sekretariat, aktif) 
                              VALUES ('$final_kode', '$nama', '$ketua_nama', $provinsi_id, '$sk_kepengurusan', " . ($periode_mulai ? "'$periode_mulai'" : "NULL") . ", " . ($periode_akhir ? "'$periode_akhir'" : "NULL") . ", '$alamat', 1)");
                $success = "Kota berhasil ditambahkan!";
            }
        }
        
        if ($success) {
            header("refresh:2;url=pengurus_list.php?jenis=$jenis");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah <?php echo $label_jenis; ?> - Sistem Beladiri</title>
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
        
        input[readonly] { background-color: #f8f9fa; }
        textarea { resize: vertical; min-height: 80px; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-row.full { grid-template-columns: 1fr; }
        
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 6px; }
        
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
    <?php renderNavbar('➕ Tambah ' . $label_jenis); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Formulir Tambah <?php echo $label_jenis; ?> Baru</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($jenis == 'provinsi'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Negara <span class="required">*</span></label>
                        <select name="negara_id" id="negara_id" required onchange="updateKode()" <?php echo ($user_role === 'negara') ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Negara --</option>
                            <?php foreach ($negara_list as $negara): ?>
                                <option value="<?php echo $negara['id']; ?>" data-kode="<?php echo $negara['kode']; ?>" <?php echo ($auto_selected_negara == $negara['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($negara['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user_role === 'negara'): ?>
                        <input type="hidden" name="negara_id" value="<?php echo $auto_selected_negara; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Kode</label>
                        <input type="text" id="kode_otomatis" readonly placeholder="Otomatis dari negara">
                    </div>
                </div>
                <?php elseif ($jenis == 'kota'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Negara <span class="required">*</span></label>
                        <select name="negara_id" id="negara_id" required onchange="loadProvinsi()" <?php echo in_array($user_role, ['negara', 'pengprov', 'pengkot']) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Negara --</option>
                            <?php foreach ($negara_list as $negara): ?>
                                <option value="<?php echo $negara['id']; ?>" <?php echo ($auto_selected_negara == $negara['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($negara['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (in_array($user_role, ['negara', 'pengprov', 'pengkot'])): ?>
                        <input type="hidden" name="negara_id" value="<?php echo $auto_selected_negara; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Kode Negara</label>
                        <input type="text" id="kode_negara" readonly placeholder="Otomatis">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Provinsi <span class="required">*</span></label>
                        <select name="provinsi_id" id="provinsi_id" required onchange="updateKode()" <?php echo in_array($user_role, ['pengprov', 'pengkot']) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsi_list as $provinsi): ?>
                                <option value="<?php echo $provinsi['id']; ?>" data-kode="<?php echo $provinsi['kode']; ?>" data-negara="<?php echo $provinsi['negara_id']; ?>" <?php echo ($auto_selected_provinsi == $provinsi['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($provinsi['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (in_array($user_role, ['pengprov', 'pengkot'])): ?>
                        <input type="hidden" name="provinsi_id" value="<?php echo $auto_selected_provinsi; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Kode</label>
                        <input type="text" id="kode_otomatis" name="kode_otomatis" readonly placeholder="Otomatis dari provinsi">
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($jenis == 'pusat'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Negara <span class="required">*</span></label>
                        <input type="text" name="nama" required placeholder="Contoh: Indonesia" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Kode Negara <span class="required">*</span></label>
                        <input type="text" name="kode" required maxlength="2" style="text-transform: uppercase;" placeholder="2 Huruf, Contoh: ID" value="<?php echo htmlspecialchars($_POST['kode'] ?? ''); ?>">
                    </div>
                </div>
                <?php else: ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama  <?php echo $label_jenis; ?> <span class="required">*</span></label>
                        <input type="text" name="nama" required placeholder="Contoh: <?php echo ($jenis == 'provinsi') ? 'Jawa Timur' : 'Surabaya'; ?>" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Kode <?php echo ($jenis == 'kota') ? 'Kota/Kabupaten' : 'Provinsi'; ?> <span class="required">*</span></label>
                        <input type="text" id="kode_otomatis" name="kode_otomatis" readonly placeholder="Otomatis" value="<?php echo htmlspecialchars($_POST['kode_otomatis'] ?? ''); ?>">
                    </div>
                </div>       
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Ketua <span class="required">*</span></label>
                        <input type="text" name="ketua_nama" required placeholder="Contoh: Ahmad Fauzi" value="<?php echo htmlspecialchars($_POST['ketua_nama'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>No. SK Kepengurusan</label>
                        <input type="text" name="sk_kepengurusan" placeholder="Contoh: 001/SK/Pusat/2024" value="<?php echo htmlspecialchars($_POST['sk_kepengurusan'] ?? ''); ?>">
                    </div>
                    
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Periode Mulai <span class="required">*</span></label>
                        <input type="date" name="periode_mulai" required value="<?php echo htmlspecialchars($_POST['periode_mulai'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Periode Akhir <span class="required">*</span></label>
                        <input type="date" name="periode_akhir" required value="<?php echo htmlspecialchars($_POST['periode_akhir'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row full">                    
                    <div class="form-group">
                        <label>Alamat Sekretariat <span class="required">*</span></label>
                        <textarea name="alamat" required placeholder="Contoh: Jl. Contoh No. 123" value="<?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?>"></textarea>
                    </div>
                </div>                               
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan</button>
                    <a href="pengurus_list.php?jenis=<?php echo $jenis; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($jenis != 'pusat'): ?>
    <script>
    const negaraKodeMap = {};
    <?php foreach ($negara_list as $negara): ?>
    negaraKodeMap[<?php echo $negara['id']; ?>] = '<?php echo $negara['kode']; ?>';
    <?php endforeach; ?>
    
    // Auto-trigger functions on page load if there are auto-selected values
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($jenis == 'provinsi' && $auto_selected_negara > 0): ?>
        // Trigger code update for pre-selected negara
        updateKode();
        <?php elseif ($jenis == 'kota'): ?>
        // Trigger province loading for pre-selected negara
        loadProvinsi();
        // If there's a pre-selected province, trigger code update
        setTimeout(function() {
            const provinsiSelect = document.getElementById('provinsi_id');
            if (provinsiSelect && provinsiSelect.value) {
                updateKode();
            }
        }, 100);
        <?php endif; ?>
    });
    
    // Function to get next code via AJAX
    async function getNextCode(table, parentId, parentField) {
        try {
            const response = await fetch(`api/get_next_kode.php?table=${table}&parent_id=${parentId}&parent_field=${parentField}`);
            const data = await response.json();
            return data.kode || '001';
        } catch (e) {
            console.error('Error getting next code:', e);
            return '001';
        }
    }
    
    function loadProvinsi() {
        const negaraIdEl = document.getElementById('negara_id');
        const negaraId = negaraIdEl.value;
        const kodeNegara = negaraId ? negaraKodeMap[negaraId] : '';
        document.getElementById('kode_negara').value = kodeNegara;
        
        const provinsiSelect = document.getElementById('provinsi_id');
        const options = provinsiSelect.querySelectorAll('option');
        options.forEach(opt => {
            if (opt.value === '') return;
            const negaraIdAttr = opt.getAttribute('data-negara');
            opt.style.display = (negaraId === '' || negaraIdAttr === negaraId) ? '' : 'none';
        });
        
        // Only clear if not disabled (pengprov/pengkot roles)
        if (!provinsiSelect.disabled) {
            provinsiSelect.value = '';
        }
        document.getElementById('kode_otomatis').value = '';
    }
    
    async function updateKode() {
        <?php if ($jenis == 'provinsi'): ?>
        const negaraIdEl = document.getElementById('negara_id');
        const negaraId = negaraIdEl.value;
        if (negaraId) {
            const nextCode = await getNextCode('provinsi', negaraId, 'negara_id');
            document.getElementById('kode_otomatis').value = nextCode;
        } else {
            document.getElementById('kode_otomatis').value = '';
        }
        <?php elseif ($jenis == 'kota'): ?>
        const opt2 = document.getElementById('provinsi_id').selectedOptions[0];
        const kode2 = opt2 ? opt2.getAttribute('data-kode') : '';
        const kodeNegara = document.getElementById('kode_negara').value;
        const provinsiId = document.getElementById('provinsi_id').value;
        
        if (provinsiId) {
            const nextCode = await getNextCode('kota', provinsiId, 'provinsi_id');
            document.getElementById('kode_otomatis').value = nextCode;
        } else {
            document.getElementById('kode_otomatis').value = '';
        }
        <?php endif; ?>
    }
    </script>
    <?php endif; ?>
</body>
</html>
