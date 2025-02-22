<?php

/**
 * Simple logging function that writes messages to a daily log file
 * @param string $message The message to log
 * @param string $level The log level (info, error, debug)
 */
function log_message($message, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../../logs/' . date('Y-m-d') . '.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Format the log message
    $formattedMessage = sprintf(
        "[%s] [%s] %s\n",
        $timestamp,
        strtoupper($level),
        $message
    );
    
    // Append to log file
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
} 