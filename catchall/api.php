<?php
// Load the configuration for logging
$config = require __DIR__ . '/../config.php';

// Set headers to allow all origins and methods
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST requests are allowed'
    ]);
    exit();
}

// File to store requests (shared across all clients)
$requestsFile = __DIR__ . '/requests.json';

// File to store current response template selection
$responseTemplateFile = __DIR__ . '/response_template.json';

// Function to log inbound requests to main logs directory
function logInboundRequest($config, $requestData) {
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
            'ip' => $requestData['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    ];
    
    // Generate filename
    $filename = sprintf('%s_%s_%s_%s.json', $identifier, $datePart, $timePart, $uniqueId);
    $filePath = $logDirectory . '/' . $filename;
    
    // Write log file
    @file_put_contents($filePath, json_encode($logEntry, JSON_PRETTY_PRINT));
}

// Function to read requests from file
function readRequests($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $requests = json_decode($content, true);
        return is_array($requests) ? $requests : [];
    }
    return [];
}

// Function to write requests to file
function writeRequests($file, $requests) {
    // Keep only the last 100 requests
    $requests = array_slice($requests, 0, 100);
    file_put_contents($file, json_encode($requests, JSON_PRETTY_PRINT));
    return $requests;
}

// Get request body to check for actions
$rawBody = file_get_contents('php://input');
$bodyData = json_decode($rawBody, true);

// Handle get_requests action (for fetching existing requests)
if (isset($bodyData['action']) && $bodyData['action'] === 'get_requests') {
    $requests = readRequests($requestsFile);
    echo json_encode([
        'status' => 'success',
        'requests' => $requests,
        'total_requests' => count($requests)
    ], JSON_PRETTY_PRINT);
    exit();
}

// Handle set_response_template action
if (isset($bodyData['action']) && $bodyData['action'] === 'set_response_template') {
    $templateName = isset($bodyData['template_name']) && !empty($bodyData['template_name']) 
        ? $bodyData['template_name'] 
        : null;
    file_put_contents($responseTemplateFile, json_encode(['template_name' => $templateName]));
    echo json_encode(['status' => 'success', 'message' => 'Response template updated']);
    exit();
}

// Handle clear action
if (isset($bodyData['action']) && $bodyData['action'] === 'clear') {
    writeRequests($requestsFile, []);
    echo json_encode(['status' => 'success', 'message' => 'Data cleared']);
    exit();
}

// Handle clear request via GET parameter (for backwards compatibility)
if (isset($_GET['clear'])) {
    writeRequests($requestsFile, []);
    echo json_encode(['status' => 'success', 'message' => 'Data cleared']);
    exit();
}

// Get the request data for new incoming requests
$requestData = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => $rawBody,
    'query' => $_GET,
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR']
];

// Try to parse JSON body
try {
    $requestData['parsed_body'] = json_decode($requestData['body'], true);
} catch (Exception $e) {
    $requestData['parsed_body'] = null;
}

// Read existing requests
$requests = readRequests($requestsFile);

// Add new request to the beginning of the array
array_unshift($requests, $requestData);

// Write back to file (this also limits to 100 requests)
$requests = writeRequests($requestsFile, $requests);

// Also log to main logs directory
logInboundRequest($config, $requestData);

// Check if a response template is set
$responseTemplateName = null;
if (file_exists($responseTemplateFile)) {
    $templateConfig = json_decode(file_get_contents($responseTemplateFile), true);
    $responseTemplateName = isset($templateConfig['template_name']) ? $templateConfig['template_name'] : null;
}

// If a response template is selected, use it
if ($responseTemplateName) {
    $templatesFile = __DIR__ . '/../templates.json';
    if (file_exists($templatesFile)) {
        $allTemplates = json_decode(file_get_contents($templatesFile), true);
        if (isset($allTemplates[$responseTemplateName]) && 
            isset($allTemplates[$responseTemplateName]['type']) && 
            $allTemplates[$responseTemplateName]['type'] === 'response_template') {
            
            $template = $allTemplates[$responseTemplateName];
            
            // Set HTTP status code if specified
            $statusCode = isset($template['status_code']) ? intval($template['status_code']) : 200;
            http_response_code($statusCode);
            
            // Set custom headers if specified
            if (isset($template['headers']) && is_array($template['headers'])) {
                foreach ($template['headers'] as $key => $value) {
                    header("$key: $value");
                }
            }
            
            // Return the response body (could be JSON string or object)
            $responseBody = isset($template['body']) ? $template['body'] : '';
            
            // If body is a string that looks like JSON, try to parse and re-encode it
            if (is_string($responseBody)) {
                $decoded = json_decode($responseBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo json_encode($decoded, JSON_PRETTY_PRINT);
                } else {
                    echo $responseBody;
                }
            } else {
                echo json_encode($responseBody, JSON_PRETTY_PRINT);
            }
            exit();
        }
    }
}

// Default response (return the current state)
echo json_encode([
    'status' => 'success',
    'message' => 'Data received',
    'requests' => $requests,
    'total_requests' => count($requests)
], JSON_PRETTY_PRINT); 