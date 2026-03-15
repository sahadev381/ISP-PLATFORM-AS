<?php
/**
 * Mikrotik SNMP Monitor
 * Queries Mikrotik via SNMP to get system info, interfaces, and traffic
 */

class MikrotikSNMP {
    private $host;
    private $community;
    private $timeout = 5;
    
    public function __construct($host, $community = 'public') {
        $this->host = $host;
        $this->community = $community;
    }
    
    /**
     * Get system resources via SNMP
     */
    public function getSystemInfo() {
        $info = [];
        
        // System description
        $desc = @snmpget($this->host, $this->community, 'sysDescr.0', $this->timeout * 1000000);
        $info['description'] = str_replace('STRING: ', '', $desc ?? '');
        
        // System uptime
        $uptime = @snmpget($this->host, $this->community, 'sysUpTime.0', $this->timeout * 1000000);
        $info['uptime'] = str_replace('STRING: ', '', $uptime ?? '');
        
        // System contact
        $contact = @snmpget($this->host, $this->community, 'sysContact.0', $this->timeout * 1000000);
        $info['contact'] = str_replace('STRING: ', '', $contact ?? '');
        
        // System name
        $name = @snmpget($this->host, $this->community, 'sysName.0', $this->timeout * 1000000);
        $info['name'] = str_replace('STRING: ', '', $name ?? '');
        
        // System location
        $location = @snmpget($this->host, $this->community, 'sysLocation.0', $this->timeout * 1000000);
        $info['location'] = str_replace('STRING: ', '', $location ?? '');
        
        return $info;
    }
    
    /**
     * Get interface list
     */
    public function getInterfaces() {
        $interfaces = [];
        
        // Get interface indexes
        $ifDescr = @snmpwalk($this->host, $this->community, 'ifDescr', $this->timeout * 1000000);
        
        if ($ifDescr) {
            foreach ($ifDescr as $index => $desc) {
                $desc = str_replace('STRING: ', '', $desc);
                $ifType = @snmpget($this->host, $this->community, "ifType.$index", $this->timeout * 1000000);
                $ifType = str_replace('INTEGER: ', '', $ifType ?? '');
                
                $ifSpeed = @snmpget($this->host, $this->community, "ifSpeed.$index", $this->timeout * 1000000);
                $ifSpeed = str_replace('Gauge32: ', '', $ifSpeed ?? '');
                
                $ifPhysAddress = @snmpget($this->host, $this->community, "ifPhysAddress.$index", $this->timeout * 1000000);
                $ifPhysAddress = str_replace('STRING: ', '', $ifPhysAddress ?? '');
                
                $ifAdminStatus = @snmpget($this->host, $this->community, "ifAdminStatus.$index", $this->timeout * 1000000);
                $ifAdminStatus = str_replace('INTEGER: ', '', $ifAdminStatus ?? '');
                
                $ifOperStatus = @snmpget($this->host, $this->community, "ifOperStatus.$index", $this->timeout * 1000000);
                $ifOperStatus = str_replace('INTEGER: ', '', $ifOperStatus ?? '');
                
                $interfaces[] = [
                    'index' => $index,
                    'description' => $desc,
                    'type' => $ifType,
                    'speed' => $ifSpeed,
                    'mac' => $ifPhysAddress,
                    'admin_status' => $ifAdminStatus,
                    'oper_status' => $ifOperStatus
                ];
            }
        }
        
        return $interfaces;
    }
    
    /**
     * Get traffic statistics for an interface
     */
    public function getInterfaceStats($ifIndex) {
        $stats = [];
        
        // In/Out octets
        $inOctets = @snmpget($this->host, $this->community, "ifInOctets.$ifIndex", $this->timeout * 1000000);
        $stats['in_octets'] = str_replace('Counter32: ', '', $inOctets ?? '0');
        
        $outOctets = @snmpget($this->host, $this->community, "ifOutOctets.$ifIndex", $this->timeout * 1000000);
        $stats['out_octets'] = str_replace('Counter32: ', '', $outOctets ?? '0');
        
        // In/Out unicast packets
        $inUcast = @snmpget($this->host, $this->community, "ifInUcastPkts.$ifIndex", $this->timeout * 1000000);
        $stats['in_ucast_pkts'] = str_replace('Counter32: ', '', $inUcast ?? '0');
        
        $outUcast = @snmpget($this->host, $this->community, "ifOutUcastPkts.$ifIndex", $this->timeout * 1000000);
        $stats['out_ucast_pkts'] = str_replace('Counter32: ', '', $outUcast ?? '0');
        
        // Errors
        $inErrors = @snmpget($this->host, $this->community, "ifInErrors.$ifIndex", $this->timeout * 1000000);
        $stats['in_errors'] = str_replace('Counter32: ', '', $inErrors ?? '0');
        
        $outErrors = @snmpget($this->host, $this->community, "ifOutErrors.$ifIndex", $this->timeout * 1000000);
        $stats['out_errors'] = str_replace('Counter32: ', '', $outErrors ?? '0');
        
        return $stats;
    }
    
    /**
     * Get CPU load (Mikrotik specific)
     */
    public function getCpuLoad() {
        $cpu = @snmpwalk($this->host, $this->community, '1.3.6.1.4.1.14988.1.1.3.1', $this->timeout * 1000000);
        
        if ($cpu) {
            $loads = [];
            foreach ($cpu as $val) {
                $val = str_replace('INTEGER: ', '', $val);
                if (is_numeric($val) && $val < 100) {
                    $loads[] = $val;
                }
            }
            return !empty($loads) ? array_sum($loads) / count($loads) : 0;
        }
        return null;
    }
    
    /**
     * Get memory usage (Mikrotik specific)
     */
    public function getMemory() {
        $mem = @snmpwalk($this->host, $this->community, '1.3.6.1.4.1.14988.1.1.2.1', $this->timeout * 1000000);
        
        if ($mem && count($mem) >= 2) {
            $total = str_replace('INTEGER: ', '', array_values($mem)[0] ?? '0');
            $free = str_replace('INTEGER: ', '', array_values($mem)[1] ?? '0');
            
            if ($total > 0) {
                return [
                    'total' => $total * 1024, // Convert to bytes
                    'used' => ($total - $free) * 1024,
                    'free' => $free * 1024,
                    'percent' => round((($total - $free) / $total) * 100, 1)
                ];
            }
        }
        return null;
    }
    
    /**
     * Get active PPPoE connections (Mikrotik specific)
     */
    public function getActivePPPoE() {
        $active = @snmpwalk($this->host, $this->community, '1.3.6.1.4.1.14988.1.1.5.1.1', $this->timeout * 1000000);
        return $active ? count($active) : 0;
    }
    
    /**
     * Get all data in one call
     */
    public function getAll() {
        return [
            'system' => $this->getSystemInfo(),
            'cpu' => $this->getCpuLoad(),
            'memory' => $this->getMemory(),
            'pppoe_active' => $this->getActivePPPoE(),
            'interfaces' => $this->getInterfaces()
        ];
    }
}
