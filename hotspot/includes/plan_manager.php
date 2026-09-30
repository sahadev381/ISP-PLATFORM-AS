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
        
        $params = [];
        if ($type) {
            $sql     .= " AND type = ?";
            $params[] = $type;
        }
        if ($status) {
            $sql     .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY type, price";

        return db_all($this->conn, $sql, $params);
    }
    
    /**
     * Get plan by ID
     */
    public function getPlan($id) {
        return db_one($this->conn, "SELECT * FROM hotspot_plan_types WHERE id = ?", [(int) $id]);
    }
    
    /**
     * Create new plan
     */
    public function createPlan($data) {
        $name = (string) ($data['name'] ?? '');
        $type = (string) ($data['type'] ?? '');
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
        $billingCycle = (string) ($data['billing_cycle'] ?? 'monthly');
        $isShared = isset($data['is_shared']) ? 1 : 0;
        $sharedUsers = (int)($data['shared_users'] ?? 1);
        $description = (string) ($data['description'] ?? '');

        return db_insert($this->conn, "INSERT INTO hotspot_plan_types (
            name, type, data_limit_mb, time_limit_mins, speed_kbps, speed_down_kbps, speed_up_kbps,
            fup_limit_mb, fup_speed_kbps, price, setup_fee, validity_days, billing_cycle,
            is_shared, shared_users, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $name, $type, $dataLimit, $timeLimit, $speed, $speedDown, $speedUp,
            $fupLimit, $fupSpeed, $price, $setupFee, $validity, $billingCycle,
            $isShared, $sharedUsers, $description,
        ]);
    }
    
    /**
     * Update plan
     */
    public function updatePlan($id, $data) {
        // Only these columns may ever be written, and only via bound values.
        $textCols = ['name', 'type', 'billing_cycle', 'description'];
        $intCols  = ['data_limit_mb', 'time_limit_mins', 'speed_kbps', 'speed_down_kbps',
                     'speed_up_kbps', 'fup_limit_mb', 'fup_speed_kbps', 'validity_days',
                     'shared_users', 'is_shared'];
        $numCols  = ['price', 'setup_fee'];

        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $textCols, true)) {
                $fields[] = "`$key` = ?";
                $params[] = (string) $value;
            } elseif ($key === 'is_shared') {
                $fields[] = "`$key` = ?";
                $params[] = $value ? 1 : 0;
            } elseif (in_array($key, $intCols, true)) {
                $fields[] = "`$key` = ?";
                $params[] = (int) $value;
            } elseif (in_array($key, $numCols, true)) {
                $fields[] = "`$key` = ?";
                $params[] = (float) $value;
            }
        }

        if ($fields) {
            $params[] = (int) $id;
            return db_exec($this->conn, "UPDATE hotspot_plan_types SET "
                . implode(', ', $fields) . " WHERE id = ?", $params);
        }
        return false;
    }
    
    /**
     * Delete plan
     */
    public function deletePlan($id) {
        return db_exec($this->conn, "DELETE FROM hotspot_plan_types WHERE id = ?", [(int) $id]);
    }
    
    // ==================== VOUCHER TYPES ====================
    
    /**
     * Get all voucher types
     */
    public function getAllVoucherTypes($status = 'active') {
        $sql    = "SELECT * FROM hotspot_voucher_types";
        $params = [];
        if ($status) {
            $sql     .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY type, price";

        return db_all($this->conn, $sql, $params);
    }
    
    /**
     * Create voucher type
     */
    public function createVoucherType($data) {
        return db_insert($this->conn,
            "INSERT INTO hotspot_voucher_types (name, type, value, unit, price, validity_days)
             VALUES (?, ?, ?, ?, ?, ?)", [
            (string) ($data['name'] ?? ''),
            (string) ($data['type'] ?? ''),
            (float) ($data['value'] ?? 0),
            (string) ($data['unit'] ?? 'mb'),
            (float) ($data['price'] ?? 0),
            (int) ($data['validity_days'] ?? 30),
        ]);
    }
    
    /**
     * Delete voucher type
     */
    public function deleteVoucherType($id) {
        return db_exec($this->conn, "DELETE FROM hotspot_voucher_types WHERE id = ?", [(int) $id]);
    }
    
    // ==================== VOUCHER GENERATION ====================
    
    /**
     * Generate vouchers
     */
    public function generateVouchers($voucherTypeId, $count = 10, $profileId = null) {
        $type = db_one($this->conn, "SELECT * FROM hotspot_voucher_types WHERE id = ?", [(int) $voucherTypeId]);
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
                    $p = db_one($this->conn, "SELECT id FROM hotspot_profiles WHERE type = 'data' ORDER BY data_limit_mb DESC LIMIT 1");
                } else {
                    $p = db_one($this->conn, "SELECT id FROM hotspot_profiles WHERE type = 'time' ORDER BY validity_hours DESC LIMIT 1");
                }
                if ($p) {
                    $assignedProfile = $p['id'];
                }
            }
            
            // Insert voucher
            db_exec($this->conn, "INSERT INTO hotspot_vouchers (pin_code, profile_id, expires_at)
                VALUES (?, ?, ?)", [$code, (int) $assignedProfile, $expires]);
            
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
            $profile = db_one($this->conn, "SELECT validity_hours FROM hotspot_profiles WHERE id = ?", [(int) $profileId])
                ?? ['validity_hours' => 24];
        }
        
        $validityHours = $profile['validity_hours'] ?? 24;
        
        for ($i = 0; $i < $count; $i++) {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + ($validityHours * 3600));
            
            db_exec($this->conn, "INSERT INTO hotspot_vouchers (pin_code, profile_id, expires_at)
                VALUES (?, ?, ?)", [$pin, (int) $profileId, $expires]);
            
            $pins[] = $pin;
        }
        
        return $pins;
    }
    
    // ==================== VOUCHER REDEMPTION ====================
    
    /**
     * Redeem voucher (for user account)
     */
    public function redeemVoucher($userId, $voucherCode) {
        // Check voucher
        $voucher = db_one($this->conn, "
            SELECT v.*, p.data_limit_mb, p.validity_hours, p.speed_kbps
            FROM hotspot_vouchers v
            JOIN hotspot_profiles p ON v.profile_id = p.id
            WHERE v.pin_code = ?
        ", [(string) $voucherCode]);

        if (!$voucher) {
            return ['status' => 'error', 'message' => 'Invalid voucher'];
        }

        
        if ($voucher['status'] != 'available') {
            return ['status' => 'error', 'message' => 'Voucher already used or expired'];
        }
        
        // Get user
        $user = db_one($this->conn, "SELECT * FROM hotspot_users WHERE id = ?", [(int) $userId]);
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
        db_exec($this->conn, "UPDATE hotspot_users SET
            data_limit_mb = ?,
            valid_until = ?,
            status = 'active'
            WHERE id = ?", [$newDataLimit, $newValidUntil, (int) $userId]);

        // Mark voucher as used
        db_exec($this->conn, "UPDATE hotspot_vouchers SET
            status = 'used',
            used_by = ?,
            used_at = NOW()
            WHERE id = ?", [$user['username'], (int) $voucher['id']]);
        
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
        $user = db_one($this->conn, "SELECT * FROM hotspot_users WHERE id = ?", [(int) $userId]);
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        $newLimit = $user['data_limit_mb'] + $mb;

        db_exec($this->conn, "UPDATE hotspot_users SET data_limit_mb = ? WHERE id = ?", [$newLimit, (int) $userId]);
        
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
        $user = db_one($this->conn, "SELECT * FROM hotspot_users WHERE id = ?", [(int) $userId]);
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        $newBalance = $user['current_balance'] + $amount;

        db_exec($this->conn, "UPDATE hotspot_users SET current_balance = ? WHERE id = ?", [$newBalance, (int) $userId]);

        // Log transaction
        db_exec($this->conn, "INSERT INTO hotspot_invoices (user_id, description, amount, total, status, paid_at)
            VALUES (?, 'Account Recharge', ?, ?, 'paid', NOW())", [(int) $userId, $amount, $amount]);
        
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
        $user = db_one($this->conn, "SELECT * FROM hotspot_users WHERE id = ?", [(int) $userId]);
        $plan = $this->getPlan($planId);
        
        if (!$user || !$plan) {
            return ['status' => 'error', 'message' => 'User or plan not found'];
        }
        
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
        $amount = $plan['price'];
        $tax = $amount * 0.13; // 13% VAT
        $total = $amount + $tax;
        $dueDate = date('Y-m-d', strtotime('+' . $plan['validity_days'] . ' days'));
        
        $desc = $description ?: "{$plan['name']} - {$plan['validity_days']} days";

        $invoiceId = db_insert($this->conn, "INSERT INTO hotspot_invoices (
            invoice_number, user_id, plan_id, description, amount, tax, total, due_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$invoiceNumber, (int) $userId, (int) $planId, $desc, $amount, $tax, $total, $dueDate]);

        return [
            'status' => 'success',
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'total' => $total
        ];
    }
    
    /**
     * Pay invoice
     */
    public function payInvoice($invoiceId, $paymentMethod = 'cash', $reference = '') {
        $invoice = db_one($this->conn, "SELECT * FROM hotspot_invoices WHERE id = ?", [(int) $invoiceId]);
        if (!$invoice) {
            return ['status' => 'error', 'message' => 'Invoice not found'];
        }
        
        if ($invoice['status'] == 'paid') {
            return ['status' => 'error', 'message' => 'Invoice already paid'];
        }
        
        db_exec($this->conn, "UPDATE hotspot_invoices SET
            status = 'paid',
            paid_at = NOW(),
            payment_method = ?,
            payment_reference = ?
            WHERE id = ?", [(string) $paymentMethod, (string) $reference, (int) $invoiceId]);
        
        // Activate user
        $userId = $invoice['user_id'];
        $planId = $invoice['plan_id'];
        
        if ($planId) {
            $plan = $this->getPlan($planId);
            $validUntil = date('Y-m-d', strtotime('+' . $plan['validity_days'] . ' days'));
            
            db_exec($this->conn, "UPDATE hotspot_users SET
                status = 'active',
                valid_until = ?,
                profile_id = ?
                WHERE id = ?", [$validUntil, (int) $planId, (int) $userId]);
        }
        
        return ['status' => 'success', 'message' => 'Invoice paid successfully'];
    }
    
    /**
     * Get user invoices
     */
    public function getUserInvoices($userId) {
        return db_all($this->conn, "SELECT * FROM hotspot_invoices WHERE user_id = ? ORDER BY created_at DESC", [(int) $userId]);
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
