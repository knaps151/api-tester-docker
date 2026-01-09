# Data Catcher

A catch-all endpoint that displays any data sent to it in real-time with a modern UI. This tool is useful for debugging API requests, testing webhooks, or monitoring incoming data.

## Features

- Accepts only POST requests
- Shows request headers, body, and query parameters
- Automatically detects and displays base64 encoded images
- Real-time updates every 5 seconds
- Clean, modern UI
- Ability to clear all stored data
- Toggle base64 image display
- Stores last 100 requests in session

## Usage

1. Send POST requests to the endpoint
2. The data will be automatically displayed in the UI
3. Use the "Clear All Data" button to remove all stored requests
4. Use the "Toggle Base64 Images" button to show/hide base64 encoded images

## Example Requests

```bash
# POST request with JSON
curl -X POST -H "Content-Type: application/json" -d '{"key":"value"}' http://your-domain/

# POST request with base64 image
curl -X POST -H "Content-Type: application/json" -d '{"image":"data:image/png;base64,..."}' http://your-domain/
```

## Requirements

- PHP 7.0 or higher
- Web server (Apache/Nginx)
- Session support enabled in PHP

## Security Note

This tool is designed for development and debugging purposes. It's not recommended to use it in production environments as it stores all incoming requests in the session. 