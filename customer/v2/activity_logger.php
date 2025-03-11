<?php
function logActivity($message) {
    $logFile = __DIR__ . '/activity_log.txt'; // Log file in the same directory as the script
    $timestamp = date('Y-m-d H:i:s');

    // Get the current page URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    $currentUrl = $protocol . $host . $uri;

    // Get the file and line number where logActivity was called
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller = $backtrace[0]; // Get the first element of the backtrace
    $file = $caller['file']; // File where logActivity was called
    $line = $caller['line']; // Line number where logActivity was called

    // Append the URL, file, line number, and message to the log
    $logMessage = "[$timestamp] [Page: $currentUrl] [File: $file, Line: $line] $message" . PHP_EOL;

    // Append the log message to the log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}