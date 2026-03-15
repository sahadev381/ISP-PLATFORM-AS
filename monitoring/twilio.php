<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;

define('TWILIO_SID', 'ACb924db5c3bf9dacee7c28a83513ce907');
define('TWILIO_TOKEN', '587e2db2539e5a1991ea4aa2f83c7dbc');

/* WHATSAPP SETTINGS */
define('TWILIO_FROM', 'whatsapp:+14155238886'); // Twilio sandbox
define('ALERT_TO', 'whatsapp:+9779801116703');  // Your WhatsApp number

function sendWhatsApp($message) {
    try {
        $client = new Client(TWILIO_SID, TWILIO_TOKEN);
        $client->messages->create(
            ALERT_TO,
            [
                'from' => TWILIO_FROM,
                'body' => $message
            ]
        );
    } catch (Exception $e) {
        error_log("Twilio WhatsApp Error: " . $e->getMessage());
    }
}

