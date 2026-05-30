<?php
// 1. Force PHP to display errors on the screen (Prevents the blank white screen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Enable strict error reporting for MySQLi (Must be set BEFORE connecting)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// XAMPP settings
$servername = "127.0.0.1";    // Using 127.0.0.1 is safer than 'localhost' for custom ports
$username = "root";           // Default XAMPP username
$password = "";               // Default XAMPP password is empty
$dbname = "smart_auction_db"; // Your database name
$port = 3307;                 // YOUR CUSTOM XAMPP PORT!

try {
    // Create the connection (Including the port!)
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    
    // Set charset to support modern text/emojis perfectly
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // If the port or database name is wrong, it will stop here and tell you exactly why
    die("Database Connection Failed! Error: " . $e->getMessage());
}
?>