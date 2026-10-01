<?php
require_once __DIR__ . '/includes/session.php';
session_boot();
session_kill();
header("Location: /index.php");
exit;

