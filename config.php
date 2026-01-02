<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "attendance_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session
session_start();
?>