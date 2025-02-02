<?php
function logActivity($message) {
    $logFile = __DIR__ . '/activity_log.txt'; // Log file in the same directory as the script
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Append the log message to the log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}