<?php
/**
 * Hotspot Voucher/PIN System
 */

class VoucherSystem {
    private $conn;
    
    public function __construct() {
        chdir(__DIR__ . '/../..');
        include 'config.php';
        $this->conn = $conn;
    }
    
    /**
     * Generate PIN codes
     */
    public function generatePins($profileId, $count = 10) {
        $pins = [];
        
        for ($i = 0; $i < $count; $i++) {
            // Generate 4-digit PIN
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            
            // Get profile validity
            $result = $this->conn->query("SELECT validity_hours FROM hotspot_profiles WHERE id = $profileId");
            $profile = $result->fetch_assoc();
            $validityHours = $profile['validity_hours'] ?? 24;
            
            // Calculate expiry
            $expires = date('Y-m-d H:i:s', time() + ($validityHours * 3600));
            
            // Insert into database
            $this->conn->query("
                INSERT INTO hotspot_vouchers (pin_code, profile_id, expires_at) 
                VALUES ('$pin', $profileId, '$expires')
            ");
            
            $pins[] = $pin;
        }
        
        return $pins;
    }
    
    /**
     * Validate PIN code
     */
    public function validatePin($pin) {
        $pin = preg_replace('/[^0-9]/', '', $pin);
        
        $result = $this->conn->query("
            SELECT v.*, p.name as plan_name, p.data_limit_mb, p.validity_hours, p.speed_kbps
            FROM hotspot_vouchers v
            JOIN hotspot_profiles p ON v.profile_id = p.id
            WHERE v.pin_code = '$pin'
        ");
        
        if ($result->num_rows == 0) {
            return ['status' => 'error', 'message' => 'Invalid PIN'];
        }
        
        $voucher = $result->fetch_assoc();
        
        // Check status
        if ($voucher['status'] == 'used') {
            return ['status' => 'error', 'message' => 'PIN already used'];
        }
        
        if ($voucher['status'] == 'expired') {
            return ['status' => 'error', 'message' => 'PIN expired'];
        }
        
        if ($voucher['status'] == 'cancelled') {
            return ['status' => 'error', 'message' => 'PIN cancelled'];
        }
        
        // Check expiry
        if (strtotime($voucher['expires_at']) < time()) {
            $this->conn->query("UPDATE hotspot_vouchers SET status = 'expired' WHERE id = {$voucher['id']}");
            return ['status' => 'error', 'message' => 'PIN expired'];
        }
        
        return [
            'status' => 'success',
            'voucher' => $voucher
        ];
    }
    
    /**
     * Use PIN - mark as used
     */
    public function usePin($pin, $username) {
        $pin = preg_replace('/[^0-9]/', '', $pin);
        
        $this->conn->query("
            UPDATE hotspot_vouchers 
            SET status = 'used', used_by = '$username', used_at = NOW() 
            WHERE pin_code = '$pin'
        ");
        
        return true;
    }
    
    /**
     * Get profile by ID
     */
    public function getProfile($profileId) {
        $result = $this->conn->query("SELECT * FROM hotspot_profiles WHERE id = $profileId");
        return $result->fetch_assoc();
    }
    
    /**
     * Get all profiles
     */
    public function getAllProfiles() {
        $result = $this->conn->query("SELECT * FROM hotspot_profiles ORDER BY type, price");
        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }
        return $profiles;
    }
    
    /**
     * Get voucher statistics
     */
    public function getStats() {
        $stats = [];
        
        // Total vouchers
        $r = $this->conn->query("SELECT status, COUNT(*) as cnt FROM hotspot_vouchers GROUP BY status");
        while ($row = $r->fetch_assoc()) {
            $stats[$row['status']] = $row['cnt'];
        }
        
        // By profile
        $r = $this->conn->query("
            SELECT p.name, COUNT(v.id) as total, 
                   SUM(CASE WHEN v.status='available' THEN 1 ELSE 0 END) as available,
                   SUM(CASE WHEN v.status='used' THEN 1 ELSE 0 END) as used
            FROM hotspot_profiles p
            LEFT JOIN hotspot_vouchers v ON p.id = v.profile_id
            GROUP BY p.id
        ");
        
        $stats['by_profile'] = [];
        while ($row = $r->fetch_assoc()) {
            $stats['by_profile'][] = $row;
        }
        
        return $stats;
    }
}
