# API Tester

A comprehensive web-based tool for testing APIs, managing request templates, capturing webhooks, and logging all HTTP traffic. Perfect for development, debugging, and API integration testing.

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Network Access](#network-access)
- [Configuration](#configuration)
- [Using the Application](#using-the-application)
- [Data Persistence](#data-persistence)
- [Troubleshooting](#troubleshooting)
- [Example Usage](#example-usage)
- [Docker Commands Reference](#docker-commands-reference)
- [License](#license)

## Features

### 🔄 Send API Requests
- Proxy requests to any external API with full request/response logging
- Support for all HTTP methods (GET, POST, PUT, DELETE, PATCH, etc.)
- Custom headers, query parameters, and request bodies
- Configurable timeouts and authentication (Basic Auth, Bearer Token)
- Automatic logging of all outbound requests

### 📥 Webhook Catcher
- Capture and inspect incoming webhook requests in real-time
- Custom response templates for webhook endpoints
- View request headers, body, query parameters, and client IP
- Automatic logging of all inbound requests
- Support for JSON, form data, and other content types

### 📋 Template Management
- Save and reuse API request configurations
- Create response templates for webhook endpoints
- Organize templates by type (Request or Response)
- Edit and delete templates easily
- Pre-fill forms with saved templates

### 📊 Real-Time Logs
- View all inbound and outbound requests with full details
- Search and filter logs by identifier or filename
- Automatic log file generation with timestamps
- Delete individual log entries
- Real-time updates without page refresh

## Quick Start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) installed
- [Docker Compose](https://docs.docker.com/compose/install/) (usually included with Docker Desktop)

### Running the Application

1. **Clone or download this repository**

2. **Start the container:**
   ```bash
   docker-compose up -d
   ```

3. **Access the application:**
   Open your browser and navigate to:
   - **Main Dashboard**: http://localhost:8080
   - **Log Viewer**: http://localhost:8080/index.html
   - **Send Requests**: http://localhost:8080/post-form.html
   - **Webhook Catcher**: http://localhost:8080/catchall/index.php
   - **Template Management**: http://localhost:8080/template-management.html

4. **Stop the container:**
   ```bash
   docker-compose down
   ```

## Network Access

The application is accessible from other devices on your local network by default.

### Accessing from Other Devices

1. **Find your machine's IP address:**
  ```bash
   # On macOS/Linux
   ifconfig | grep "inet " | grep -v 127.0.0.1
   
   # On Windows
   ipconfig
   ```

2. **Access from other devices:**
   - Replace `localhost` with your machine's IP address
   - Example: `http://10.0.0.151:8080` (use your actual IP)
   - The BASE_URL is automatically detected from the request

### Firewall Considerations

If other devices can't connect, check your firewall:
- **macOS**: System Settings → Network → Firewall (may need to allow incoming connections)
- **Linux**: Check `ufw` or `iptables` rules
- **Windows**: Windows Defender Firewall settings

The container binds to `0.0.0.0:8080` by default, making it accessible on all network interfaces.

### Client IP Address Detection

**macOS Docker Desktop Limitation**: Due to Docker Desktop's VM architecture, client IP addresses will show as the Docker gateway IP (e.g., `192.168.128.1`) instead of the real client IP. This is a known limitation of Docker Desktop on macOS.

**Workaround for macOS**: To see real client IPs, you can run a reverse proxy (like nginx) on your Mac that forwards to the Docker container. See `nginx-proxy.conf.example` for a reference configuration.

**Linux hosts**: To see real client IP addresses, you can use host networking mode. Edit `docker-compose.yml`:

```yaml
services:
  apitester:
    network_mode: host
    # Remove the ports section when using host networking
```

Then update the Dockerfile to configure Apache for port 8080 and rebuild: `docker-compose build && docker-compose up -d`

## Configuration

### Change Port

Edit `docker-compose.yml` to use a different port:

```yaml
ports:
  - "YOUR_PORT:80"  # Replace YOUR_PORT with your desired port
```

Then restart:
```bash
docker-compose down
docker-compose up -d
```

### Override Base URL (Advanced)

The `BASE_URL` is automatically detected from request headers, so it works correctly whether accessed via localhost or network IP. If you need to manually override it, uncomment the environment section in `docker-compose.yml`:

```yaml
environment:
  - BASE_URL=http://your-ip:8080/
```

Then restart: `docker-compose restart`

## Using the Application

### View Logs

The log viewer displays all inbound and outbound API requests:

1. Navigate to **Logs** (http://localhost:8080/index.html)
2. Use the search bar to filter logs by identifier or filename
3. Click on any log entry to view full request/response details including:
   - Request method, endpoint, headers, and payload
   - Response status, headers, and body
   - Timestamps and client identifiers
4. Delete logs using the delete button (trash icon)

### Send API Requests

Send requests to any API endpoint with full logging:

1. Go to **Send Request** (http://localhost:8080/post-form.html)
2. **Option A - Use a template:**
   - Select a saved template from the dropdown
   - The form will be pre-filled with template data
   - Modify as needed
3. **Option B - Manual entry:**
   - Select HTTP method (GET, POST, PUT, DELETE, etc.)
   - Enter the full endpoint URL
   - Add headers as JSON (optional)
   - Add request body (optional)
   - Set timeout (default: 5000ms)
4. Add a custom identifier (e.g., `?id=my-test`) to help identify logs
5. Click **"Send Request"**
6. View the response and check logs for full request/response details

### Capture Webhooks

Use the webhook catcher to receive and inspect incoming requests:

1. Go to **Receive** (http://localhost:8080/catchall/index.php)
2. Copy the webhook URL displayed at the top
3. Configure your external service to send webhooks to this URL
4. **Configure response template (optional):**
   - Select a response template from the dropdown
   - Preview the response that will be returned
   - The template defines status code, headers, and response body
5. View incoming requests in real-time on the page
6. All requests are automatically logged to the logs directory

### Manage Templates

Create and manage request and response templates:

1. Go to **Manage Templates** (http://localhost:8080/template-management.html)
2. **Create a Request Template:**
   - Select "Request Template" as the type
   - Fill in endpoint, method, headers, query params, payload
   - Configure authentication if needed
   - Enter a template name and click "Save Template"
3. **Create a Response Template:**
   - Select "Response Template" as the type
   - Set HTTP status code (200, 201, 400, etc.)
   - Add response headers as JSON
   - Define response body (JSON, text, etc.)
   - Enter a template name and click "Save Template"
4. **Edit a template:**
   - Click the "Edit" button next to any template
   - Modify the fields and click "Save Template"
5. **Delete a template:**
   - Click the delete button (trash icon) next to any template

## Data Persistence

The following data persists across container restarts:

- `./logs/` - All request/response logs (JSON files)
- `./templates.json` - Saved request and response templates
- `./catchall/requests.json` - Webhook request data (last 100 requests)

**Note**: These files are stored on your host machine, so they persist even if you remove the container.

## Troubleshooting

### Container won't start

```bash
# Check logs for errors
docker-compose logs

# Rebuild the image from scratch
docker-compose build --no-cache
docker-compose up -d
```

### Port already in use

Change the port in `docker-compose.yml` or stop the conflicting service:

```bash
# Find what's using the port
lsof -i :8080  # macOS/Linux
netstat -ano | findstr :8080  # Windows

# Change port in docker-compose.yml and restart
docker-compose down
docker-compose up -d
```

### View container logs

```bash
# View application logs (follow mode)
docker-compose logs -f

# View Apache error logs
docker exec apitester tail -f /var/log/apache2/error.log

# View last 50 lines
docker-compose logs --tail=50
```

### Access container shell

  ```bash
# Open a bash shell in the container
docker exec -it apitester bash

# Check Apache status
docker exec apitester apache2ctl status
```

### Templates not saving

Ensure the `templates.json` file has write permissions:

  ```bash
# Check file permissions
ls -la templates.json

# Fix permissions if needed
chmod 664 templates.json
```

### Logs not displaying

Check if the logs directory exists and is writable:

  ```bash
# Check directory
ls -la logs/

# Fix permissions if needed
chmod 755 logs/
```

## Example Usage

### Send a test request via cURL

   ```bash
curl -X POST "http://localhost:8080/api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "proxy",
    "method": "GET",
    "endpoint": "https://httpbin.org/get",
    "logIdentifier": "test-request"
  }'
```

### Send data to webhook catcher

   ```bash
curl -X POST "http://localhost:8080/catchall/api.php?id=my-webhook" \
  -H "Content-Type: application/json" \
  -d '{"event": "test", "data": "example"}'
```

### Use webhook catcher with custom response

1. Create a response template in **Manage Templates**:
   - Name: "Success Response"
   - Status Code: 200
   - Headers: `{"Content-Type": "application/json"}`
   - Body: `{"status": "success", "message": "Webhook received"}`

2. Go to **Receive** page and select "Success Response" template

3. Send a request to your webhook URL - it will return your custom response

## Docker Commands Reference

   ```bash
# Start the application
docker-compose up -d

# Stop the application
docker-compose down

# View logs (follow mode)
docker-compose logs -f

# Restart the application
docker-compose restart

# Rebuild after code changes
docker-compose build
docker-compose up -d

# Remove container and volumes (⚠️ deletes all data)
docker-compose down -v

# View running containers
docker-compose ps

# Execute command in container
docker exec apitester <command>
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

**Made with ❤️ for developers who need a simple, powerful API testing tool.**
