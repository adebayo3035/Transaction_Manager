<?php
function logActivity($message) {
    $logFile = __DIR__ . '/activity_log.txt'; // Log file in the same directory as the script
    $timestamp = date('Y-m-d H:i:s');

    // Get the current page URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    $currentUrl = $protocol . $host . $uri;

    // Append the URL and message to the log
    $logMessage = "[$timestamp] [Page: $currentUrl] $message" . PHP_EOL;

    // Append the log message to the log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}