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

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Cek apakah user sudah ada berdasarkan username
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        
        // Update user - password di-reset sesuai format default username + 1955
        // Ini mengikuti username karena username tidak bisa diubah setelah dibuat
        $sql = "UPDATE users SET nama_lengkap = ?, role = ?, pengurus_id = ?, ranting_id = ?, no_anggota = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiissi", $nama_lengkap, $role, $pengurus_id, $ranting_id, $no_anggota, $hashed_password, $user_id);
        return $stmt->execute();
    } else {
        // Insert new user
        $sql = "INSERT INTO users (username, password, nama_lengkap, role, pengurus_id, ranting_id, no_anggota) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiis", $username, $hashed_password, $nama_lengkap, $role, $pengurus_id, $ranting_id, $no_anggota);
        return $stmt->execute();
    }
}

/**
 * Format string menjadi lowercase dan tanpa spasi untuk password
 */
function formatPwd($str) {
    return strtolower(str_replace(' ', '', $str));
}
