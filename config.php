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
    
    // Determine protocol
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    
    // Get host from HTTP_HOST or fallback to SERVER_NAME
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    
    // Get script directory path
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    // Ensure script path ends with / if not root
    if ($scriptPath !== '/' && $scriptPath !== '\\') {
        $scriptPath = rtrim($scriptPath, '/') . '/';
    } else {
        $scriptPath = '/';
    }
    
    return $protocol . "://" . $host . $scriptPath;
}

return [
    'BASE_URL' => detectBaseUrl(),
    'LOG_DIRECTORY' => __DIR__ . '/logs', // Automatically resolves to the correct path in Docker
];
