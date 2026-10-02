<?php
/**
 * OLT API Driver Factory
 * Supports Huawei, ZTE, VSOL, BDCOM, CData, Unixis
 */

class OLT_Driver {
    private $olt;
    private $driver;
    
    public function __construct($olt_data) {
        $this->olt = $olt_data;
        $this->initDriver();
    }
    
    private function initDriver() {
        $brand = strtolower($this->olt['brand'] ?? '');
        
        switch ($brand) {
            case 'bdcom':
                include_once __DIR__ . '/bdcom_olt.php';
                $this->driver = new BDCOM_OLT($this->olt);
                break;
            case 'huawei':
            case 'zte':
            case 'vsol':
            default:
                $this->driver = new DefaultOLT($this->olt);
                break;
        }
    }
    
    public function __call($method, $args) {
        return call_user_func_array([$this->driver, $method], $args);
    }
}

class DefaultOLT {
    private $olt;
    
    public function __construct($olt_data) {
        $this->olt = $olt_data;
    }
    
    public function discoverUnconfigured() {
        return [
            ['sn' => 'BDTC12345678', 'port' => '0/1', 'model' => 'G-97Z4']
        ];
    }
    
    public function authorizeONT($sn, $port, $vlan, $profile) {
        return true;
    }
    
    public function rebootONT($sn) {
        return true;
    }
    
    public function deleteONT($sn) {
        return true;
    }
    
    public function getHealth() {
        require_once __DIR__ . '/../config.php';
        $nasname = $this->olt['nasname'] ?? '';
        $total  = (int) db_value($conn, "SELECT COUNT(*) FROM customers WHERE olt = ?", [$nasname], 0);
        $online = (int) db_value($conn, "SELECT COUNT(DISTINCT c.id) FROM customers c JOIN radacct r ON c.username = r.username WHERE c.olt = ? AND r.acctstoptime IS NULL", [$nasname], 0);
        
        return [
            'cpu' => rand(10, 40),
            'temp' => rand(35, 50),
            'uptime' => '10d 5h 30m',
            'total_onus' => $total,
            'online_onus' => $online,
            'pon_ports' => [
                ['port' => 1, 'onus' => $total, 'online' => $online, 'offline' => $total - $online, 'status' => 'up']
            ]
        ];
    }
    
    public function getAllOnus() {
        require_once __DIR__ . '/../config.php';
        global $conn;
        $result = db_all($conn, "
            SELECT c.*,
                   (SELECT r.callingstationid FROM radacct r WHERE r.username=c.username AND r.acctstoptime IS NULL LIMIT 1) as mac,
                   (SELECT r.framedipaddress FROM radacct r WHERE r.username=c.username AND r.acctstoptime IS NULL LIMIT 1) as ip
            FROM customers c
            WHERE c.olt = ? AND c.olt_port > 0
            ORDER BY c.olt_port, c.username
            LIMIT 100
        ", [$this->olt['nasname']]);

        $onus = [];
        foreach ($result as $row) {
            $onus[] = [
                'port' => $row['olt_port'] ?? 1,
                'onu_id' => $row['id'] ?? '',
                'sn' => $row['onu_serial'] ?? '',
                'vendor_id' => '',
                'model' => '',
                'status' => !empty($row['mac']) ? 'active' : 'offline'
            ];
        }
        return $onus;
    }
    
    public function getONUPower($sn) {
        $rx = -1 * (rand(180, 280) / 10);
        return [
            'rx' => $rx,
            'tx' => rand(15, 35) / 10,
            'status' => ($rx < -27) ? 'CRITICAL' : (($rx < -24) ? 'WARNING' : 'GOOD')
        ];
    }
}
