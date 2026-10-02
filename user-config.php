<?php
/**
 * Legacy alias kept for older pages that include 'user-config.php'.
 *
 * Credentials used to be hardcoded here. They now live in .env and are
 * loaded by config.php — this file only forwards to the real bootstrap.
 */
require_once __DIR__ . '/config.php';
