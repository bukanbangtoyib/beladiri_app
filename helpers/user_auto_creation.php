<?php
/**
 * Helper function untuk otomatisasi pembuatan user
 */

function createOrUpdateUser($conn, $data) {
    $username = $data['username'];
    $password = $data['password'];
    $nama_lengkap = $data['nama_lengkap'];
    $role = $data['role'];
    $pengurus_id = $data['pengurus_id'] ?? null;
    $ranting_id = $data['ranting_id'] ?? null;
    $no_anggota = $data['no_anggota'] ?? null;
    $anggota_id = $data['anggota_id'] ?? null;

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Cek apakah user sudah ada
    // Prioritas pencarian:
    // 1. anggota_id (jika role anggota)
    // 2. pengurus_id + role (untuk negara, pengprov, pengkot)
    // 3. username
    
    $user_id = null;
    if ($anggota_id && $role === 'anggota') {
        $check = $conn->prepare("SELECT id FROM users WHERE anggota_id = ?");
        $check->bind_param("i", $anggota_id);
    } elseif ($pengurus_id && in_array($role, ['negara', 'pengprov', 'pengkot'])) {
        $check = $conn->prepare("SELECT id FROM users WHERE pengurus_id = ? AND role = ?");
        $check->bind_param("is", $pengurus_id, $role);
    } elseif ($ranting_id && in_array($role, ['ranting', 'unit'])) {
        $check = $conn->prepare("SELECT id FROM users WHERE ranting_id = ? AND role = ?");
        $check->bind_param("is", $ranting_id, $role);
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
    }
    
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        
        // Update user
        $sql = "UPDATE users SET username = ?, password = ?, nama_lengkap = ?, role = ?, pengurus_id = ?, ranting_id = ?, no_anggota = ?, anggota_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiissi", $username, $hashed_password, $nama_lengkap, $role, $pengurus_id, $ranting_id, $no_anggota, $anggota_id, $user_id);
        return $stmt->execute();
    } else {
        // Insert new user
        $sql = "INSERT INTO users (username, password, nama_lengkap, role, pengurus_id, ranting_id, no_anggota, anggota_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiisi", $username, $hashed_password, $nama_lengkap, $role, $pengurus_id, $ranting_id, $no_anggota, $anggota_id);
        return $stmt->execute();
    }
}

/**
 * Format string menjadi lowercase dan tanpa spasi untuk username/password
 */
function formatPwd($str) {
    if (empty($str)) return '';
    return strtolower(str_replace(' ', '', $str));
}
