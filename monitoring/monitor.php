<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
require 'check.php';
require 'twilio.php';

$devices = $conn->query("SELECT * FROM devices");


while ($d = $devices->fetch_assoc()) {

    $isUp = ($d['type'] === 'ping')
        ? pingDevice($d['ip_address'])
        : httpCheck($d['ip_address']);

    /* DEVICE FAILED */
    if (!$isUp) {

        $failCount = $d['fail_count'] + 1;

        // Mark DOWN only after 3 failures
        if ($failCount >= 3 && $d['status'] === 'UP') {

            $msg = " *DEVICE DOWN*\n"
                 . "Name: {$d['name']}\n"
                 . "IP: {$d['ip_address']}\n"
                 . "Time: " . date('Y-m-d H:i:s');

            sendWhatsApp($msg);

            $stmt = $conn->prepare(
                "UPDATE devices 
                 SET status='DOWN', fail_count=?, last_checked=NOW() 
                 WHERE id=?"
            );
            $stmt->bind_param("ii", $failCount, $d['id']);
            $stmt->execute();

        } else {
            // Just increase fail count
            $stmt = $conn->prepare(
                "UPDATE devices 
                 SET fail_count=?, last_checked=NOW() 
                 WHERE id=?"
            );
            $stmt->bind_param("ii", $failCount, $d['id']);
            $stmt->execute();
        }

    }
    /* DEVICE IS UP */
    else {

        // Send recovery alert only if previously DOWN
        if ($d['status'] === 'DOWN') {

            $msg = " *DEVICE RECOVERED*\n"
                 . "Name: {$d['name']}\n"
                 . "IP: {$d['ip_address']}\n"
                 . "Time: " . date('Y-m-d H:i:s');

            sendWhatsApp($msg);
        }

        // Reset fail count
        $stmt = $conn->prepare(
            "UPDATE devices 
             SET status='UP', fail_count=0, last_checked=NOW() 
             WHERE id=?"
        );
        $stmt->bind_param("i", $d['id']);
        $stmt->execute();
    }
}

