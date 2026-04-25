<?php
class PermissionManager {
    private $conn;
    private $user_id;
    private $role;
    private $pengurus_id;
    private $ranting_id;
    private $no_anggota;
    private $roles_config;
    
    public function __construct($conn, $user_id, $role, $pengurus_id = null, $ranting_id = null, $no_anggota = null) {
        $this->conn = $conn;
        $this->user_id = $user_id;
        $this->role = $role;
        $this->pengurus_id = $pengurus_id;
        $this->ranting_id = $ranting_id;
        $this->no_anggota = $no_anggota;
        
        include __DIR__ . '/../config/roles.php';
        $this->roles_config = $ROLES_CONFIG;
    }

    /**
     * Returns true for both admin and superadmin roles
     */
    private function isAdmin(): bool {
        return $this->role === 'admin' || $this->role === 'superadmin';
    }
    
    /**
     * Check apakah user memiliki permission
     * 
     * @param string $action Permission yang dicek (misal: 'anggota_create')
     * @param int $target_pengurus_id ID pengurus target (opsional)
     * @param int $target_ranting_id ID ranting target (opsional)
     * @return bool
     */
    public function can($action, $target_pengurus_id = null, $target_ranting_id = null) {
        // Admin & superadmin selalu bisa
        if ($this->isAdmin()) {
            return true;
        }
        
        // Check apakah permission ada di role
        if (!isset($this->roles_config[$this->role])) {
            return false;
        }
        
        $permissions = $this->roles_config[$this->role]['permissions'];
        
        if (!isset($permissions[$action])) {
            return false;
        }
        
        $permission = $permissions[$action];
        
        // Jika permission adalah boolean
        if (is_bool($permission)) {
            return $permission;
        }
        
        // Jika permission adalah string, handle scope
        return $this->checkScope($permission, $target_pengurus_id, $target_ranting_id, (func_num_args() > 3 ? func_get_arg(3) : null));
    }
    
    /**
     * Check scope-based permission
     */
    private function checkScope($scope, $target_pengurus_id = null, $target_ranting_id = null, $target_no_anggota = null) {
        // If no target specified, allow access for read operations
        // (the actual filtering will be done at the data query level)
        $no_target = empty($target_pengurus_id) && empty($target_ranting_id) && empty($target_no_anggota);
        
        switch ($scope) {
            case 'own':
                // Hanya dirinya sendiri
                if ($no_target) return true; // Allow, will filter at query level
                
                if ($this->role === 'anggota') {
                    return !empty($this->no_anggota) && $this->no_anggota === $target_no_anggota;
                }
                
                return (int)$this->pengurus_id === (int)$target_pengurus_id &&
                       (int)$this->ranting_id === (int)$target_ranting_id;
            
            case 'own_hierarchy':
                // Struktur bawahnya
                if ($no_target) return true; // Allow, will filter at query level
                return $this->isInHierarchy($target_pengurus_id, $target_ranting_id);
            
            case 'own_hierarchy_plus':
                // Struktur bawah + level yang sama
                if ($no_target) return true; // Allow, will filter at query level
                return $this->isInHierarchyPlus($target_pengurus_id, $target_ranting_id);
            
            case 'all':
                // Semua boleh (untuk read)
                return true;
            
            default:
                return false;
        }
    }
    
    /**
     * Check apakah target ada di bawah user hierarchy
     */
    private function isInHierarchy($target_pengurus_id, $target_ranting_id) {
        // Jika target ranting, cari tahu kota_id nya
        if ($target_ranting_id && !$target_pengurus_id) {
            $ranting = $this->conn->query(
                "SELECT kota_id FROM ranting WHERE id = " . (int)$target_ranting_id
            )->fetch_assoc();
            
            if (!$ranting) return false;
            $target_pengurus_id = $ranting['kota_id'];
        }
        
        // Check hierarchy berdasarkan role
        if ($this->role === 'negara') {
            // Negara bisa manage segalanya di bawahnya (semua provinsi -> kota -> ranting)
            // Jadi jika target_pengurus_id valid, kita check apakah dia bagian dari negara ini
            // Asumsi: pengurus_id user negara adalah ID negaranya
            if ($target_ranting_id) {
                // Ranting must eventually link to this negara
                $sql = "SELECT n.id as negara_id 
                        FROM ranting r
                        JOIN kota k ON r.kota_id = k.id
                        JOIN provinsi p ON k.provinsi_id = p.id
                        JOIN negara n ON p.negara_id = n.id
                        WHERE r.id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $target_ranting_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                return $res && (int)$res['negara_id'] === (int)$this->pengurus_id;
            }
            if ($target_pengurus_id) {
                // target_pengurus_id bisa ID provinsi atau ID kota
                // Check if it belongs to this negara
                // Cek as Provinsi
                $check_p = $this->conn->query("SELECT id FROM provinsi WHERE id = " . (int)$target_pengurus_id . " AND negara_id = " . (int)$this->pengurus_id);
                if ($check_p && $check_p->num_rows > 0) return true;
                
                // Cek as Kota
                $check_k = $this->conn->query("SELECT k.id FROM kota k JOIN provinsi p ON k.provinsi_id = p.id WHERE k.id = " . (int)$target_pengurus_id . " AND p.negara_id = " . (int)$this->pengurus_id);
                if ($check_k && $check_k->num_rows > 0) return true;
            }
            return true; // Default for negara if no specific target
        }

        if ($this->role === 'pengprov') {
            // PengProv bisa manage semua kota yang id_provinsi-nya sama
            if ($target_ranting_id) {
                $sql = "SELECT p.id as provinsi_id 
                        FROM ranting r
                        JOIN kota k ON r.kota_id = k.id
                        JOIN provinsi p ON k.provinsi_id = p.id
                        WHERE r.id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $target_ranting_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                return $res && (int)$res['provinsi_id'] === (int)$this->pengurus_id;
            }
            if ($target_pengurus_id) {
                // target_pengurus_id must be a kota in this province
                $check = $this->conn->query("SELECT id FROM kota WHERE id = " . (int)$target_pengurus_id . " AND provinsi_id = " . (int)$this->pengurus_id);
                return $check && $check->num_rows > 0;
            }
            return false;
        }
        
        if ($this->role === 'pengkot') {
            // PengKot hanya manage unit/ranting di bawahnya
            if ($target_ranting_id) {
                $ranting = $this->conn->query(
                    "SELECT kota_id FROM ranting WHERE id = " . (int)$target_ranting_id
                )->fetch_assoc();
                
                return $ranting && (int)$ranting['kota_id'] === (int)$this->pengurus_id;
            }
            return false;
        }
        
        if ($this->role === 'unit') {
            // Unit hanya manage data di ranting mereka sendiri
            return (int)$this->ranting_id === (int)$target_ranting_id;
        }
        
        return false;
    }
    
    /**
     * Check hierarchy plus (own level + bawahan)
     */
    private function isInHierarchyPlus($target_pengurus_id, $target_ranting_id) {
        // Cek dulu own level
        if ((int)$this->pengurus_id === (int)$target_pengurus_id) {
            return true;
        }
        
        // Kemudian cek hierarchy
        return $this->isInHierarchy($target_pengurus_id, $target_ranting_id);
    }
    
    /**
     * Get role name
     */
    public function getRoleName() {
        return isset($this->roles_config[$this->role]) 
            ? $this->roles_config[$this->role]['label']
            : 'Unknown';
    }
    
    /**
     * Get all permissions untuk role ini
     */
    public function getPermissions() {
        return isset($this->roles_config[$this->role])
            ? $this->roles_config[$this->role]['permissions']
            : [];
    }
    
    /**
     * Khusus untuk check permission UKT berdasarkan level penyelenggara
     */
    public function canManageUKT($action, $jenis_peny, $peny_id) {
        if ($this->isAdmin()) return true;
        
        // For UKT create/update, special handling - $peny_id IS the organization ID
        $permissions = $this->getPermissions();
        
        if ($jenis_peny === 'pusat') {
            // negara can manage pusat
            return isset($permissions['ukt_manage_pusat']) && $permissions['ukt_manage_pusat'] === true;
        } elseif ($jenis_peny === 'provinsi') {
            // pengprov can only manage their own province
            if ($this->role === 'pengprov' && (int)$peny_id === (int)$this->pengurus_id) {
                return true;
            }
            // negara can manage all provinces within their country - check hierarchy
            if ($this->role === 'negara' && isset($permissions['ukt_manage_provinsi']) && $permissions['ukt_manage_provinsi'] === true) {
                // Check if the province belongs to this negara
                $check = $this->conn->query("SELECT id FROM provinsi WHERE id = " . (int)$peny_id . " AND negara_id = " . (int)$this->pengurus_id);
                return $check && $check->num_rows > 0;
            }
            return false;
        } elseif ($jenis_peny === 'kota') {
            // pengkot can only manage their own city
            if ($this->role === 'pengkot' && (int)$peny_id === (int)$this->pengurus_id) {
                return true;
            }
            // pengprov CANNOT manage cities - they can only see them (read access)
            // Only negara can manage all cities within their country
            if ($this->role === 'negara' && isset($permissions['ukt_manage_kota']) && $permissions['ukt_manage_kota'] === true) {
                // Check if the kota belongs to this negara
                $check = $this->conn->query("SELECT k.id FROM kota k JOIN provinsi p ON k.provinsi_id = p.id WHERE k.id = " . (int)$peny_id . " AND p.negara_id = " . (int)$this->pengurus_id);
                return $check && $check->num_rows > 0;
            }
            return false;
        }
        
        return false;
    }
    
    /**
     * Get SQL filter for UKT listing based on user role and hierarchy
     * Returns array with 'where_clause' and 'params' for prepared statement
     */
    public function getUKTFilterSQL() {
        // Admin & superadmin can see all UKT
        if ($this->isAdmin()) {
            return ['where' => '1=1', 'params' => []];
        }
        
        // Unit and Tamu should not see UKT at all
        if ($this->role === 'unit' || $this->role === 'tamu') {
            return ['where' => '1=0', 'params' => []];
        }
        
        $pengurus_id = $this->pengurus_id;
        
        // Negara: can see UKT at pusat level + all provinces + all cities in their country
        if ($this->role === 'negara') {
            $where = "(
                (u.jenis_penyelenggara = 'pusat' AND u.penyelenggara_id = ?)
                OR
                (u.jenis_penyelenggara = 'provinsi' AND u.penyelenggara_id IN 
                    (SELECT id FROM provinsi WHERE negara_id = ?))
                OR
                (u.jenis_penyelenggara = 'kota' AND u.penyelenggara_id IN 
                    (SELECT k.id FROM kota k JOIN provinsi p ON k.provinsi_id = p.id WHERE p.negara_id = ?))
            )";
            return [
                'where' => $where, 
                'params' => [$pengurus_id, $pengurus_id, $pengurus_id]
            ];
        }
        
        // PengProv: can see UKT at their province level + all cities in their province
        if ($this->role === 'pengprov') {
            $where = "(
                (u.jenis_penyelenggara = 'provinsi' AND u.penyelenggara_id = ?)
                OR
                (u.jenis_penyelenggara = 'kota' AND u.penyelenggara_id IN 
                    (SELECT id FROM kota WHERE provinsi_id = ?))
            )";
            return [
                'where' => $where, 
                'params' => [$pengurus_id, $pengurus_id]
            ];
        }
        
        // PengKot: can see only UKT at their city level
        if ($this->role === 'pengkot') {
            $where = "(u.jenis_penyelenggara = 'kota' AND u.penyelenggara_id = ?)";
            return [
                'where' => $where, 
                'params' => [$pengurus_id]
            ];
        }
        
        // Default: no access
        return ['where' => '1=0', 'params' => []];
    }
    
    /**
     * Check if user can create UKT at their own level
     * Returns the level they can create and the penyelenggaraan_id
     */
    public function canCreateOwnUKT() {
        if ($this->isAdmin()) {
            return ['can' => true, 'jenis' => 'all', 'peny_id' => null];
        }
        
        if ($this->role === 'unit' || $this->role === 'tamu') {
            return ['can' => false, 'jenis' => null, 'peny_id' => null];
        }
        
        $pengurus_id = $this->pengurus_id;
        
        // The pengurus_id in session IS the organization ID (negara_id, provinsi_id, or kota_id)
        // based on the user's role
        if ($this->role === 'negara') {
            // Pusat - pengurus_id is the negara_id
            return ['can' => true, 'jenis' => 'pusat', 'peny_id' => $pengurus_id];
        }
        
        if ($this->role === 'pengprov') {
            // Provinsi - pengurus_id is the provinsi_id
            return ['can' => true, 'jenis' => 'provinsi', 'peny_id' => $pengurus_id];
        }
        
        if ($this->role === 'pengkot') {
            // Kota - pengurus_id is the kota_id
            return ['can' => true, 'jenis' => 'kota', 'peny_id' => $pengurus_id];
        }
        
        return ['can' => false, 'jenis' => null, 'peny_id' => null];
    }
    
    /**
     * Check if user can update UKT at their own level (for UI buttons)
     */
    public function canUpdateOwnUKT() {
        if ($this->isAdmin()) {
            return true;
        }
        
        if ($this->role === 'unit' || $this->role === 'tamu') {
            return false;
        }
        
        // negara, pengprov, pengkot can all update their own level
        return in_array($this->role, ['negara', 'pengprov', 'pengkot']);
    }
    
    /**
     * Check if user can delete UKT (must have no participants)
     */
    public function canDeleteUKT($ukt_id) {
        // Admin & superadmin always can delete
        if ($this->isAdmin()) {
            return true;
        }
        
        // Get UKT data
        $ukt_result = $this->conn->query("SELECT * FROM ukt WHERE id = " . (int)$ukt_id);
        if ($ukt_result->num_rows === 0) {
            return false;
        }
        
        $ukt = $ukt_result->fetch_assoc();
        
        // Check if there are participants
        $peserta_result = $this->conn->query("SELECT COUNT(*) as total FROM ukt_peserta WHERE ukt_id = " . (int)$ukt_id);
        $peserta = $peserta_result->fetch_assoc();
        
        if ($peserta && $peserta['total'] > 0) {
            return false; // Cannot delete if there are participants
        }
        
        // Check hierarchical permission to manage this UKT
        return $this->canManageUKT('ukt_delete', $ukt['jenis_penyelenggara'], $ukt['penyelenggara_id']);
    }
}
?>
