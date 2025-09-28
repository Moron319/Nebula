<?php
// Database connection parameters
$servername = "localhost"; // Database server name
$username = "root";        // Database username
$password = "";            // Database password (empty if not set)
$dbname = "mybasesql";     // Name of the database to connect to

try {
    // Create a new PDO instance to connect to MySQL with the specified parameters
    // DSN format: mysql:host=server_address;dbname=database_name
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    
    // Set the error mode to Exception to handle errors effectively
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Catch any connection errors and display the error message
    echo "Connection failed: " . $e->getMessage();
}
?>
