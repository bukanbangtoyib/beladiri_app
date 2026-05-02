<?php
/**
 * Date Helper for Import standardizing
 * Developed by Antigravity
 */

/**
 * Standardize various date formats into YYYY-MM-DD for database storage.
 * Handles:
 * 1. dd-mmyyyy (e.g., 10-122025)
 * 2. dd Month YYYY (e.g., 10 Jan 2025, 10 Nopember 2025)
 * 3. ddMonthYYYY (e.g., 10Nopember2025)
 * 4. Excel serial date (e.g., 42540)
 * 5. Standard formats (dd/mm/yyyy, yyyy-mm-dd)
 */
function parse_import_date($date_str) {
    if (empty($date_str)) return null;
    $date_str = trim($date_str);

    // 1. Handle Excel serial date (numeric)
    // Formula: 01/01/1900 + (val-1) days
    if (is_numeric($date_str) && intval($date_str) > 1000) {
        $days = intval($date_str) - 1;
        try {
            $base = new DateTime('1900-01-01');
            $base->modify('+' . $days . ' days');
            return $base->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    // 2. Handle 10-122025 -> 2025-12-10 (dd-mmyyyy)
    // We expect at least 7-8 digits if separators are missing, or with a separator after day
    if (preg_match('/^(\d{1,2})[- \/.]?(\d{2})(\d{4})$/', $date_str, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }

    // 3. Handle with month names
    $months = [
        'jan' => '01', 'januari' => '01', 'january' => '01',
        'feb' => '02', 'februari' => '02', 'pebruari' => '02', 'february' => '02',
        'mar' => '03', 'maret' => '03', 'march' => '03',
        'apr' => '04', 'april' => '04',
        'may' => '05', 'mei' => '05',
        'jun' => '06', 'juni' => '06', 'june' => '06',
        'jul' => '07', 'juli' => '07', 'july' => '07',
        'aug' => '08', 'agustus' => '08', 'august' => '08',
        'sep' => '09', 'september' => '09',
        'oct' => '10', 'oktober' => '10', 'october' => '10',
        'nov' => '11', 'november' => '11', 'nopember' => '11',
        'dec' => '12', 'desember' => '12', 'december' => '12',
    ];

    // Regex to extract day, month name, and year
    // Matches "10 Jan 2025", "10Jan2025", "10 Nopember 2025", "10Nopember2025"
    if (preg_match('/^(\d{1,2})[- \/.]?([a-zA-Z]+)[- \/.]?(\d{4})$/', $date_str, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month_name = strtolower($matches[2]);
        $year = $matches[3];
        if (isset($months[$month_name])) {
            return $year . '-' . $months[$month_name] . '-' . $day;
        }
    }

    // 4. Standard formats
    // dd/mm/yyyy or dd-mm-yyyy or dd.mm.yyyy
    if (preg_match('/^(\d{1,2})[- \/.](\d{1,2})[- \/.](\d{4})$/', $date_str, $matches)) {
        return $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }
    
    // yyyy-mm-dd
    if (preg_match('/^(\d{4})[- \/.](\d{1,2})[- \/.](\d{1,2})$/', $date_str, $matches)) {
        return $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
    }

    return null;
}
