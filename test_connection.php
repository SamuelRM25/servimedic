<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing Database Connection...\n";

require_once 'config/database.php';

try {
    $db = new Database();
    echo "Database instance created.\n";
    
    $start = microtime(true);
    $conn = $db->getConnection();
    $end = microtime(true);
    
    echo "Connection successful!\n";
    echo "Time taken: " . round($end - $start, 4) . " seconds.\n";
    
    $stmt = $conn->query("SELECT @@version");
    $version = $stmt->fetchColumn();
    echo "MySQL Version: " . $version . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), '2002') !== false) {
        echo "\nPOSSIBLE CAUSES:\n";
        echo "1. Firewall blocking port 20926.\n";
        echo "2. MAMP not allowing outbound connections on this port.\n";
        echo "3. No internet connection.\n";
        echo "4. Hostname resolution failed.\n";
    }
}
?>
