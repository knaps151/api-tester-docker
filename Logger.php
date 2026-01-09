<?php

/**
 * Logger class for handling inbound and outbound request logging
 * Consolidates logging logic to follow DRY principles
 */
class Logger {
    
    /**
     * Log an outbound API request
     * 
     * @param array $config Configuration array with LOG_DIRECTORY and BASE_URL
     * @param string $endpoint The endpoint URL that was called
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $headers Request headers
     * @param string $payload Request payload/body
     * @param int $responseStatus HTTP response status code
     * @param string $responseHeaders Response headers
     * @param string $responseBody Response body
     * @param string|null $customIdentifier Optional custom identifier for the log
     * @return void
     */
    public static function logOutboundRequest($config, $endpoint, $method, $headers, $payload, $responseStatus, $responseHeaders, $responseBody, $customIdentifier = null) {
        $logDirectory = $config['LOG_DIRECTORY'];
        
        // Ensure log directory exists
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0755, true);
        }
        
        // Generate identifier from custom identifier, query param, endpoint, or use default
        $identifier = 'outbound';
        if (!empty($customIdentifier)) {
            $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $customIdentifier);
        } elseif (isset($_GET['id'])) {
            $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
        } else {
            // Try to extract identifier from endpoint domain
            $parsedUrl = parse_url($endpoint);
            if (isset($parsedUrl['host'])) {
                $hostParts = explode('.', $parsedUrl['host']);
                if (count($hostParts) > 0) {
                    $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $hostParts[0]);
                }
            }
        }
        
        // Generate timestamp and unique ID
        $timestamp = date('Y-m-d H:i:s');
        $datePart = date('Y-m-d');
        $timePart = date('H-i-s');
        $uniqueId = substr(uniqid(), -12);
        
        // Create log entry
        $logEntry = [
            'timestamp' => $timestamp,
            'type' => 'outbound',
            'request' => [
                'method' => $method,
                'endpoint' => $endpoint,
                'headers' => $headers,
                'payload' => $payload
            ],
            'response' => [
                'status' => $responseStatus,
                'headers' => $responseHeaders,
                'body' => $responseBody
            ],
            'server' => [
                'base_url' => $config['BASE_URL'],
                'client_identifier' => $identifier
            ]
        ];
        
        // Generate filename
        $filename = sprintf('%s_%s_%s_%s.json', $identifier, $datePart, $timePart, $uniqueId);
        $filePath = $logDirectory . '/' . $filename;
        
        // Write log file
        @file_put_contents($filePath, json_encode($logEntry, JSON_PRETTY_PRINT));
    }
    
    /**
     * Log an inbound request (webhook catcher)
     * 
     * @param array $config Configuration array with LOG_DIRECTORY and BASE_URL
     * @param array $requestData Request data array containing method, headers, body, query, ip, etc.
     * @return void
     */
    public static function logInboundRequest($config, $requestData) {
        $logDirectory = $config['LOG_DIRECTORY'];
        
        // Ensure log directory exists
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0755, true);
        }
        
        // Generate identifier from query param or use default
        $identifier = 'inbound';
        if (isset($_GET['id'])) {
            $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
        }
        
        // Generate timestamp and unique ID
        $timestamp = date('Y-m-d H:i:s');
        $datePart = date('Y-m-d');
        $timePart = date('H-i-s');
        $uniqueId = substr(uniqid(), -12);
        
        // Create log entry (similar format to outbound logs)
        $logEntry = [
            'timestamp' => $timestamp,
            'type' => 'inbound',
            'request' => [
                'method' => $requestData['method'],
                'uri' => $_SERVER['REQUEST_URI'] ?? '/catchall/api.php',
                'query_string' => http_build_query($requestData['query'] ?? []),
                'headers' => $requestData['headers'] ?? [],
                'body' => $requestData['body'] ?? ''
            ],
            'server' => [
                'base_url' => $config['BASE_URL'],
                'client_identifier' => $identifier,
                'ip' => $requestData['ip'] ?? 'unknown'
            ]
        ];
        
        // Generate filename
        $filename = sprintf('%s_%s_%s_%s.json', $identifier, $datePart, $timePart, $uniqueId);
        $filePath = $logDirectory . '/' . $filename;
        
        // Write log file
        @file_put_contents($filePath, json_encode($logEntry, JSON_PRETTY_PRINT));
    }
}

