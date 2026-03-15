<?php
$conn = new mysqli("localhost", "monitordb", "password", "monitoring");

if ($conn->connect_error) {
    die("Database connection failed");
}

