<?php
require_once __DIR__ . '/../config/genieacs.php';

function genieacs_request($endpoint, $method = "GET", $data = null) {
    $ch = curl_init(GENIEACS_URL . $endpoint);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, GENIEACS_USER . ":" . GENIEACS_PASS);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ["error" => curl_error($ch)];
    }

    curl_close($ch);
    return json_decode($response, true);
}
/**
 * Find GenieACS device ID (_id) by ONU serial
 */
function getDeviceIdBySerial($serial) {
    $devices = genieacs_request("devices"); // GET /devices
    if (!is_array($devices)) return null;

    foreach ($devices as $device) {
        $deviceSerial = $device["InternetGatewayDevice"]["DeviceInfo"]["SerialNumber"]["_value"] ?? '';
        if ($deviceSerial === $serial) {
            return $device["_id"] ?? null;
        }
    }
    return null;
}

/**
 * Push TR-069 parameters to a device
 * @param string $deviceId GenieACS device _id
 * @param array $parameters Associative array ["Parameter.Name" => value, ...]
 * @return bool|string True if success, or error message
 */
function pushToDevice($deviceId, $parameters) {
    $paramArray = [];
    foreach ($parameters as $param => $value) {
        $paramArray[] = [$param, $value, "xsd:string"];
    }

    $payload = [
        "name" => "setParameterValues",
        "parameterValues" => $paramArray
    ];

    $response = genieacs_request("devices/$deviceId/tasks", "POST", $payload);

    if (isset($response["error"])) {
        return "ACS Error: " . $response["error"];
    }

    return true;
}

