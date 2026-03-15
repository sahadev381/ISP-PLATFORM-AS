<?php
/**
 * BDCOM OLT SNMP Monitor
 * Uses snmpwalk/snmpget commands to query BDCOM OLT
 */

class BDCOM_SNMP {
    private $host;
    private $community;
    private $timeout = 10;
    
    public function __construct($host, $community = 'public') {
        $this->host = $host;
        $this->community = $community;
    }
    
    private function snmpget($oid) {
        $cmd = sprintf("snmpget -v 2c -c '%s' -t %d -Ov %s %s 2>/dev/null", 
            escapeshellarg($this->community),
            $this->timeout,
            $this->host,
            $oid
        );
        $output = shell_exec($cmd);
        if ($output) {
            $output = str_replace("STRING: ", "", $output);
            $output = str_replace("INTEGER: ", "", $output);
            $output = str_replace("Timeticks: ", "", $output);
            $output = trim($output);
            $output = trim($output, '"');
        }
        return $output;
    }
    
    private function snmpwalk($oid) {
        $cmd = sprintf("snmpwalk -v 2c -c '%s' -t %d -Os %s %s 2>/dev/null", 
            escapeshellarg($this->community),
            $this->timeout,
            $this->host,
            $oid
        );
        $output = shell_exec($cmd);
        $results = [];
        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (preg_match('/^(\S+)\s*=\s*(.*)$/', $line, $matches)) {
                    $results[$matches[1]] = str_replace(['STRING: ', 'INTEGER: '], '', $matches[2]);
                }
            }
        }
        return $results;
    }
    
    public function getSystemInfo() {
        $info = [];
        
        $info['sysDescr'] = $this->snmpget('sysDescr.0');
        $info['sysUpTime'] = $this->snmpget('sysUpTime.0');
        $info['sysName'] = $this->snmpget('sysName.0');
        $info['sysLocation'] = $this->snmpget('sysLocation.0');
        $info['sysContact'] = $this->snmpget('sysContact.0');
        
        return $info;
    }
    
    public function getPonPorts() {
        $ports = [];
        
        // BDCOM GPON OLT PON port status OIDs
        // This is example OID - may need adjustment based on OLT firmware
        $portOid = '1.3.6.1.4.1.6321.1.2.2.1.3'; // Example BDCOM OID
        
        $walk = $this->snmpwalk($portOid);
        
        // If no data, try alternative OIDs
        if (empty($walk)) {
            // Try generic interface OIDs
            $ifDescr = $this->snmpwalk('ifDescr');
            $ifOperStatus = $this->snmpwalk('ifOperStatus');
            
            foreach ($ifDescr as $idx => $desc) {
                if (stripos($desc, 'PON') !== false || stripos($desc, 'Gpon') !== false) {
                    $ports[] = [
                        'index' => $idx,
                        'description' => $desc,
                        'status' => $ifOperStatus[$idx] ?? 'unknown'
                    ];
                }
            }
        }
        
        return $ports;
    }
    
    public function getOnus() {
        $onus = [];
        
        // ONT/ONU OIDs - these are example OIDs
        // BDCOM specific OIDs for ONUs
        $onuOid = '1.3.6.1.4.1.6321.1.2.4.1'; // Example
        
        $walk = $this->snmpwalk($onuOid);
        
        // Parse ONUs from walk results
        foreach ($walk as $oid => $value) {
            if (stripos($oid, 'onu') !== false || stripos($oid, 'ont') !== false) {
                $onus[$oid] = $value;
            }
        }
        
        return $onus;
    }
    
    public function getOnuOpticalPower($onuSerial = null) {
        $power = [];
        
        // Optical power OIDs
        $rxOid = '1.3.6.1.4.1.6321.1.2.5.1.10'; // Example RX power OID
        $txOid = '1.3.6.1.4.1.6321.1.2.5.1.11'; // Example TX power OID
        
        $rxWalk = $this->snmpwalk($rxOid);
        $txWalk = $this->snmpwalk($txOid);
        
        foreach ($rxWalk as $idx => $rx) {
            $power[$idx] = [
                'rx' => $rx,
                'tx' => $txWalk[$idx] ?? 'N/A'
            ];
        }
        
        return $power;
    }
    
    public function getAll() {
        return [
            'system' => $this->getSystemInfo(),
            'pon_ports' => $this->getPonPorts(),
            'onus' => $this->getOnus(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

// Test endpoint
if ((php_sapi_name() === 'cli' && isset($_GET['test'])) || (php_sapi_name() !== 'cli' && isset($_GET['test']))) {
    header('Content-Type: application/json');
    
    $host = $_GET['host'] ?? '10.10.90.56';
    $community = $_GET['community'] ?? 'mct@net';
    
    $snmp = new BDCOM_SNMP($host, $community);
    
    try {
        $data = $snmp->getAll();
        echo json_encode($data, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }
}
