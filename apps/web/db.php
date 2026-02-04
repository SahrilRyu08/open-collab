<?php
// Database connection configuration
$host_nya = 'localhost';
$user_nya = 'root';
$pass_nya = '';
$nama_db_nya = 'opencollab_music';

// Create mysqli connection
$conn = new mysqli($host_nya, $user_nya, $pass_nya, $nama_db_nya);

// Fail fast if connection error
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
