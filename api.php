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
// Note: Using '*' allows all origins. For production, consider restricting to specific domains
// Example: header('Access-Control-Allow-Origin: https://yourdomain.com');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
// For now, allow all origins for flexibility, but this should be restricted in production
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Load the configuration
$config = require __DIR__ . '/config.php';

// Load the Logger class
require_once __DIR__ . '/Logger.php';

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

/**
 * Check if an IP address is in a private network range
 * Used for SSRF protection
 * 
 * @param string $ip IP address to check
 * @return bool True if IP is in private range
 */
function isPrivateIP($ip) {
    // Check if it's localhost
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        return true;
    }
    
    // Check if it's a private IP range
    // 10.0.0.0/8
    if (strpos($ip, '10.') === 0) {
        return true;
    }
    
    // 172.16.0.0/12 (172.16.0.0 to 172.31.255.255)
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) {
        return true;
    }
    
    // 192.168.0.0/16
    if (strpos($ip, '192.168.') === 0) {
        return true;
    }
    
    // 169.254.0.0/16 (link-local)
    if (strpos($ip, '169.254.') === 0) {
        return true;
    }
    
    return false;
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
        
        // Validate JSON parsing
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON in request body: " . json_last_error_msg()]);
            exit;
        }

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
            
            // SSRF Protection: Prevent access to internal/private networks
            $parsedUrl = parse_url($endpoint);
            if (!isset($parsedUrl['host'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid endpoint URL: missing host."]);
                exit;
            }
            
            $host = $parsedUrl['host'];
            
            // Resolve hostname to IP address
            $ip = gethostbyname($host);
            
            // Check if IP is private/internal
            if (isPrivateIP($ip)) {
                http_response_code(403);
                echo json_encode(["error" => "Access to internal network denied."]);
                exit;
            }
            
            // Additional check: if hostname itself looks like localhost/internal
            $hostLower = strtolower($host);
            if (in_array($hostLower, ['localhost', '127.0.0.1', '::1', '0.0.0.0']) ||
                strpos($hostLower, '.local') !== false ||
                strpos($hostLower, '.internal') !== false) {
                http_response_code(403);
                echo json_encode(["error" => "Access to internal network denied."]);
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
                Logger::logOutboundRequest($config, $endpoint, $method, $headers, $payload, 0, '', "cURL Error: " . $error, $logIdentifier);
                
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
            Logger::logOutboundRequest($config, $endpoint, $method, $headers, $payload, $httpCode, $responseHeaders, $responseBody, $logIdentifier);

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
