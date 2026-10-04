<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

function logAction($action, $user_id = null, $user_name = null, $data = null) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/actions_' . date('Y-m-d') . '.json';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'unix_timestamp' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $user_id ?? 'guest',
        'user_name' => $user_name ?? 'guest',
        'action' => $action,
        'data' => $data,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ];
    
    $existing_logs = [];
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        if (!empty($content)) {
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $existing_logs[] = json_decode($line, true);
                }
            }
        }
    }
    
    $existing_logs[] = $log_entry;
    $json_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    
    file_put_contents($log_file, $json_line, FILE_APPEND | LOCK_EX);
}

function logError($error_message, $file = null, $line = null) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/errors_' . date('Y-m-d') . '.json';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'unix_timestamp' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'user_name' => $_SESSION['user_name'] ?? 'guest',
        'error' => $error_message,
        'file' => $file,
        'line' => $line,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
    ];
    
    $json_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    
    file_put_contents($log_file, $json_line, FILE_APPEND | LOCK_EX);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("PHP Error [$errno]: $errstr", $errfile, $errline);
    return true;
});

set_exception_handler(function($exception) {
    logError("Uncaught Exception: " . $exception->getMessage(), $exception->getFile(), $exception->getLine());
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        logError("Fatal Error: " . $error['message'], $error['file'], $error['line']);
    }
});
