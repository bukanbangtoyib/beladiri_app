<?php
/**
 * Helper Functions untuk UKT Terakhir
 * File ini berisi fungsi-fungsi utility untuk handle kolom ukt_terakhir
 */

/**
 * Parse UKT Terakhir dari input user
 * Input bisa berupa:
 * - Tanggal lengkap: "15/07/2024" atau "2024-07-15"
 * - Tahun saja: "2024" → dikonversi ke "2024-07-02"
 * 
 * @param string $input Input dari user
 * @return string|null Format DATE SQL (YYYY-MM-DD) atau null jika invalid
 */
function parseUKTTerakhir($input) {
    if (empty($input)) {
        return null;
    }
    
    $input = trim($input);
    
    // Jika hanya tahun (4 digit)
    if (preg_match('/^(\d{4})$/', $input, $matches)) {
        $tahun = $matches[1];
        // Konversi ke 02/07/tahun (2 Juli)
        return $tahun . '-07-02';
    }
    
    // Jika format dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $input, $matches)) {
        $hari = $matches[1];
        $bulan = $matches[2];
        $tahun = $matches[3];
        
        // Validasi tanggal
        if (checkdate((int)$bulan, (int)$hari, (int)$tahun)) {
            return $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-' . str_pad($hari, 2, '0', STR_PAD_LEFT);
        }
    }
    
    // Jika format yyyy-mm-dd (ISO format)
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $input, $matches)) {
        $tahun = $matches[1];
        $bulan = $matches[2];
        $hari = $matches[3];
        
        if (checkdate((int)$bulan, (int)$hari, (int)$tahun)) {
            return $input;
        }
    }
    
    // Invalid format
    return null;
}

/**
 * Format UKT Terakhir untuk ditampilkan
 * 
 * @param string|null $date Tanggal dari database (format YYYY-MM-DD)
 * @return string Tanggal terformat DD/MM/YYYY atau "-"
 */
function formatUKTTerakhir($date) {
    if (empty($date)) {
        return '-';
    }
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Hitung eligibility UKT berikutnya
 * Menghitung apakah anggota eligible untuk UKT berikutnya
 * Default rule: 1 tahun sejak UKT terakhir
 * 
 * @param string|null $ukt_terakhir Tanggal UKT terakhir (YYYY-MM-DD)
 * @param int $months_interval Interval dalam bulan (default: 12)
 * @return array ['eligible' => bool, 'message' => string, 'next_eligible_date' => string]
 */
function checkUKTEligibility($ukt_terakhir, $months_interval = 12) {
    if (empty($ukt_terakhir)) {
        return [
            'eligible' => true,
            'message' => 'UKT terakhir tidak tercatat - Eligible untuk UKT',
            'next_eligible_date' => null
        ];
    }
    
    try {
        $last_ukt = new DateTime($ukt_terakhir);
        $today = new DateTime();
        
        // Hitung next eligible date (UKT terakhir + interval)
        $next_eligible = clone $last_ukt;
        $next_eligible->add(new DateInterval('P' . $months_interval . 'M'));
        
        if ($today >= $next_eligible) {
            return [
                'eligible' => true,
                'message' => 'Eligible untuk UKT',
                'next_eligible_date' => $next_eligible->format('d/m/Y')
            ];
        } else {
            return [
                'eligible' => false,
                'message' => 'Belum eligible UKT (eligible tgl: ' . $next_eligible->format('d/m/Y') . ')',
                'next_eligible_date' => $next_eligible->format('d/m/Y')
            ];
        }
    } catch (Exception $e) {
        return [
            'eligible' => true,
            'message' => 'Format tanggal invalid',
            'next_eligible_date' => null
        ];
    }
}

/**
 * Get days until next UKT eligibility
 * 
 * @param string|null $ukt_terakhir Tanggal UKT terakhir (YYYY-MM-DD)
 * @param int $months_interval Interval dalam bulan (default: 12)
 * @return int Jumlah hari sampai eligible, atau 0 jika sudah eligible
 */
function getDaysUntilEligible($ukt_terakhir, $months_interval = 12) {
    if (empty($ukt_terakhir)) {
        return 0; // Sudah eligible
    }
    
    try {
        $last_ukt = new DateTime($ukt_terakhir);
        $today = new DateTime();
        
        $next_eligible = clone $last_ukt;
        $next_eligible->add(new DateInterval('P' . $months_interval . 'M'));
        
        if ($today >= $next_eligible) {
            return 0; // Sudah eligible
        }
        
        $interval = $today->diff($next_eligible);
        return $interval->days;
    } catch (Exception $e) {
        return 0;
    }
}

?>