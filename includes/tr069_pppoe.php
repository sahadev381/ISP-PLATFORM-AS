<?php
/* ======================================
   TR-069 MULTI-VENDOR PPPoE PROVISIONING
====================================== */

function genieacs($endpoint, $method = "GET", $data = null) {
    $ch = curl_init("http://103.90.144.133:7558" . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => $data ? json_encode($data) : null
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

/* ---------- Detect Vendor ---------- */
function detectVendor($device) {
    return strtoupper($device['InternetGatewayDevice']['DeviceInfo']['Manufacturer']['_value'] ?? '');
}

/* ---------- Find Free WANPPPConnection Slot ---------- */
function findFreePPPIndex($device) {
    $ppp = $device['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'] ?? [];
    foreach ($ppp as $wanDev) {
        if (!isset($wanDev['WANPPPConnection'])) {
            return 1;
        }
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($wanDev['WANPPPConnection'][$i])) {
                return $i;
            }
        }
    }
    return false;
}

/* ---------- Huawei Provision ---------- */
function provisionHuawei($deviceId, $device, $user, $pass, $vlan) {

    $idx = findFreePPPIndex($device);
    if ($idx === false) return "No free WANPPPConnection slot";

    // Create WANConnectionDevice
    genieacs("/devices/$deviceId/tasks", "POST", [
        "name" => "addObject",
        "objectName" => "InternetGatewayDevice.WANDevice.1.WANConnectionDevice."
    ]);

    sleep(2);

    $path = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.$idx";

    genieacs("/devices/$deviceId/tasks", "POST", [
        "name" => "addObject",
        "objectName" => "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection."
    ]);

    genieacs("/devices/$deviceId/tasks", "POST", [
        "name" => "setParameterValues",
        "parameterValues" => [
            ["$path.Username", $user, "xsd:string"],
            ["$path.Password", $pass, "xsd:string"],
            ["$path.X_HW_VLANID", (string)$vlan, "xsd:int"],
            ["$path.Enable", "true", "xsd:boolean"]
        ]
    ]);

    genieacs("/devices/$deviceId/tasks", "POST", ["name" => "reboot"]);

    return "WAN PPPoE CREATED\nInterface: WANPPPConnection.$idx\nVLAN: $vlan\nDevice rebooting";
}

/* ---------- ZTE Provision ---------- */
function provisionZTE($deviceId, $device, $user, $pass, $vlan) {

    $path = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1";

    genieacs("/devices/$deviceId/tasks", "POST", [
        "name" => "setParameterValues",
        "parameterValues" => [
            ["$path.Username", $user, "xsd:string"],
            ["$path.Password", $pass, "xsd:string"],
            ["$path.X_ZTE_VLANID", (string)$vlan, "xsd:int"],
            ["$path.Enable", "true", "xsd:boolean"]
        ]
    ]);

    genieacs("/devices/$deviceId/tasks", "POST", ["name" => "reboot"]);

    return "ZTE PPPoE configured with VLAN $vlan";
}

/* ---------- Generic / HSGQ ---------- */
function provisionGeneric($deviceId, $user, $pass) {

    genieacs("/devices/$deviceId/tasks", "POST", [
        "name" => "addObject",
        "objectName" => "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection."
    ]);

    genieacs("/devices/$deviceId/tasks", "POST", [
        "name" => "setParameterValues",
        "parameterValues" => [
            ["InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username", $user, "xsd:string"],
            ["InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password", $pass, "xsd:string"],
            ["InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Enable", "true", "xsd:boolean"]
        ]
    ]);

    return "Generic PPPoE pushed";
}

