<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;

require_once __DIR__ . '/../includes/env.php';

/* Credentials come from .env — see .env.example */
define('TWILIO_SID', (string) env('TWILIO_SID', ''));
define('TWILIO_TOKEN', (string) env('TWILIO_TOKEN', ''));

/* WHATSAPP SETTINGS */
define('TWILIO_FROM', (string) env('TWILIO_FROM', ''));
define('ALERT_TO', (string) env('ALERT_TO', ''));

function sendWhatsApp($message) {
    if (TWILIO_SID === '' || TWILIO_TOKEN === '' || ALERT_TO === '') {
        error_log('Twilio not configured — set TWILIO_* in .env');
        return;
    }

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

