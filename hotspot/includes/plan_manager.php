<?php
/**
 * Hotspot Plan & Voucher Manager
 * Supports: Prepaid, Postpaid, Smart Bytes, Daily, Day & Night plans
 * Vouchers: Data TopUp, Recharge, Bandwidth, Discount, Time Extend
 */

class PlanManager {
    private $conn;
    
    public function __construct() {
        chdir(__DIR__ . '/../..');
        include 'config.php';
        $this->conn = $conn;
    }
    
    // ==================== PLAN MANAGEMENT ====================
    
    /**
     * Get all plans
     */
    public function getAllPlans($type = null, $status = 'active') {
        $sql = "SELECT * FROM hotspot_plan_types WHERE 1=1";
        
        if ($type) {
            $sql .= " AND type = '$type'";
        }
        if ($status) {
            $sql .= " AND status = '$status'";
        }
        
        $sql .= " ORDER BY type, price";
        
        $result = $this->conn->query($sql);
        $plans = [];
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
        return $plans;
    }
    
    /**
     * Get plan by ID
     */
    public function getPlan($id) {
        $result = $this->conn->query("SELECT * FROM hotspot_plan_types WHERE id = $id");
        return $result ? $result->fetch_assoc() : null;
    }
    
    /**
     * Create new plan
     */
    public function createPlan($data) {
        $name = $this->conn->real_escape_string($data['name']);
        $type = $this->conn->real_escape_string($data['type']);
        $dataLimit = (int)($data['data_limit_mb'] ?? 0);
        $timeLimit = (int)($data['time_limit_mins'] ?? 0);
        $speed = (int)($data['speed_kbps'] ?? 1024);
        $speedDown = (int)($data['speed_down_kbps'] ?? $speed);
        $speedUp = (int)($data['speed_up_kbps'] ?? 512);
        $fupLimit = (int)($data['fup_limit_mb'] ?? 0);
        $fupSpeed = (int)($data['fup_speed_kbps'] ?? 512);
        $price = (float)($data['price'] ?? 0);
        $setupFee = (float)($data['setup_fee'] ?? 0);
        $validity = (int)($data['validity_days'] ?? 30);
        $billingCycle = $this->conn->real_escape_string($data['billing_cycle'] ?? 'monthly');
        $isShared = isset($data['is_shared']) ? 1 : 0;
        $sharedUsers = (int)($data['shared_users'] ?? 1);
        $description = $this->conn->real_escape_string($data['description'] ?? '');
        
        $sql = "INSERT INTO hotspot_plan_types (
            name, type, data_limit_mb, time_limit_mins, speed_kbps, speed_down_kbps, speed_up_kbps,
            fup_limit_mb, fup_speed_kbps, price, setup_fee, validity_days, billing_cycle,
            is_shared, shared_users, description
        ) VALUES (
            '$name', '$type', $dataLimit, $timeLimit, $speed, $speedDown, $speedUp,
            $fupLimit, $fupSpeed, $price, $setupFee, $validity, '$billingCycle',
            $isShared, $sharedUsers, '$description'
        )";
        
        $this->conn->query($sql);
        return $this->conn->insert_id;
    }
    
    /**
     * Update plan
     */
    public function updatePlan($id, $data) {
        $fields = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'type', 'billing_cycle', 'description'])) {
                $fields[] = "$key = '" . $this->conn->real_escape_string($value) . "'";
            } elseif (in_array($key, ['is_shared'])) {
                $fields[] = "$key = " . ($value ? 1 : 0);
            } elseif (is_numeric($value)) {
                $fields[] = "$key = " . (int)$value;
            }
        }
        
        if (!empty($fields)) {
            $sql = "UPDATE hotspot_plan_types SET " . implode(', ', $fields) . " WHERE id = $id";
            return $this->conn->query($sql);
        }
        return false;
    }
    
    /**
     * Delete plan
     */
    public function deletePlan($id) {
        return $this->conn->query("DELETE FROM hotspot_plan_types WHERE id = $id");
    }
    
    // ==================== VOUCHER TYPES ====================
    
    /**
     * Get all voucher types
     */
    public function getAllVoucherTypes($status = 'active') {
        $sql = "SELECT * FROM hotspot_voucher_types";
        if ($status) {
            $sql .= " WHERE status = '$status'";
        }
        $sql .= " ORDER BY type, price";
        
        $result = $this->conn->query($sql);
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        return $types;
    }
    
    /**
     * Create voucher type
     */
    public function createVoucherType($data) {
        $name = $this->conn->real_escape_string($data['name']);
        $type = $this->conn->real_escape_string($data['type']);
        $value = (float)$data['value'];
        $unit = $this->conn->real_escape_string($data['unit'] ?? 'mb');
        $price = (float)($data['price'] ?? 0);
        $validity = (int)($data['validity_days'] ?? 30);
        
        $sql = "INSERT INTO hotspot_voucher_types (name, type, value, unit, price, validity_days) 
                VALUES ('$name', '$type', $value, '$unit', $price, $validity)";
        
        $this->conn->query($sql);
        return $this->conn->insert_id;
    }
    
    /**
     * Delete voucher type
     */
    public function deleteVoucherType($id) {
        return $this->conn->query("DELETE FROM hotspot_voucher_types WHERE id = $id");
    }
    
    // ==================== VOUCHER GENERATION ====================
    
    /**
     * Generate vouchers
     */
    public function generateVouchers($voucherTypeId, $count = 10, $profileId = null) {
        $type = $this->conn->query("SELECT * FROM hotspot_voucher_types WHERE id = $voucherTypeId")->fetch_assoc();
        if (!$type) {
            return ['status' => 'error', 'message' => 'Voucher type not found'];
        }
        
        $vouchers = [];
        
        for ($i = 0; $i < $count; $i++) {
            // Generate unique code
            $code = strtoupper(bin2hex(random_bytes(4)));
            
            // Calculate expiry
            $expires = date('Y-m-d H:i:s', time() + ($type['validity_days'] * 86400));
            
            // Determine profile
            $assignedProfile = $profileId;
            if (!$assignedProfile) {
                // Find matching profile based on voucher type
                if ($type['type'] == 'data_topup') {
                    $result = $this->conn->query("SELECT id FROM hotspot_profiles WHERE type = 'data' ORDER BY data_limit_mb DESC LIMIT 1");
                } else {
                    $result = $this->conn->query("SELECT id FROM hotspot_profiles WHERE type = 'time' ORDER BY validity_hours DESC LIMIT 1");
                }
                if ($result && $result->num_rows > 0) {
                    $p = $result->fetch_assoc();
                    $assignedProfile = $p['id'];
                }
            }
            
            // Insert voucher
            $this->conn->query("INSERT INTO hotspot_vouchers (pin_code, profile_id, expires_at) 
                VALUES ('$code', $assignedProfile, '$expires')");
            
            $vouchers[] = $code;
        }
        
        return [
            'status' => 'success',
            'count' => count($vouchers),
            'vouchers' => $vouchers
        ];
    }
    
    /**
     * Generate PIN-based vouchers (4-digit)
     */
    public function generatePINs($profileId, $count = 10) {
        $pins = [];
        
        // Get profile validity
        $profile = $this->getPlan($profileId);
        if (!$profile) {
            // Try old profiles table
            $result = $this->conn->query("SELECT validity_hours FROM hotspot_profiles WHERE id = $profileId");
            $profile = $result ? $result->fetch_assoc() : ['validity_hours' => 24];
        }
        
        $validityHours = $profile['validity_hours'] ?? 24;
        
        for ($i = 0; $i < $count; $i++) {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + ($validityHours * 3600));
            
            $this->conn->query("INSERT INTO hotspot_vouchers (pin_code, profile_id, expires_at) 
                VALUES ('$pin', $profileId, '$expires')");
            
            $pins[] = $pin;
        }
        
        return $pins;
    }
    
    // ==================== VOUCHER REDEMPTION ====================
    
    /**
     * Redeem voucher (for user account)
     */
    public function redeemVoucher($userId, $voucherCode) {
        $code = $this->conn->real_escape_string($voucherCode);
        
        // Check voucher
        $result = $this->conn->query("
            SELECT v.*, p.data_limit_mb, p.validity_hours, p.speed_kbps
            FROM hotspot_vouchers v
            JOIN hotspot_profiles p ON v.profile_id = p.id
            WHERE v.pin_code = '$code'
        ");
        
        if (!$result || $result->num_rows == 0) {
            return ['status' => 'error', 'message' => 'Invalid voucher'];
        }
        
        $voucher = $result->fetch_assoc();
        
        if ($voucher['status'] != 'available') {
            return ['status' => 'error', 'message' => 'Voucher already used or expired'];
        }
        
        // Get user
        $user = $this->conn->query("SELECT * FROM hotspot_users WHERE id = $userId")->fetch_assoc();
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }
        
        // Apply voucher based on profile type
        $newDataLimit = $user['data_limit_mb'];
        $newValidUntil = $user['valid_until'];
        
        if ($voucher['type'] == 'data' || $voucher['profile_id']) {
            // Add data limit
            $profileData = $voucher['data_limit_mb'] ?? 0;
            if ($profileData > 0) {
                if ($newDataLimit > 0) {
                    $newDataLimit += $profileData;
                } else {
                    $newDataLimit = $profileData;
                }
            }
        }
        
        // Extend validity
        $validityHours = $voucher['validity_hours'] ?? 24;
        if ($newValidUntil && strtotime($newValidUntil) > time()) {
            $newValidUntil = date('Y-m-d', strtotime($newValidUntil) + ($validityHours * 3600));
        } else {
            $newValidUntil = date('Y-m-d', time() + ($validityHours * 3600));
        }
        
        // Update user
        $this->conn->query("UPDATE hotspot_users SET 
            data_limit_mb = $newDataLimit,
            valid_until = '$newValidUntil',
            status = 'active'
            WHERE id = $userId");
        
        // Mark voucher as used
        $this->conn->query("UPDATE hotspot_vouchers SET 
            status = 'used', 
            used_by = '{$user['username']}', 
            used_at = NOW() 
            WHERE id = {$voucher['id']}");
        
        return [
            'status' => 'success',
            'message' => 'Voucher redeemed successfully',
            'data_added_mb' => $voucher['data_limit_mb'] ?? 0,
            'validity_extended_hours' => $validityHours
        ];
    }
    
    /**
     * TopUp user data
     */
    public function topupData($userId, $mb) {
        $user = $this->conn->query("SELECT * FROM hotspot_users WHERE id = $userId")->fetch_assoc();
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }
        
        $newLimit = $user['data_limit_mb'] + $mb;
        
        $this->conn->query("UPDATE hotspot_users SET data_limit_mb = $newLimit WHERE id = $userId");
        
        return [
            'status' => 'success',
            'message' => "Added {$mb}MB to account",
            'new_limit' => $newLimit
        ];
    }
    
    /**
     * Recharge user balance
     */
    public function recharge($userId, $amount) {
        $user = $this->conn->query("SELECT * FROM hotspot_users WHERE id = $userId")->fetch_assoc();
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }
        
        $newBalance = $user['current_balance'] + $amount;
        
        $this->conn->query("UPDATE hotspot_users SET current_balance = $newBalance WHERE id = $userId");
        
        // Log transaction
        $this->conn->query("INSERT INTO hotspot_invoices (user_id, description, amount, total, status, paid_at) 
            VALUES ($userId, 'Account Recharge', $amount, $amount, 'paid', NOW())");
        
        return [
            'status' => 'success',
            'message' => "Added Rs.{$amount} to account",
            'new_balance' => $newBalance
        ];
    }
    
    // ==================== BILLING ====================
    
    /**
     * Create invoice
     */
    public function createInvoice($userId, $planId, $description = '') {
        $user = $this->conn->query("SELECT * FROM hotspot_users WHERE id = $userId")->fetch_assoc();
        $plan = $this->getPlan($planId);
        
        if (!$user || !$plan) {
            return ['status' => 'error', 'message' => 'User or plan not found'];
        }
        
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
        $amount = $plan['price'];
        $tax = $amount * 0.13; // 13% VAT
        $total = $amount + $tax;
        $dueDate = date('Y-m-d', strtotime('+' . $plan['validity_days'] . ' days'));
        
        $desc = $this->conn->real_escape_string($description ?: "{$plan['name']} - {$plan['validity_days']} days");
        
        $this->conn->query("INSERT INTO hotspot_invoices (
            invoice_number, user_id, plan_id, description, amount, tax, total, due_date
        ) VALUES (
            '$invoiceNumber', $userId, $planId, '$desc', $amount, $tax, $total, '$dueDate'
        )");
        
        return [
            'status' => 'success',
            'invoice_id' => $this->conn->insert_id,
            'invoice_number' => $invoiceNumber,
            'total' => $total
        ];
    }
    
    /**
     * Pay invoice
     */
    public function payInvoice($invoiceId, $paymentMethod = 'cash', $reference = '') {
        $invoice = $this->conn->query("SELECT * FROM hotspot_invoices WHERE id = $invoiceId")->fetch_assoc();
        if (!$invoice) {
            return ['status' => 'error', 'message' => 'Invoice not found'];
        }
        
        if ($invoice['status'] == 'paid') {
            return ['status' => 'error', 'message' => 'Invoice already paid'];
        }
        
        $ref = $this->conn->real_escape_string($reference);
        
        $this->conn->query("UPDATE hotspot_invoices SET 
            status = 'paid', 
            paid_at = NOW(),
            payment_method = '$paymentMethod',
            payment_reference = '$ref'
            WHERE id = $invoiceId");
        
        // Activate user
        $userId = $invoice['user_id'];
        $planId = $invoice['plan_id'];
        
        if ($planId) {
            $plan = $this->getPlan($planId);
            $validUntil = date('Y-m-d', strtotime('+' . $plan['validity_days'] . ' days'));
            
            $this->conn->query("UPDATE hotspot_users SET 
                status = 'active',
                valid_until = '$validUntil',
                profile_id = $planId
                WHERE id = $userId");
        }
        
        return ['status' => 'success', 'message' => 'Invoice paid successfully'];
    }
    
    /**
     * Get user invoices
     */
    public function getUserInvoices($userId) {
        $result = $this->conn->query("SELECT * FROM hotspot_invoices WHERE user_id = $userId ORDER BY created_at DESC");
        $invoices = [];
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
        return $invoices;
    }
    
    // ==================== STATISTICS ====================
    
    /**
     * Get plan statistics
     */
    public function getStats() {
        $stats = [
            'total_plans' => 0,
            'active_plans' => 0,
            'total_vouchers' => 0,
            'available_vouchers' => 0,
            'used_vouchers' => 0,
            'revenue_month' => 0,
            'pending_invoices' => 0,
            'paid_invoices' => 0
        ];
        
        // Plans
        $result = $this->conn->query("SELECT status, COUNT(*) as cnt FROM hotspot_plan_types GROUP BY status");
        while ($row = $result->fetch_assoc()) {
            $stats['total_plans'] += $row['cnt'];
            if ($row['status'] == 'active') {
                $stats['active_plans'] = $row['cnt'];
            }
        }
        
        // Vouchers
        $result = $this->conn->query("SELECT status, COUNT(*) as cnt FROM hotspot_vouchers GROUP BY status");
        while ($row = $result->fetch_assoc()) {
            $stats['total_vouchers'] += $row['cnt'];
            if ($row['status'] == 'available') {
                $stats['available_vouchers'] = $row['cnt'];
            } elseif ($row['status'] == 'used') {
                $stats['used_vouchers'] = $row['cnt'];
            }
        }
        
        // Invoices
        $result = $this->conn->query("SELECT status, SUM(total) as sum FROM hotspot_invoices WHERE MONTH(created_at) = MONTH(NOW()) GROUP BY status");
        while ($row = $result->fetch_assoc()) {
            if ($row['status'] == 'paid') {
                $stats['revenue_month'] = $row['sum'] ?? 0;
            } elseif ($row['status'] == 'pending') {
                $stats['pending_invoices'] = $row['sum'] ?? 0;
            }
        }
        
        return $stats;
    }
    
    /**
     * Generate invoice number
     */
    public function generateInvoiceNumber() {
        $prefix = 'INV';
        $date = date('Ymd');
        $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        return "{$prefix}-{$date}-{$random}";
    }
}
