<?php
class PermissionManager {
    private $conn;
    private $user_id;
    private $role;
    private $pengurus_id;
    private $ranting_id;
    private $roles_config;
    
    public function __construct($conn, $user_id, $role, $pengurus_id = null, $ranting_id = null) {
        $this->conn = $conn;
        $this->user_id = $user_id;
        $this->role = $role;
        $this->pengurus_id = $pengurus_id;
        $this->ranting_id = $ranting_id;
        
        include __DIR__ . '/../config/roles.php';
        $this->roles_config = $ROLES_CONFIG;
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
        // Admin selalu bisa
        if ($this->role === 'admin') {
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
        return $this->checkScope($permission, $target_pengurus_id, $target_ranting_id);
    }
    
    /**
     * Check scope-based permission
     */
    private function checkScope($scope, $target_pengurus_id = null, $target_ranting_id = null) {
        switch ($scope) {
            case 'own':
                // Hanya dirinya sendiri
                return $this->pengurus_id === $target_pengurus_id && 
                       $this->ranting_id === $target_ranting_id;
            
            case 'own_hierarchy':
                // Struktur bawahnya
                return $this->isInHierarchy($target_pengurus_id, $target_ranting_id);
            
            case 'own_hierarchy_plus':
                // Struktur bawah + level yang sama
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
        // Jika target ranting, check apakah pengurus_kota dari ranting itu 
        // sama atau di bawah hierarchy user
        if ($target_ranting_id) {
            $ranting = $this->conn->query(
                "SELECT kota_id FROM ranting WHERE id = " . (int)$target_ranting_id
            )->fetch_assoc();
            
            if (!$ranting) return false;
            
            $target_pengurus_id = $ranting['kota_id'];
        }
        
        // Check hierarchy berdasarkan role
        if ($this->role === 'pengprov') {
            // PengProv bisa manage semua kota yang id_provinsi-nya sama dengan province ini
            $sql = "SELECT id FROM kota WHERE provinsi_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $this->pengurus_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if ($row['id'] == $target_pengurus_id) {
                    return true;
                }
            }
            return false;
        }
        
        if ($this->role === 'pengkot') {
            // PengKot hanya manage unit/ranting di bawahnya
            if ($target_ranting_id) {
                $ranting = $this->conn->query(
                    "SELECT kota_id FROM ranting WHERE id = " . (int)$target_ranting_id
                )->fetch_assoc();
                
                return $ranting && $ranting['kota_id'] == $this->pengurus_id;
            }
            return false;
        }
        
        if ($this->role === 'unit') {
            // Unit hanya manage data di ranting mereka sendiri
            return $this->ranting_id === $target_ranting_id;
        }
        
        return false;
    }
    
    /**
     * Check hierarchy plus (own level + bawahan)
     */
    private function isInHierarchyPlus($target_pengurus_id, $target_ranting_id) {
        // Cek dulu own level
        if ($this->pengurus_id === $target_pengurus_id) {
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
}
?>
