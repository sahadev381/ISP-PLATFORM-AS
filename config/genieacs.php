<?php
/**
 * GenieACS (TR-069) connection settings.
 * Values come from .env — see .env.example.
 */
require_once __DIR__ . '/../includes/env.php';

define("GENIEACS_URL",  (string) env('GENIEACS_URL', 'http://127.0.0.1:7557'));
define("GENIEACS_USER", (string) env('GENIEACS_USER', ''));
define("GENIEACS_PASS", (string) env('GENIEACS_PASS', ''));
