<?php
/**
 * BDCOM OLT Telnet/SSH Driver
 * Connects to BDCOM OLT via Telnet to execute commands
 */

class BDCOM_Telnet {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $timeout = 10;
    private $socket;
    private $debug = false;
    
    public function __construct($host, $user = 'admin', $pass = 'admin', $port = 23) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }
    
    public function connect() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$this->socket) {
            throw new Exception("Cannot connect to {$this->host}: {$errstr}");
        }
        
        stream_set_timeout($this->socket, $this->timeout);
        
        // Wait for login prompt
        $this->readUntil(['login:', 'username:', 'Login:']);
        
        // Send username
        $this->write($this->user . "\r\n");
        $this->readUntil(['password:', 'Password:']);
        
        // Send password
        $this->write($this->pass . "\r\n");
        
        // Wait for initial prompt
        $output = $this->readUntil(['#', '>', '$', 'MLH_GPON']);
        
        // Try to enter enable mode (some OLTs need this)
        $this->write("enable\r\n");
        $this->readUntil(['#', '>', '$', 'MLH_GPON']);
        
        return true;
    }
    
    public function disconnect() {
        if ($this->socket) {
            $this->write("exit\r\n");
            fclose($this->socket);
            $this->socket = null;
        }
    }
    
    private function write($data) {
        fwrite($this->socket, $data);
    }
    
    private function readUntil($prompts, $timeout = 10) {
        $output = '';
        $start = time();
        
        while (time() - $start < $timeout) {
            $read = fread($this->socket, 8192);
            if ($read) {
                $output .= $read;
            }
            
            foreach ($prompts as $prompt) {
                if (stripos($output, $prompt) !== false) {
                    return $output;
                }
            }
            
            if (feof($this->socket)) {
                break;
            }
            
            usleep(200000);
        }
        
        return $output;
    }
    
    public function execute($command, $timeout = 10) {
        $this->write($command . "\r\n");
        
        $output = '';
        $maxPages = 2; // Limit pages for speed
        $pageCount = 0;
        
        while ($pageCount < $maxPages) {
            $chunk = $this->readUntil(['#', '>', '$', 'MLH_GPON'], $timeout);
            $output .= $chunk;
            
            // Check if there's more data
            if (stripos($chunk, '--More--') === false && stripos($chunk, 'more') === false) {
                break;
            }
            
            // Send space to continue
            $this->write(" \r\n");
            $pageCount++;
        }
        
        return $output;
    }
    
    public function getSystemInfo() {
        $output = $this->execute('show version');
        
        $info = [];
        if (preg_match('/Model\s*[:=]?\s*(.+)/i', $output, $m)) $info['model'] = trim($m[1]);
        if (preg_match('/Software\s*[:=]?\s*(.+)/i', $output, $m)) $info['software'] = trim($m[1]);
        if (preg_match('/Uptime/i', $output, $m)) {
            preg_match('/Uptime.*?(\d+)d\s*(\d+)h/i', $output, $um);
            if ($um) $info['uptime'] = $um[1] . 'd ' . $um[2] . 'h';
        }
        
        // Get CPU
        $cpu = $this->execute('show cpu');
        if (preg_match('/(\d+)%/i', $cpu, $cm)) {
            $info['cpu'] = $cm[1];
        }
        
        return $info;
    }
    
    public function getPonPorts() {
        $ports = [];
        
        // Get summary of ONUs per port
        $output = $this->execute('show gpon onu-status');
        
        // Parse summary lines
        $stats = [];
        if (preg_match('/Offline:\s*(\d+)/', $output, $m)) $stats['offline'] = (int)$m[1];
        if (preg_match('/Active:\s*(\d+)/', $output, $m)) $stats['active'] = (int)$m[1];
        if (preg_match('/Disable:\s*(\d+)/', $output, $m)) $stats['disabled'] = (int)$m[1];
        
        // Return single aggregated port since this OLT shows all PON ports together
        $ports[] = [
            'port' => 1,
            'onus' => ($stats['offline'] ?? 0) + ($stats['active'] ?? 0) + ($stats['disabled'] ?? 0),
            'online' => $stats['active'] ?? 0,
            'offline' => $stats['offline'] ?? 0,
            'disabled' => $stats['disabled'] ?? 0,
            'status' => ($stats['active'] ?? 0) > 0 ? 'up' : 'down'
        ];
        
        return $ports;
    }
    
    public function getOnus() {
        $onus = [];
        
        // Get ONUs from the OLT - get first 2 pages for speed
        $output = $this->execute('show gpon onu-information');
        
        // Parse ONUs
        // GPON0/1:1      HWTC      HG8145V5     HWTC:5F593DA5    N/A  active   success       2026-03-14 16:30:32
        preg_match_all('/GPON0\/(\d+):(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/', $output, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $m) {
            $onus[] = [
                'interface' => 'GPON0/' . $m[1],
                'port' => (int)$m[1],
                'onu_id' => (int)$m[2],
                'vendor_id' => $m[3],
                'model' => $m[4],
                'sn' => $m[5],
                'loid' => $m[6],
                'status' => $m[7],
                'config_status' => $m[8]
            ];
        }
        
        return $onus;
    }
    
    public function getOnuOpticalPower($onuSerial = null) {
        $power = [];
        
        // Try different commands to get optical power
        $commands = [
            'show gpon onu optical al',
            'show gpon onu optical',
            'show onu optical'
        ];
        
        foreach ($commands as $cmd) {
            $output = $this->execute($cmd);
            
            // Try to parse RX power from output
            // Look for patterns like: -21.5 dBm or -22.34
            if (preg_match_all('/([-\d.]+)\s*dBm?/i', $output, $rxMatches)) {
                $power['rx'] = $rxMatches[1];
                return $power;
            }
        }
        
        return $power;
    }
    
    public function getOnuOpticalPowerByPort($port, $onuId) {
        // Try to get optical power for specific ONT
        // Different OLTs have different command formats
        $commands = [
            "show gpon onu onu-optical-transceiver-diagnosis interface 0/{$port} onu {$onuId}",
            "show pon onu optical {$port} {$onuId}",
            "show ont optical {$port}/{$onuId}"
        ];
        
        foreach ($commands as $cmd) {
            $output = $this->execute($cmd);
            
            // Parse RX power
            if (preg_match('/RX\s*[:=]?\s*([-\d.]+)/i', $output, $m)) {
                return [
                    'rx' => floatval($m[1]),
                    'tx' => null,
                    'raw' => $output
                ];
            }
            
            // Try alternative pattern
            if (preg_match('/(-?\d+\.?\d*)\s*dBm/i', $output, $m)) {
                return [
                    'rx' => floatval($m[1]),
                    'tx' => null,
                    'raw' => $output
                ];
            }
        }
        
        return null;
    }
    
    public function getOnuInfoByPort($port) {
        $onus = [];
        
        // Get all ONUs and filter by port
        $allOnus = $this->getOnus();
        
        foreach ($allOnus as $onu) {
            if ($onu['port'] == $port) {
                $onus[] = $onu;
            }
        }
        
        return $onus;
    }
    
    public function rebootOnu($onuSerial) {
        // Common commands for rebooting ONT
        $cmds = [
            "ont reset {$onuSerial}",
            "gpon onu reset {$onuSerial}",
            "onu reset {$onuSerial}"
        ];
        
        foreach ($cmds as $cmd) {
            $output = $this->execute($cmd);
            if (stripos($output, 'success') !== false || stripos($output, 'ok') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function deleteOnu($onuSerial) {
        // Common commands for deleting ONT
        $cmds = [
            "no ont {$onuSerial}",
            "ont delete {$onuSerial}",
            "gpon onu delete {$onuSerial}"
        ];
        
        foreach ($cmds as $cmd) {
            $output = $this->execute($cmd);
            if (stripos($output, 'success') !== false || stripos($output, 'ok') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function authorizeOnu($onuSerial, $port, $vlan, $profile = 'default') {
        // Common commands for authorizing ONT
        $cmds = [
            "ont add {$onuSerial} {$port} vlan {$vlan}",
            "gpon onu add {$onuSerial} {$port} vlan {$vlan}",
            "onu provision {$onuSerial} {$port} vlan {$vlan}"
        ];
        
        foreach ($cmds as $cmd) {
            $output = $this->execute($cmd);
            if (stripos($output, 'success') !== false || stripos($output, 'ok') !== false) {
                return true;
            }
        }
        
        return false;
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

// CLI test
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $host = $argv[1];
    $user = $argv[2] ?? 'admin';
    $pass = $argv[3] ?? 'admin';
    
    echo "Connecting to BDCOM OLT at $host...\n";
    
    try {
        $olt = new BDCOM_Telnet($host, $user, $pass);
        $olt->connect();
        
        echo "Connected! Getting system info...\n";
        print_r($olt->getSystemInfo());
        
        echo "\nGetting PON ports...\n";
        print_r($olt->getPonPorts());
        
        echo "\nGetting ONUs...\n";
        print_r($olt->getOnus());
        
        $olt->disconnect();
        echo "\nDone.\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
