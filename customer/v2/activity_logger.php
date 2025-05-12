<?php
function logActivity($message) {
    $logFile = __DIR__ . '/activity_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    // Get request information
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    // Get caller information
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller = $backtrace[0];
    $file = basename($caller['file']); // Just show filename without full path
    $line = $caller['line'];
    
    // Format the log entry with proper spacing
    $logMessage = sprintf(
        "[%s]\n" .
        "URL: %s\n" .
        "Source: %s (Line %d)\n" .
        "Message: %s\n" .
        "----------------------------------------\n\n",
        $timestamp,
        $currentUrl,
        $file,
        $line,
        trim($message)
    );
    
    // Write to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}