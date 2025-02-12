<?php

class LogService {
    private static $instance = null;
    private $logFile;
    private $config;
    
    private function __construct($config) {
        $this->config = $config;
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/app.log';
    }
    
    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new RuntimeException('Config must be provided for first instantiation');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
    
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_PRETTY_PRINT);
        $logMessage = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        
        // Also log to error_log for development
        if ($this->config['app']['environment'] === 'development') {
            error_log($logMessage);
        }
    }
    
    public function clear() {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
    
    public function getLogPath() {
        return $this->logFile;
    }
} 