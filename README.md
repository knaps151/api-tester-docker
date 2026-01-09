# API Tester

A web-based tool for testing APIs, managing request templates, and capturing webhooks. Perfect for development, debugging, and API integration testing.

## Features

- **Send API Requests**: Proxy requests to external APIs with full logging
- **Webhook Catcher**: Capture and inspect incoming webhook requests
- **Template Management**: Save and reuse API request templates
- **Real-Time Logs**: View all requests and responses with search and filtering
- **Request Logging**: Automatic logging of all inbound and outbound requests

## Quick Start

### Prerequisites

- Docker and Docker Compose installed

### Running the Application

1. **Start the container:**
   ```bash
   docker-compose up -d
   ```

2. **Access the application:**
   - Main interface: http://localhost:8080
   - Log viewer: http://localhost:8080/index.html
   - Send requests: http://localhost:8080/post-form.html
   - Webhook catcher: http://localhost:8080/catchall/index.php
   - Template management: http://localhost:8080/template-management.html

3. **Stop the container:**
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
   - The BASE_URL is automatically detected from the request, so it will use the correct IP/domain based on how it's accessed

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

1. Navigate to the log viewer (http://localhost:8080/index.html)
2. Use the search bar to filter logs by identifier or filename
3. Click on any log entry to view full request/response details
4. Delete logs using the delete button

### Send API Requests

1. Go to the request form (http://localhost:8080/post-form.html)
2. Select a template or enter:
   - HTTP method (GET, POST, PUT, DELETE, etc.)
   - Endpoint URL
   - Headers (optional)
   - Request body (optional)
3. Add a custom identifier (e.g., `?id=my-test`) to help identify logs
4. Click "Send Request"
5. View the response and check logs for the full request/response details

### Capture Webhooks

1. Go to the webhook catcher (http://localhost:8080/catchall/index.php)
2. Copy the webhook URL displayed
3. Configure your external service to send webhooks to this URL
4. View incoming requests in real-time on the page
5. All requests are automatically logged to the logs directory

### Manage Templates

1. Go to template management (http://localhost:8080/template-management.html)
2. **Add a template:**
   - Fill in the request form with your endpoint, method, headers, and payload
   - Enter a template name
   - Click "Save Template"
3. **Use a template:**
   - Select a template from the dropdown in the request form
   - The form will be pre-filled with the template data
4. **Delete a template:**
   - Use the delete button next to each template

## Data Persistence

The following data persists across container restarts:
- `./logs/` - All request/response logs
- `./templates.json` - Saved templates
- `./catchall/requests.json` - Webhook request data

## Troubleshooting

### Container won't start

```bash
# Check logs
docker-compose logs

# Rebuild the image
docker-compose build --no-cache
docker-compose up -d
```

### Port already in use

Change the port in `docker-compose.yml` or stop the conflicting service.

### View container logs

```bash
# View application logs
docker-compose logs -f

# View Apache error logs
docker exec apitester tail -f /var/log/apache2/error.log
```

### Access container shell

```bash
docker exec -it apitester bash
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

## Docker Commands Reference

```bash
# Start the application
docker-compose up -d

# Stop the application
docker-compose down

# View logs
docker-compose logs -f

# Restart the application
docker-compose restart

# Rebuild after code changes
docker-compose build
docker-compose up -d

# Remove container and volumes (deletes all data)
docker-compose down -v
```
