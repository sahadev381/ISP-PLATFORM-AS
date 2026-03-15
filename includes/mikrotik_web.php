<?php
/**
 * Mikrotik Web Monitor
 * Uses Web Interface to get system info and stats
 */

class MikrotikWeb {
    private $host;
    private $user;
    private $pass;
    private $cookieFile;
    
    public function __construct($host, $user, $pass) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->cookieFile = sys_get_temp_dir() . '/mikrotik_' . md5($host) . '.txt';
    }
    
    /**
     * Login to Mikrotik Web
     */
    public function login() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->host}/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "username={$this->user}&password={$this->pass}");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return file_exists($this->cookieFile);
    }
    
    /**
     * Get system resource data from WebFig
     */
    public function getSystemInfo() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->host}/rest/tool/system/resource/print");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $result) {
            $data = json_decode($result, true);
            if ($data && isset($data[0])) {
                return $data[0];
            }
        }
        
        return null;
    }
    
    /**
     * Get interface statistics
     */
    public function getInterfaces() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->host}/rest/interface/print");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        if ($result) {
            return json_decode($result, true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Get active PPPoE connections
     */
    public function getActivePPP() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->host}/rest/ppp/active/print");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        if ($result) {
            return json_decode($result, true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Get PPPoE secrets
     */
    public function getSecrets() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->host}/rest/ppp/secret/print");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        if ($result) {
            return json_decode($result, true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Get DHCP leases
     */
    public function getDhcpLeases() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->host}/rest/ip/dhcp-server/lease/print");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        if ($result) {
            return json_decode($result, true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Get all data
     */
    public function getAll() {
        return [
            'system' => $this->getSystemInfo(),
            'interfaces' => $this->getInterfaces(),
            'ppp_active' => $this->getActivePPP(),
            'ppp_secrets' => $this->getSecrets(),
            'dhcp_leases' => $this->getDhcpLeases()
        ];
    }
    
    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }
}
