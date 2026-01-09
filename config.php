<?php
// Auto-detect base URL from request headers
function detectBaseUrl() {
    // First, check if BASE_URL is explicitly set in environment
    $envUrl = getenv('BASE_URL');
    if (!empty($envUrl)) {
        return rtrim($envUrl, '/') . '/';
    }
    
    // If $_SERVER is not available (CLI context), fall back to default
    if (!isset($_SERVER) || empty($_SERVER)) {
        return 'http://localhost:8080/';
    }
    
    // Detect from request headers (works for both localhost and network access)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
              (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) 
              ? 'https' : 'http';
    
    $host = $_SERVER['HTTP_HOST'] ?? 
            $_SERVER['SERVER_NAME'] ?? 
            $_SERVER['SERVER_ADDR'] ?? 
            'localhost';
    
    $port = '';
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
        // Only include port if it's not the default and not already in host
        if (strpos($host, ':') === false) {
            $port = ':' . $_SERVER['SERVER_PORT'];
        }
    }
    
    return $scheme . '://' . $host . $port . '/';
}

return [
    'BASE_URL' => detectBaseUrl(),
    'LOG_DIRECTORY' => __DIR__ . '/logs', // Automatically resolves to the correct path in Docker
];
