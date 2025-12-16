<?php
function writeLog($message, $data = null) {
    $logFile = __DIR__ . '/debug_purchases.log';
    $timestamp = date('Y-m-d H:i:s');
    
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= " | Data: " . print_r($data, true);
    }
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>
