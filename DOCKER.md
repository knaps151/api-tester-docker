# Docker Quick Start Guide

## Quick Start

```bash
# Start the application
docker-compose up -d

# View logs
docker-compose logs -f

# Stop the application
docker-compose down
```

## Access the Application

Once the container is running, access the application at:
- **Main Application:** http://localhost:8080
- **Logs Viewer:** http://localhost:8080/index.html
- **Send Requests:** http://localhost:8080/post-form.html
- **Manage Templates:** http://localhost:8080/template-management.html
- **Webhook Catcher:** http://localhost:8080/catchall/index.php
- **API Endpoint:** http://localhost:8080/api.php

## Custom Port

To change the port, edit `docker-compose.yml`:

```yaml
ports:
  - "YOUR_PORT:80"  # Replace YOUR_PORT with your desired port
```

Then restart:
```bash
docker-compose down
docker-compose up -d
```

## Data Persistence

The following are persisted via Docker volumes:
- `./logs` - All log files
- `./templates.json` - Saved templates

These files persist even when the container is stopped or removed.

## Troubleshooting

### Container won't start
```bash
# Check logs
docker-compose logs

# Rebuild the image
docker-compose build --no-cache
docker-compose up -d
```

### Permission issues
The Dockerfile automatically sets correct permissions. If you encounter issues:
```bash
# Rebuild the container
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Port already in use
If port 8080 is already in use, change it in `docker-compose.yml` or stop the conflicting service.

## Development

### Rebuild after code changes
```bash
docker-compose build
docker-compose up -d
```

### Access container shell
```bash
docker exec -it apitester bash
```

### View Apache error logs
```bash
docker exec apitester tail -f /var/log/apache2/error.log
```

