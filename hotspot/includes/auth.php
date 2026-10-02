<?php
/**
 * Hotspot Authentication System
 * Supports: Browser Login, MAC Address, IP Address, IP+MAC Binding, PPPoE, Voucher
 */

class HotspotAuth {
    private $conn;
    private $session_timeout = 3600;
    
    public function __construct() {
        /* require_once returns true without re-executing when the file
           has already been included, so $conn is never created in this
           scope - every caller that had already loaded config.php got
           null here. Take it from the global scope instead, where
           config.php actually put it. */
        require_once __DIR__ . '/../../config.php';
        global $conn;
        $this->conn = $conn;

        if (!$this->conn instanceof mysqli) {
            throw new RuntimeException('HotspotAuth needs a database connection.');
        }

        // Load settings
        $value = db_value(
            $this->conn,
            "SELECT setting_value FROM hotspot_settings WHERE setting_key = 'session_timeout'"
        );
        if ($value !== null) {
            $this->session_timeout = (int) $value;
        }
    }
    
    /**
     * Main authentication handler
     */
    public function authenticate($credentials) {
        $loginType = $credentials['login_type'] ?? 'user';
        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';
        $pin = $credentials['pin'] ?? '';
        $mac = $credentials['mac'] ?? $this->getClientMac();
        $ip = $credentials['ip'] ?? $this->getClientIP();
        
        $this->logAccess(null, $username, $ip, $mac, 'login_attempt', $loginType, 'success', 'Login attempt started');
        
        switch ($loginType) {
            case 'voucher':
                return $this->authenticateWithVoucher($pin, $mac, $ip);
            case 'sms':
                return $this->authenticateWithSMS($credentials);
            case 'mac':
                return $this->authenticateWithMAC($mac, $username);
            case 'ip':
                return $this->authenticateWithIP($ip, $username);
            case 'ip_mac':
                return $this->authenticateWithIPMAC($ip, $mac, $username);
            case 'pppoe':
                return $this->authenticateWithPPPoE($username, $password);
            case 'user':
            default:
                return $this->authenticateWithUser($username, $password, $mac, $ip);
        }
    }
    
    /**
     * Authenticate with username/password (Browser Login)
     */
    private function authenticateWithUser($username, $password, $mac, $ip) {
        // Get user
        $user = db_one($this->conn, "SELECT * FROM hotspot_users WHERE username = ?", [$username]);
        if (!$user) {
            $this->logAccess(null, $username, $ip, $mac, 'login', 'user', 'failed', 'User not found');
            return ['status' => 'error', 'message' => 'Invalid username or password'];
        }

        // Check status
        if ($user['status'] != 'active') {
            $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Account ' . $user['status']);
            return ['status' => 'error', 'message' => 'Account is ' . $user['status']];
        }
        
        // Check valid_until for postpaid
        if ($user['plan_type'] == 'postpaid' && !empty($user['valid_until'])) {
            if (strtotime($user['valid_until']) < time()) {
                $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Account expired');
                return ['status' => 'error', 'message' => 'Account has expired'];
            }
            
            // Check credit limit
            if ($user['credit_limit'] > 0 && $user['current_balance'] >= $user['credit_limit']) {
                $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Credit limit exceeded');
                return ['status' => 'error', 'message' => 'Credit limit exceeded. Please recharge.'];
            }
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'failed', 'Invalid password');
            return ['status' => 'error', 'message' => 'Invalid username or password'];
        }
        
        // Check Hours of Service (HoS)
        if ($user['hos_enabled']) {
            $now = time();
            $start = strtotime($user['hos_start']);
            $end = strtotime($user['hos_end']);
            
            if ($start > $end) {
                // Overnight (e.g., 22:00 to 06:00)
                if ($now < $start && $now > $end) {
                    $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Outside HoS hours');
                    return ['status' => 'error', 'message' => 'Access not allowed at this time'];
                }
            } else {
                // Same day (e.g., 08:00 to 22:00)
                if ($now < $start || $now > $end) {
                    $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Outside HoS hours');
                    return ['status' => 'error', 'message' => 'Access not allowed at this time'];
                }
            }
        }
        
        // Check single session
        if ($user['single_session']) {
            $active = db_all($this->conn, "SELECT id FROM hotspot_sessions WHERE username = ? AND status = 'active'", [$username]);
            if ($active) {
                $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Single session active');
                return ['status' => 'error', 'message' => 'Account already logged in elsewhere'];
            }
        }
        
        // Check max devices
        if ($user['max_devices'] > 0) {
            $active = db_all($this->conn, "SELECT id FROM hotspot_sessions WHERE username = ? AND status = 'active'", [$username]);
            $currentDevices = count($active);
            if ($currentDevices >= $user['max_devices']) {
                $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'Max devices reached');
                return ['status' => 'error', 'message' => 'Maximum devices reached. Logout from another device.'];
            }
        }
        
        // Check IP-MAC binding
        if ($user['ip_mac_binding']) {
            if (!empty($user['ip_address']) && $user['ip_address'] != $ip) {
                $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'ip_mac', 'blocked', 'IP mismatch');
                return ['status' => 'error', 'message' => 'IP address not allowed for this account'];
            }
            if (!empty($user['mac_address']) && $user['mac_address'] != $mac) {
                $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'ip_mac', 'blocked', 'MAC mismatch');
                return ['status' => 'error', 'message' => 'Device not authorized'];
            }
        }
        
        // Check access lists (blacklist)
        if ($this->isBlocked($ip, $mac)) {
            $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'blocked', 'In blacklist');
            return ['status' => 'error', 'message' => 'Access denied'];
        }
        
        // Create session
        $this->createSession($user['id'], $username, $mac, $ip, $user['profile_id']);
        
        // Update last login
        db_exec($this->conn, "UPDATE hotspot_users SET last_login = NOW() WHERE id = ?", [(int) $user['id']]);
        
        $this->logAccess($user['id'], $username, $ip, $mac, 'login', 'user', 'success', 'Login successful');
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user
        ];
    }
    
    /**
     * Authenticate with PIN/Voucher
     */
    private function authenticateWithVoucher($pin, $mac, $ip) {
        $pin = preg_replace('/[^0-9]/', '', $pin);
        
        $voucher = db_one($this->conn, "
            SELECT v.*, p.name as plan_name, p.data_limit_mb, p.validity_hours, p.speed_kbps
            FROM hotspot_vouchers v
            JOIN hotspot_profiles p ON v.profile_id = p.id
            WHERE v.pin_code = ?
        ", [$pin]);

        if (!$voucher) {
            $this->logAccess(null, '', $ip, $mac, 'login', 'voucher', 'failed', 'Invalid PIN');
            return ['status' => 'error', 'message' => 'Invalid PIN code'];
        }

        if ($voucher['status'] != 'available') {
            $this->logAccess(null, '', $ip, $mac, 'login', 'voucher', 'failed', 'PIN status: ' . $voucher['status']);
            return ['status' => 'error', 'message' => 'PIN already used or expired'];
        }
        
        if (strtotime($voucher['expires_at']) < time()) {
            db_exec($this->conn, "UPDATE hotspot_vouchers SET status = 'expired' WHERE id = ?", [(int) $voucher['id']]);
            $this->logAccess(null, '', $ip, $mac, 'login', 'voucher', 'failed', 'PIN expired');
            return ['status' => 'error', 'message' => 'PIN has expired'];
        }
        
        // Check access lists
        if ($this->isBlocked($ip, $mac)) {
            $this->logAccess(null, '', $ip, $mac, 'login', 'voucher', 'blocked', 'In blacklist');
            return ['status' => 'error', 'message' => 'Access denied'];
        }
        
        // Generate username from voucher
        $username = 'VOUCHER_' . $voucher['pin_code'];
        $voucherId = $voucher['id'];
        
        // Mark voucher as used
        db_exec($this->conn, "UPDATE hotspot_vouchers SET status = 'used', used_by = ?, used_at = NOW() WHERE id = ?", [$username, (int) $voucherId]);
        
        // Create session
        $this->createSession(null, $username, $mac, $ip, $voucher['profile_id']);
        
        $this->logAccess(null, $username, $ip, $mac, 'login', 'voucher', 'success', 'Login successful');
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'voucher' => $voucher,
            'username' => $username
        ];
    }
    
    /**
     * Authenticate with MAC address only
     */
    private function authenticateWithMAC($mac, $username = '') {
        $mac = strtoupper($mac);

        $sql    = "SELECT * FROM hotspot_users WHERE mac_address = ?";
        $params = [$mac];
        if (!empty($username)) {
            $sql     .= " OR username = ?";
            $params[] = $username;
        }

        $user = db_one($this->conn, $sql . " LIMIT 1", $params);

        if (!$user) {
            $this->logAccess(null, $username, '', $mac, 'login', 'mac', 'failed', 'MAC not registered');
            return ['status' => 'error', 'message' => 'Device not authorized'];
        }

        
        if ($user['status'] != 'active') {
            return ['status' => 'error', 'message' => 'Account is ' . $user['status']];
        }
        
        $ip = $this->getClientIP();
        
        // Create session
        $this->createSession($user['id'], $user['username'], $mac, $ip, $user['profile_id']);
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user
        ];
    }
    
    /**
     * Authenticate with IP address only
     */
    private function authenticateWithIP($ip, $username = '') {
        $sql    = "SELECT * FROM hotspot_users WHERE ip_address = ?";
        $params = [$ip];
        if (!empty($username)) {
            $sql     .= " OR username = ?";
            $params[] = $username;
        }

        $user = db_one($this->conn, $sql . " LIMIT 1", $params);

        if (!$user) {
            return ['status' => 'error', 'message' => 'IP not authorized'];
        }

        
        if ($user['status'] != 'active') {
            return ['status' => 'error', 'message' => 'Account is ' . $user['status']];
        }
        
        $mac = $this->getClientMac();
        
        $this->createSession($user['id'], $user['username'], $mac, $ip, $user['profile_id']);
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user
        ];
    }
    
    /**
     * Authenticate with IP + MAC binding
     */
    private function authenticateWithIPMAC($ip, $mac, $username = '') {
        $mac = strtoupper($mac);
        
        $sql = "SELECT * FROM hotspot_users WHERE ip_mac_binding = 1 AND (
            (ip_address = ? AND mac_address = ?) OR
            (allowed_ips LIKE ? AND allowed_ips LIKE ?)
        )";
        $params = [$ip, $mac, db_like($ip), db_like($mac)];

        if (!empty($username)) {
            $sql     .= " OR username = ?";
            $params[] = $username;
        }

        $user = db_one($this->conn, $sql . " LIMIT 1", $params);

        if (!$user) {
            return ['status' => 'error', 'message' => 'IP/MAC combination not authorized'];
        }

        
        if ($user['status'] != 'active') {
            return ['status' => 'error', 'message' => 'Account is ' . $user['status']];
        }
        
        $this->createSession($user['id'], $user['username'], $mac, $ip, $user['profile_id']);
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user
        ];
    }
    
    /**
     * Authenticate with PPPoE (via RADIUS)
     */
    private function authenticateWithPPPoE($username, $password) {
        // Check against RADIUS tables
        $rad = db_one(
            $this->conn,
            "SELECT * FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password'",
            [$username]
        );

        if (!$rad) {
            $this->logAccess(null, $username, '', '', 'login', 'pppoe', 'failed', 'User not found in RADIUS');
            return ['status' => 'error', 'message' => 'Invalid PPPoE credentials'];
        }

        // Constant-time comparison so the password cannot be guessed by timing
        if (!hash_equals((string) $rad['value'], (string) $password)) {
            $this->logAccess(null, $username, '', '', 'login', 'pppoe', 'failed', 'Invalid password');
            return ['status' => 'error', 'message' => 'Invalid PPPoE credentials'];
        }
        
        // Get user profile from radusergroup
        $group = db_one($this->conn, "SELECT groupname FROM radusergroup WHERE username = ? LIMIT 1", [$username]);
        $profileId = 1;
        if ($group) {
            $profile = db_one(
                $this->conn,
                "SELECT id FROM hotspot_profiles WHERE name LIKE ? LIMIT 1",
                [db_like((string) $group['groupname'])]
            );
            if ($profile) {
                $profileId = (int) $profile['id'];
            }
        }
        
        $ip = $this->getClientIP();
        $mac = $this->getClientMac();
        
        // Create session
        $this->createSession(null, $username, $mac, $ip, $profileId);
        
        $this->logAccess(null, $username, $ip, $mac, 'login', 'pppoe', 'success', 'PPPoE login successful');
        
        return [
            'status' => 'success',
            'message' => 'PPPoE login successful',
            'username' => $username
        ];
    }
    
    /**
     * Authenticate with SMS OTP
     */
    private function authenticateWithSMS($credentials) {
        $phone = $credentials['phone'] ?? '';
        $otp = $credentials['otp'] ?? '';
        
        if (empty($phone) || empty($otp)) {
            return ['status' => 'error', 'message' => 'Phone and OTP required'];
        }
        
        // Verify OTP
        $otpRecord = db_one($this->conn, "
            SELECT * FROM hotspot_sms_otp
            WHERE phone = ? AND otp_code = ?
            AND status = 'pending' AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ", [$phone, $otp]);

        if (!$otpRecord) {
            $this->logAccess(null, $phone, '', '', 'login', 'sms', 'failed', 'Invalid OTP');
            return ['status' => 'error', 'message' => 'Invalid or expired OTP'];
        }

        // Mark OTP as used
        db_exec($this->conn, "UPDATE hotspot_sms_otp SET status = 'used', used_at = NOW() WHERE id = ?", [(int) $otpRecord['id']]);

        // Find or create user
        $user = db_one($this->conn, "SELECT * FROM hotspot_users WHERE phone = ?", [$phone]);

        if (!$user) {
            // Create temporary user
            $tempPass = password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
            $username = "SMS_$phone";
            $userId = db_insert($this->conn, "INSERT INTO hotspot_users (phone, username, password, auth_method, status)
                VALUES (?, ?, ?, 'sms', 'active')", [$phone, $username, $tempPass]);
        } else {
            $userId = $user['id'];
            $username = $user['username'];
        }
        
        $ip = $this->getClientIP();
        $mac = $this->getClientMac();
        
        // Create session
        $this->createSession($userId, $username, $mac, $ip, $user['profile_id'] ?? 1);
        
        $this->logAccess($userId, $username, $ip, $mac, 'login', 'sms', 'success', 'SMS login successful');
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user ?? ['id' => $userId, 'username' => $username]
        ];
    }
    
    /**
     * Send SMS OTP
     */
    public function sendSMSOTP($phone) {
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // Save OTP
        db_exec($this->conn, "DELETE FROM hotspot_sms_otp WHERE phone = ? AND status = 'pending'", [$phone]);
        db_exec($this->conn, "INSERT INTO hotspot_sms_otp (phone, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))", [$phone, $otp]);

        // Send SMS
        include_once __DIR__ . '/sms.php';
        $sms = new SMSGateway();
        $result = $sms->send($phone, "Your OTP is: $otp. Valid for 5 minutes.");
        
        return ['status' => 'success', 'message' => 'OTP sent to ' . substr($phone, -4)];
    }
    
    /**
     * Create session
     */
    private function createSession($userId, $username, $mac, $ip, $profileId) {
        $sessionId = bin2hex(random_bytes(16));

        db_exec($this->conn, "INSERT INTO hotspot_sessions (session_id, username, mac, ip_address, profile_id, login_time, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'active')", [$sessionId, $username, $mac, $ip, (int) $profileId]);
        
        $_SESSION['hotspot_user_id'] = $userId;
        $_SESSION['hotspot_username'] = $username;
        $_SESSION['hotspot_session_id'] = $sessionId;
        $_SESSION['hotspot_login_time'] = time();
    }
    
    /**
     * Logout
     */
    public function logout($sessionId = '') {
        $sessionId = $sessionId ?? $_SESSION['hotspot_session_id'] ?? '';
        
        if (!empty($sessionId)) {
            db_exec($this->conn, "UPDATE hotspot_sessions SET logout_time = NOW(), status = 'closed' WHERE session_id = ?", [$sessionId]);
            
            $username = $_SESSION['hotspot_username'] ?? '';
            $ip = $this->getClientIP();
            $mac = $this->getClientMac();
            $this->logAccess(null, $username, $ip, $mac, 'logout', '', 'success', 'Logged out');
        }
        
        session_unset();
        session_destroy();
        
        return ['status' => 'success', 'message' => 'Logged out successfully'];
    }
    
    /**
     * Check if IP/MAC is blocked
     */
    private function isBlocked($ip, $mac) {
        $mac = strtoupper((string) $mac);

        // Check IP blacklist
        if (db_one($this->conn, "SELECT id FROM hotspot_access_lists WHERE list_type = 'ip' AND value = ? AND is_active = 1", [$ip])) {
            return true;
        }

        // Check MAC blacklist
        if (db_one($this->conn, "SELECT id FROM hotspot_access_lists WHERE list_type = 'mac' AND value = ? AND is_active = 1", [$mac])) {
            return true;
        }

        return false;
    }
    
    /**
     * Log access attempt
     */
    private function logAccess($userId, $username, $ip, $mac, $action, $authMethod, $status, $message) {
        db_exec($this->conn, "INSERT INTO hotspot_access_logs (user_id, username, ip_address, mac_address, action, auth_method, status, message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
            $userId ? (int) $userId : null,
            (string) $username,
            (string) $ip,
            (string) $mac,
            (string) $action,
            (string) $authMethod,
            (string) $status,
            (string) $message,
        ]);
    }
    
    /**
     * Get client IP
     */
    public function getClientIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return trim($ip);
    }
    
    /**
     * Get client MAC address (from headers if available)
     */
    public function getClientMac() {
        $mac = $_SERVER['HTTP_X_CLIENT_MAC'] ?? '';
        if (empty($mac)) {
            // Try to get from router
            $mac = $_SERVER['HTTP_X_REAL_IP'] ?? '';
        }
        $mac = strtoupper(trim((string) $mac));

        // This value is fully client-controlled; only accept a real MAC.
        if (!preg_match('/^([0-9A-F]{2}[:-]){5}[0-9A-F]{2}$/', $mac)) {
            return '';
        }
        return $mac;
    }
    
    /**
     * Check active session
     */
    public function checkSession() {
        if (!isset($_SESSION['hotspot_session_id'])) {
            return null;
        }
        
        $sessionId = $_SESSION['hotspot_session_id'];

        return db_one(
            $this->conn,
            "SELECT * FROM hotspot_sessions WHERE session_id = ? AND status = 'active'",
            [$sessionId]
        );
    }
    
    /**
     * Get user by username
     */
    public function getUser($username) {
        return db_one($this->conn, "SELECT * FROM hotspot_users WHERE username = ?", [$username]);
    }
    
    /**
     * Get profile details
     */
    public function getProfile($profileId) {
        return db_one($this->conn, "SELECT * FROM hotspot_profiles WHERE id = ?", [(int) $profileId]);
    }
}
