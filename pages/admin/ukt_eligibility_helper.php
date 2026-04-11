<?php
/**
 * Helper Functions untuk Kelayakan UKT
 * Menentukan apakah anggota layak untuk mengikuti UKT berdasarkan:
 * - Tingkat 1-6: 6 bulan sekali
 * - Tingkat 7: 1 tahun sekali
 * - Tingkat 8-9: 2 tahun sekali
 * - Tingkat 10-12: 3 tahun sekali
 */

/**
 * Dapatkan interval bulan berdasarkan tingkat
 * 
 * @param int $tingkat_urutan Urutan tingkat (1-13)
 * @return int Interval dalam bulan
 */
function getIntervalBulanByTingkat($conn, $tingkat_urutan) {
    // Ambil dari database jika tersedia
    $result = $conn->query("SELECT waktu_ukt FROM tingkatan WHERE urutan = $tingkat_urutan");
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['waktu_ukt'] !== null && $row['waktu_ukt'] > 0) {
            return (int)$row['waktu_ukt'];
        }
    }

    // Harus ada di database, jika tidak ada atau 0, anggap tidak layak/interval sangat besar
    return 999;
}

/**
 * Hitung kelayakan UKT untuk seorang anggota
 * 
 * @param mysqli $conn Database connection
 * @param int $anggota_id ID anggota
 * @return array [
 *     'layak' => bool,
 *     'ukt_terakhir' => datetime atau null,
 *     'next_eligible_date' => datetime,
 *     'hari_tersisa' => int,
 *     'tingkat_urutan' => int,
 *     'interval_bulan' => int
 * ]
 */
function checkUKTEligibility($conn, $anggota_id) {
    // Ambil tingkat terakhir anggota
    $anggota = $conn->query(
        "SELECT a.tingkat_id, a.ukt_terakhir, t.urutan 
         FROM anggota a
         LEFT JOIN tingkatan t ON a.tingkat_id = t.urutan
         WHERE a.id = $anggota_id"
    )->fetch_assoc();
    
    if (!$anggota) {
        return [
            'layak' => false,
            'ukt_terakhir' => null,
            'next_eligible_date' => null,
            'hari_tersisa' => -1,
            'tingkat_urutan' => 0,
            'interval_bulan' => 0,
            'alasan' => 'Anggota tidak ditemukan'
        ];
    }
    
    $tingkat_urutan = (int)$anggota['urutan'];
    
    // Pendekar tidak ada UKT
    if ($tingkat_urutan == 13) {
        return [
            'layak' => false,
            'ukt_terakhir' => null,
            'next_eligible_date' => null,
            'hari_tersisa' => -1,
            'tingkat_urutan' => 13,
            'interval_bulan' => 999999,
            'alasan' => 'Sudah mencapai tingkat tertinggi (Pendekar)'
        ];
    }
    
    // Ambil UKT terakhir yang LULUS dari tabel ukt_peserta
    $ukt_terakhir_query = $conn->query(
        "SELECT u.tanggal_pelaksanaan 
         FROM ukt_peserta up
         JOIN ukt u ON up.ukt_id = u.id
         WHERE up.anggota_id = $anggota_id AND up.status = 'lulus'
         ORDER BY u.tanggal_pelaksanaan DESC
         LIMIT 1"
    );
    
    $ukt_terakhir_date = null;
    if ($ukt_terakhir_query->num_rows > 0) {
        $data = $ukt_terakhir_query->fetch_assoc();
        $ukt_terakhir_date = $data['tanggal_pelaksanaan'];
    } else if (!empty($anggota['ukt_terakhir']) && $anggota['ukt_terakhir'] != '0000-00-00') {
        // Fallback ke input manual di profil anggota jika data UKT resmi tidak ada
        $ukt_terakhir_date = $anggota['ukt_terakhir'];
    }
    
    // Jika belum pernah UKT, langsung layak
    if ($ukt_terakhir_date === null) {
        return [
            'layak' => true,
            'ukt_terakhir' => null,
            'next_eligible_date' => date('Y-m-d'),
            'hari_tersisa' => 0,
            'tingkat_urutan' => $tingkat_urutan,
            'interval_bulan' => getIntervalBulanByTingkat($conn, $tingkat_urutan),
            'alasan' => 'Belum pernah mengikuti UKT'
        ];
    }
    
    // Hitung interval berdasarkan tingkat
    $interval_bulan = getIntervalBulanByTingkat($conn, $tingkat_urutan);
    
    // Hitung tanggal eligible berikutnya
    try {
        $last_date = new DateTime($ukt_terakhir_date);
        $next_eligible = clone $last_date;
        $next_eligible->add(new DateInterval('P' . $interval_bulan . 'M'));
        
        $today = new DateTime('now');
        $is_eligible = ($today >= $next_eligible);
        
        if ($is_eligible) {
            $days_left = 0;
        } else {
            $interval = $today->diff($next_eligible);
            $days_left = $interval->days;
        }
        
        return [
            'layak' => $is_eligible,
            'ukt_terakhir' => $ukt_terakhir_date,
            'next_eligible_date' => $next_eligible->format('Y-m-d'),
            'hari_tersisa' => $days_left,
            'tingkat_urutan' => $tingkat_urutan,
            'interval_bulan' => $interval_bulan
        ];
    } catch (Exception $e) {
        return [
            'layak' => false,
            'ukt_terakhir' => $ukt_terakhir_date,
            'next_eligible_date' => null,
            'hari_tersisa' => -1,
            'tingkat_urutan' => $tingkat_urutan,
            'interval_bulan' => $interval_bulan,
            'alasan' => 'Error parsing date'
        ];
    }
}

/**
 * Format tanggal untuk tampilan
 * 
 * @param string|null $date Tanggal (YYYY-MM-DD)
 * @return string Tanggal terformat atau "-"
 */
function formatDate($date) {
    if (empty($date)) {
        return '-';
    }
    try {
        return date('d M Y', strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

?>