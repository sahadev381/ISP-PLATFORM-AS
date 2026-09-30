<?php
/**
 * Khalti payment gateway settings.
 * Values come from .env — see .env.example.
 */
require_once __DIR__ . '/../includes/env.php';

define('KHALTI_PUBLIC_KEY', (string) env('KHALTI_PUBLIC_KEY', ''));
define('KHALTI_SECRET_KEY', (string) env('KHALTI_SECRET_KEY', ''));
define('KHALTI_VERIFY_URL', (string) env('KHALTI_VERIFY_URL', 'https://khalti.com/api/v2/payment/verify/'));
