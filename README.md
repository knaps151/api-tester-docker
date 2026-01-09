
# API Tester Application

The **API Tester** is a web-based tool designed to simplify testing and management of API requests. It includes features such as sending API requests, managing shared templates, viewing real-time logs, and more. The application is designed to be used by multiple users sharing the same instance.

## Features

### 1. **Send API Requests**
- Supports POST requests with customizable endpoints and JSON payloads.
- Includes pre-built templates for commonly used API requests.

### 2. **Manage Templates**
- Templates are stored server-side in a shared `templates.json` file.
- Users can:
  - Select from pre-built templates.
  - Add new templates.
  - Delete templates.

### 3. **View Real-Time Logs**
- Logs are updated in real-time without needing to refresh the page.
- Includes:
  - Log preview functionality to view request details.
  - Search and filter logs by filename or identifier.
  - Pagination for managing large numbers of logs.

### 4. **Delete Logs**
- Each log entry includes a delete button to remove it from the server.
- Log filenames include an identifier (e.g., `demo1_YYYY-MM-DD_HH-MM-SS_uniqueid.json`) for easy identification.

## File Structure

```
/var/www/html/apitester
    ├── api.php           # Main API logic (handles logs, templates, and requests)
    ├── config.php        # Configuration file
    ├── templates.json    # Server-side storage for shared templates
    ├── index.html        # Frontend for viewing logs
    ├── post-form.html    # Frontend for sending API requests
    ├── logs/             # Directory for log files
```

## Setup Instructions

### Option 1: Docker (Recommended)

The easiest way to run the API Tester is using Docker.

#### Prerequisites
- Docker installed ([Install Docker](https://docs.docker.com/get-docker/))
- Docker Compose (usually included with Docker Desktop)

#### Quick Start

1. **Build and run the container:**
   ```bash
   docker-compose up -d
   ```

2. **Access the application:**
   - Open your browser and navigate to: `http://localhost:8080`
   - The application will be available at the root path

3. **Stop the container:**
   ```bash
   docker-compose down
   ```

#### Docker Commands

- **Build the image:**
  ```bash
  docker-compose build
  ```

- **View logs:**
  ```bash
  docker-compose logs -f
  ```

- **Restart the container:**
  ```bash
  docker-compose restart
  ```

- **Remove the container and volumes:**
  ```bash
  docker-compose down -v
  ```

#### Configuration

The `docker-compose.yml` file includes:
- **Port mapping:** Container port 80 is mapped to host port 8080 (change in docker-compose.yml if needed)
- **Volume mounts:** 
  - `./logs` - Persists log files across container restarts
  - `./templates.json` - Persists templates across container restarts
- **Environment variables:**
  - `BASE_URL` - Set to `http://localhost:8080/` by default (update if using different host/port)

#### Custom Port

To use a different port, edit `docker-compose.yml`:
```yaml
ports:
  - "YOUR_PORT:80"  # Change YOUR_PORT to desired port number
```

#### Building the Docker Image Manually

If you prefer to build and run without docker-compose:
```bash
# Build the image
docker build -t apitester .

# Run the container
docker run -d \
  -p 8080:80 \
  -v $(pwd)/logs:/var/www/html/logs \
  -v $(pwd)/templates.json:/var/www/html/templates.json \
  -e BASE_URL=http://localhost:8080/ \
  --name apitester \
  apitester
```

---

### Option 2: Manual Installation

### 1. **Install Required Software**
- A web server (e.g., Apache or Nginx).
- PHP installed and configured.

### 2. **Deploy the Application**
1. Place all files in the `/var/www/html/apitester` directory.
2. Set proper permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/apitester
   sudo chmod 664 /var/www/html/apitester/templates.json
   sudo chmod 755 /var/www/html/apitester/logs
   ```

3. Ensure the `logs/` directory exists:
   ```bash
   mkdir /var/www/html/apitester/logs
   sudo chmod 755 /var/www/html/apitester/logs
   ```

4. Create the `templates.json` file:
   ```bash
   touch /var/www/html/apitester/templates.json
   sudo chmod 664 /var/www/html/apitester/templates.json
   ```

### 3. **Access the Application**
- Open the browser and navigate to: `http://<server-ip>/apitester/`

## Using the Application

### **1. View Logs**
1. Open `index.html` to see a list of logs.
2. Use the search bar to filter logs by name.
3. Click on a log to preview its contents.
4. Use the delete button (trash icon) to remove logs.

### **2. Send API Requests**
1. Open `post-form.html` to access the request form.
2. Fill in the endpoint and JSON payload fields manually or select a template.
3. Include a custom identifier in the URL query string, e.g., `?id=demo1` to help identify logs.
4. Click "Send Request" to execute the request.

### **3. Manage Templates**
- **Select Template**: Choose a template from the dropdown to prefill the request form.
- **Add Template**:
  1. Fill in the endpoint and payload fields.
  2. Enter a name in the "Add New Template" section.
  3. Click "Save Template."
- **Delete Template**: (Not implemented yet).
  - Planned feature to allow deletion of templates from the dropdown.

### **4. Sending Inbound Payloads from External Tools**
To send payloads to the API Tester from external tools like Postman or custom scripts:
1. **API Endpoint**: Use the URL `http://<server-ip>/apitester/api.php`.
2. **Add Custom Identifier**:
   - Append a query parameter to the URL to include a custom identifier, e.g., `http://<server-ip>/apitester/api.php?id=demo1`.
   - This identifier will appear in the log filenames for easier tracking.
3. **Payload**:
   - Send your payload as JSON in the body of the request.
   - Example using Postman:
     - Set the method to `POST`.
     - Enter the URL with the identifier.
     - In the "Body" tab, select "raw" and choose JSON as the format.
     - Add your JSON payload.
4. **Log Results**:
   - Check the logs in the application to confirm the request was received.

Example cURL command:
```bash
curl -X POST "http://<server-ip>/apitester/api.php?id=demo1" \
     -H "Content-Type: application/json" \
     -d '{"key": "value"}'
```

## API Endpoints

### **1. Template Management**
- **GET /api.php**
  - Fetch all saved templates.
  - Response:
    ```json
    {
      "template_name": {
        "endpoint": "<API endpoint>",
        "payload": "<JSON payload>"
      }
    }
    ```

- **POST /api.php**
  - Add a new template.
  - Request body:
    ```json
    {
      "name": "template_name",
      "endpoint": "<API endpoint>",
      "payload": "<JSON payload>"
    }
    ```

- **DELETE /api.php**
  - Planned feature to delete an existing template.

### **2. Log Management**
- Logs are stored in the `/logs/` directory.
- Log filenames include an identifier, timestamp, and unique ID (e.g., `demo1_YYYY-MM-DD_HH-MM-SS_uniqueid.json`).

## Troubleshooting

### **1. Templates Not Saving**
- Ensure the `templates.json` file exists and has write permissions.
  ```bash
  sudo chown www-data:www-data /var/www/html/apitester/templates.json
  sudo chmod 664 /var/www/html/apitester/templates.json
  ```

### **2. Logs Not Displaying**
- Check if the `logs/` directory exists and is writable:
  ```bash
  chmod 755 /var/www/html/apitester/logs
  ```

- Verify PHP error logs for issues:
  ```bash
  sudo tail -f /var/log/apache2/error.log
  ```

### **3. 500 Internal Server Error**
- Check permissions for the application directory and files.
- Verify the PHP installation and server configuration.

## Future Improvements

1. **Authentication**: Add user authentication for secure access.
2. **Database Integration**: Replace `templates.json` with a database for better scalability.
3. **WebSocket Logs**: Implement WebSockets for real-time log updates without polling.
4. **Template Deletion**: Fully implement the ability to delete templates.

---

## GitHub Setup

This project is ready to be pushed to GitHub. Before pushing:

1. **Copy the example templates file:**
   ```bash
   cp templates.json.example templates.json
   ```

2. **Initialize git repository (if not already done):**
   ```bash
   git init
   git add .
   git commit -m "Initial commit"
   ```

3. **Add your GitHub remote and push:**
   ```bash
   git remote add origin https://github.com/yourusername/apitester.git
   git branch -M main
   git push -u origin main
   ```

### Files Ignored by Git

The following files are excluded from version control (see `.gitignore`):
- `logs/*.json` - Log files
- `templates.json` - User-specific templates (use `templates.json.example` as a template)
- IDE and OS-specific files
- Temporary files

---

Enjoy using the API Tester! 🚀
