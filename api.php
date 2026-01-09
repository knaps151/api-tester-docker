<?php

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

// Set CORS headers for all responses
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Load the configuration
$config = require __DIR__ . '/config.php';

// File to store templates
$templateFile = __DIR__ . '/templates.json';

// Ensure the template file exists
if (!file_exists($templateFile)) {
    $fileCreated = file_put_contents($templateFile, json_encode([]));
    if ($fileCreated === false) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create templates.json file. Check file permissions."]);
        exit;
    }
}

// Function to log outbound API requests
function logOutboundRequest($config, $endpoint, $method, $headers, $payload, $responseStatus, $responseHeaders, $responseBody, $customIdentifier = null) {
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

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Check if this is a request to list logs
        if (isset($_GET['action']) && $_GET['action'] === 'listLogs') {
            header('Content-Type: application/json');
            $logDirectory = $config['LOG_DIRECTORY'];
            
            // Ensure the log directory exists
            if (!is_dir($logDirectory)) {
                echo json_encode([]);
                exit;
            }
            
            // Get all .json files from the log directory
            $logFiles = glob($logDirectory . '/*.json');
            $logFilenames = array_map('basename', $logFiles);
            
            echo json_encode($logFilenames);
            exit;
        } else {
            // Fetch all templates
            header('Content-Type: application/json');
            echo file_get_contents($templateFile);
        }
        break;

    case 'POST':
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        // Check if this is a proxy request (has endpoint but no name)
        if (isset($data['action']) && $data['action'] === 'proxy') {
            // Proxy request to external API
            if (!isset($data['endpoint']) || !isset($data['method'])) {
                http_response_code(400);
                echo json_encode(["error" => "Endpoint and method are required for proxy requests."]);
                exit;
            }

            $endpoint = $data['endpoint'];
            $method = $data['method'];
            $headers = isset($data['headers']) ? $data['headers'] : [];
            $payload = isset($data['payload']) ? $data['payload'] : '';
            $timeout = isset($data['timeout']) ? intval($data['timeout']) : 30;
            $logIdentifier = isset($data['logIdentifier']) ? $data['logIdentifier'] : null;

            // Validate endpoint URL
            if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid endpoint URL."]);
                exit;
            }

            // Prepare cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout / 1000); // Convert ms to seconds
            curl_setopt($ch, CURLOPT_HEADER, true);

            // Set headers
            if (!empty($headers)) {
                $headerArray = [];
                foreach ($headers as $key => $value) {
                    $headerArray[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            }

            // Set body for methods that support it
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                // Log the failed request
                logOutboundRequest($config, $endpoint, $method, $headers, $payload, 0, '', "cURL Error: " . $error, $logIdentifier);
                
                http_response_code(500);
                echo json_encode([
                    "error" => "Request failed: " . $error,
                    "status" => 0
                ]);
                exit;
            }

            // Split headers and body
            $responseHeaders = substr($response, 0, $headerSize);
            $responseBody = substr($response, $headerSize);

            // Log the outbound request
            logOutboundRequest($config, $endpoint, $method, $headers, $payload, $httpCode, $responseHeaders, $responseBody, $logIdentifier);

            // Return response
            header('Content-Type: application/json');
            http_response_code($httpCode);
            echo json_encode([
                "status" => $httpCode,
                "headers" => $responseHeaders,
                "body" => $responseBody
            ]);
        } elseif (isset($data['name'])) {
            // Add a new template
            $templates = json_decode(file_get_contents($templateFile), true);
            $templateName = $data['name'];
            // Remove the name from the data object
            unset($data['name']);
            // Save the entire remaining data object as the template's value
            $templates[$templateName] = $data;
            file_put_contents($templateFile, json_encode($templates, JSON_PRETTY_PRINT));
            echo json_encode(["message" => "Template added successfully."]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Template name not provided or invalid request."]);
        }
        break;

    case 'DELETE':
        // Delete a template or log file
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (isset($data['filename'])) {
            // Delete a log file
            $logDirectory = $config['LOG_DIRECTORY'];
            $filename = basename($data['filename']); // Prevent directory traversal
            $filePath = $logDirectory . '/' . $filename;
            
            // Security check: ensure the file is within the LOG_DIRECTORY
            $realLogDir = realpath($logDirectory);
            $realFilePath = realpath($filePath);
            
            if ($realFilePath && strpos($realFilePath, $realLogDir) === 0 && file_exists($filePath)) {
                if (unlink($filePath)) {
                    echo json_encode(["message" => "Log file deleted successfully."]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Failed to delete log file."]);
                }
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Log file not found."]);
            }
        } elseif (isset($data['name'])) {
            // Delete a template
            $templates = json_decode(file_get_contents($templateFile), true);
            if (isset($templates[$data['name']])) {
                unset($templates[$data['name']]);
                file_put_contents($templateFile, json_encode($templates, JSON_PRETTY_PRINT));
                echo json_encode(["message" => "Template deleted successfully."]);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Template not found."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Filename or template name not provided."]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed."]);
        break;
}
