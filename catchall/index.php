<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Tester - Webhook Catcher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="../index.html">API Tester</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="../index.html">Logs</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../post-form.html">Send Request</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../template-management.html">Manage Templates</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="index.php">Receive (Webhook Catcher)</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

    <div class="container mt-4">
        <h1 class="text-center mb-4">Webhook Catcher</h1>
        
        <!-- URL Display -->
        <div class="row mb-4">
            <div class="col-md-8 offset-md-2">
                <label for="webhookUrl" class="form-label">
                    Webhook URL
                    <i class="bi bi-question-circle text-primary ms-1" data-bs-toggle="tooltip" data-bs-placement="right" title="Copy this URL and use it as a webhook endpoint in external services. Any requests sent to this URL will be captured and displayed below. You can test it using Postman, curl, or any HTTP client."></i>
                </label>
                <div class="input-group">
                    <input type="text" id="webhookUrl" class="form-control" readonly>
                    <button id="copyUrl" class="btn btn-primary">Copy URL</button>
                </div>
            </div>
        </div>
        
        <!-- Response Template Selector -->
        <div class="row mb-4">
            <div class="col-md-6 offset-md-3">
                <label for="responseTemplate" class="form-label">
                    Response Template (Optional)
                    <i class="bi bi-question-circle text-primary ms-1" data-bs-toggle="tooltip" data-bs-placement="right" title="Select a custom response template to return when requests are received. If no template is selected, the default JSON response will be sent. Response templates can be created in the 'Manage Templates' page."></i>
                </label>
                <select id="responseTemplate" class="form-select">
                    <option value="">Default Response (JSON)</option>
                </select>
                <small class="form-text text-muted">Select a custom response template to return when requests are received</small>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="row mb-4">
            <div class="col-md-12 text-center">
                <button id="clearData" class="btn btn-danger me-2">
                    Clear All Requests
                    <i class="bi bi-question-circle text-white ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Permanently delete all captured requests. This action cannot be undone."></i>
                </button>
                <button id="toggleImages" class="btn btn-secondary">
                    Toggle Base64 Images
                    <i class="bi bi-question-circle text-white ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Show or hide base64-encoded images in request bodies. When enabled, base64 image data will be displayed as actual images."></i>
                </button>
            </div>
        </div>

        <div id="dataContainer"></div>
    </div>

    <script>
        const API_URL = "../api.php";
        let showImages = true;
        const webhookUrl = document.getElementById('webhookUrl');
        const responseTemplateSelector = document.getElementById('responseTemplate');
        
        // Set the webhook URL to point to the API endpoint (same directory as index.php)
        webhookUrl.value = window.location.origin + window.location.pathname.replace('index.php', 'api.php');
        
        // Load response templates
        async function loadResponseTemplates() {
            try {
                const response = await fetch(API_URL);
                if (!response.ok) throw new Error(`Failed to fetch templates: ${response.status}`);
                const templates = await response.json();
                
                // Filter for response templates (type === 'response_template')
                const responseTemplates = Object.entries(templates)
                    .filter(([name, details]) => details.type === 'response_template')
                    .map(([name, details]) => ({ name, ...details }));
                
                // Populate selector
                responseTemplateSelector.innerHTML = '<option value="">Default Response (JSON)</option>';
                responseTemplates.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.name;
                    option.textContent = template.name;
                    responseTemplateSelector.appendChild(option);
                });
                
                // Load saved selection from localStorage
                const savedTemplate = localStorage.getItem('catchall_response_template');
                if (savedTemplate) {
                    responseTemplateSelector.value = savedTemplate;
                }
            } catch (error) {
                console.error('Error loading response templates:', error);
            }
        }
        
        // Save selection when changed
        responseTemplateSelector.addEventListener('change', () => {
            localStorage.setItem('catchall_response_template', responseTemplateSelector.value);
            // Update API with current selection
            updateResponseTemplate();
        });
        
        // Update response template on API
        async function updateResponseTemplate() {
            const selectedTemplate = responseTemplateSelector.value;
            try {
                await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'set_response_template',
                        template_name: selectedTemplate || null
                    })
                });
            } catch (error) {
                console.error('Error updating response template:', error);
            }
        }
        
        // Load templates on page load
        loadResponseTemplates();
        
        // Update response template on initial load
        updateResponseTemplate();

        // Copy URL to clipboard
        document.getElementById('copyUrl').addEventListener('click', () => {
            webhookUrl.select();
            webhookUrl.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(webhookUrl.value).then(() => {
                const button = document.getElementById('copyUrl');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                button.classList.remove('btn-primary');
                button.classList.add('btn-success');
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-primary');
                }, 2000);
            }).catch(err => {
                // Fallback for older browsers
                document.execCommand('copy');
                const button = document.getElementById('copyUrl');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            });
        });

        // Function to check if a string is base64 encoded
        function isBase64(str) {
            try {
                return btoa(atob(str)) === str;
            } catch (err) {
                return false;
            }
        }

        // Function to check if base64 string is an image
        function isBase64Image(str) {
            return str.startsWith('data:image/') || 
                   (isBase64(str) && str.length > 100);
        }

        // Function to format JSON with syntax highlighting
        function formatJSON(json) {
            let formatted;
            if (typeof json !== 'string') {
                formatted = JSON.stringify(json, null, 2);
            } else {
                // Try to parse and reformat if it's valid JSON
                try {
                    const parsed = JSON.parse(json);
                    formatted = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    formatted = json;
                }
            }
            
            // Escape HTML and apply syntax highlighting
            return formatted
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                    let cls = 'number';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'key';
                        } else {
                            cls = 'string';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'boolean';
                    } else if (/null/.test(match)) {
                        cls = 'null';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                });
        }

        // Function to detect content type and format accordingly
        function formatBody(body, headers) {
            if (!body) return '';
            
            // Check Content-Type header
            const contentType = headers && headers['Content-Type'] 
                ? headers['Content-Type'].toLowerCase() 
                : '';
            
            // Handle JSON
            if (contentType.includes('application/json') || contentType.includes('application/vnd.api+json')) {
                try {
                    const parsed = typeof body === 'string' ? JSON.parse(body) : body;
                    return `<pre class="json-body">${formatJSON(parsed)}</pre>`;
                } catch (e) {
                    return `<pre class="text-body">${escapeHtml(body)}</pre>`;
                }
            }
            
            // Handle form data
            if (contentType.includes('application/x-www-form-urlencoded')) {
                try {
                    const params = new URLSearchParams(body);
                    const obj = {};
                    for (const [key, value] of params) {
                        obj[key] = value;
                    }
                    return `<pre class="json-body">${formatJSON(obj)}</pre>`;
                } catch (e) {
                    return `<pre class="text-body">${escapeHtml(body)}</pre>`;
                }
            }
            
            // Handle multipart form data
            if (contentType.includes('multipart/form-data')) {
                return `<pre class="text-body">${escapeHtml(body)}</pre><small class="text-muted">Multipart form data (raw display)</small>`;
            }
            
            // Handle XML
            if (contentType.includes('application/xml') || contentType.includes('text/xml')) {
                try {
                    const parser = new DOMParser();
                    const xmlDoc = parser.parseFromString(body, 'text/xml');
                    const serializer = new XMLSerializer();
                    const formatted = formatXML(serializer.serializeToString(xmlDoc));
                    return `<pre class="xml-body">${formatted}</pre>`;
                } catch (e) {
                    return `<pre class="text-body">${escapeHtml(body)}</pre>`;
                }
            }
            
            // Try to parse as JSON anyway (might be JSON without proper content-type)
            try {
                const parsed = typeof body === 'string' ? JSON.parse(body) : body;
                return `<pre class="json-body">${formatJSON(parsed)}</pre>`;
            } catch (e) {
                // Not JSON, display as plain text
                return `<pre class="text-body">${escapeHtml(body)}</pre>`;
            }
        }

        // Function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Function to format XML
        function formatXML(xml) {
            let formatted = '';
            let indent = '';
            const nodes = xml.split(/>\s*</);
            nodes.forEach(function(node) {
                if (node.match(/^\/\w/)) indent = indent.substring(2);
                formatted += indent + '<' + node + '>\r\n';
                // Check if it's an opening tag (not closing, not xml declaration)
                const isOpeningTag = node.length > 0 && 
                                    !node.startsWith('/') && 
                                    !node.startsWith('?xml') &&
                                    !node.endsWith('/');
                if (isOpeningTag) indent += '  ';
            });
            return formatted.substring(1, formatted.length - 3);
        }

        // Function to create a collapsible section
        function createCollapsible(title, content, stableId) {
            // Use stableId if provided (for preserving state across refreshes)
            const uniqueId = stableId || 'accordion-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const headerId = 'header-' + uniqueId;
            const bodyId = 'body-' + uniqueId;
            
            const div = document.createElement('div');
            div.className = 'accordion-item mb-2';
            
            const header = document.createElement('h2');
            header.className = 'accordion-header';
            header.id = headerId;
            
            const button = document.createElement('button');
            button.className = 'accordion-button collapsed';
            button.type = 'button';
            button.setAttribute('data-bs-toggle', 'collapse');
            button.setAttribute('data-bs-target', '#' + bodyId);
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-controls', bodyId);
            button.textContent = title;
            
            header.appendChild(button);
            
            const collapseDiv = document.createElement('div');
            collapseDiv.id = bodyId;
            collapseDiv.className = 'accordion-collapse collapse';
            collapseDiv.setAttribute('aria-labelledby', headerId);
            
            const body = document.createElement('div');
            body.className = 'accordion-body';
            body.innerHTML = content;
            
            collapseDiv.appendChild(body);
            
            div.appendChild(header);
            div.appendChild(collapseDiv);
            
            return div;
        }

        // Function to create a request item element
        function createRequestItem(request, index) {
            // Create stable ID based on request timestamp and IP for state preservation
            const baseRequestId = (request.timestamp || '').replace(/[^0-9]/g, '') + '-' + (request.ip || '').replace(/[^0-9.]/g, '') + '-' + index;
            const requestId = 'req-' + baseRequestId;
            
            const div = document.createElement('div');
            div.className = 'card mb-3 request-item';

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';

            const header = document.createElement('div');
            header.className = 'd-flex justify-content-between align-items-center mb-3 pb-2 border-bottom';

            const method = document.createElement('span');
            method.className = 'badge bg-primary';
            method.textContent = request.method;

            const timestamp = document.createElement('span');
            timestamp.className = 'text-muted small';
            timestamp.textContent = request.timestamp;

            const ip = document.createElement('span');
            ip.className = 'text-muted small font-monospace';
            ip.textContent = request.ip;

            header.appendChild(method);
            header.appendChild(timestamp);
            header.appendChild(ip);
            cardBody.appendChild(header);

            // Add headers with stable ID
            const headersContent = `<pre>${formatJSON(request.headers)}</pre>`;
            cardBody.appendChild(createCollapsible('Headers', headersContent, requestId + '-headers'));

            // Add body if present
            if (request.body) {
                let bodyContent = '';
                const headers = request.headers || {};
                
                // Format body based on content type
                bodyContent = formatBody(request.body, headers);
                
                // Check for base64 images in parsed body (if JSON)
                if (showImages) {
                    try {
                        const parsedBody = request.parsed_body || JSON.parse(request.body);
                        if (typeof parsedBody === 'object') {
                            Object.entries(parsedBody).forEach(([key, value]) => {
                                if (typeof value === 'string' && isBase64Image(value)) {
                                    const img = document.createElement('img');
                                    img.className = 'base64-image';
                                    img.src = value;
                                    img.alt = `Base64 image from ${key}`;
                                    bodyContent += img.outerHTML;
                                }
                            });
                        }
                    } catch (e) {
                        // Not JSON, skip image detection
                    }
                }
                
                // Create body wrapper div
                const bodyWrapper = document.createElement('div');
                
                // Add copy button with unique ID
                const buttonId = 'copy-btn-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                const copyButtonHtml = `
                    <button id="${buttonId}" class="btn btn-sm btn-secondary copy-body-btn" style="margin-bottom: 0.5rem;">
                        <i class="bi bi-clipboard"></i> Copy Body
                    </button>
                `;
                bodyWrapper.innerHTML = copyButtonHtml + bodyContent;
                
                // Attach event listener to the button
                const copyButton = bodyWrapper.querySelector('#' + buttonId);
                if (copyButton) {
                    const bodyText = request.body;
                    copyButton.addEventListener('click', function() {
                        navigator.clipboard.writeText(bodyText).then(() => {
                            const originalText = copyButton.innerHTML;
                            copyButton.innerHTML = '<i class="bi bi-check"></i> Copied!';
                            setTimeout(() => {
                                copyButton.innerHTML = originalText;
                            }, 2000);
                        }).catch(err => {
                            console.error('Failed to copy:', err);
                        });
                    });
                }
                
                cardBody.appendChild(createCollapsible('Body', bodyWrapper.innerHTML, requestId + '-body'));
            }

            div.appendChild(cardBody);
            return div;
        }

        // Track displayed requests to avoid rebuilding
        let displayedRequestIds = new Set();
        
        // Function to generate a unique ID for a request
        function getRequestId(request, index) {
            return (request.timestamp || '').replace(/[^0-9]/g, '') + '-' + (request.ip || '').replace(/[^0-9.]/g, '') + '-' + index;
        }
        
        // Function to update the display intelligently
        function updateDisplay() {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'get_requests' })
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('dataContainer');
                
                if (!data.requests || data.requests.length === 0) {
                    // Only clear if we had content before
                    if (displayedRequestIds.size > 0) {
                        container.innerHTML = '<div class="alert alert-info text-center">No requests received yet. Send a POST request to the URL above.</div>';
                        displayedRequestIds.clear();
                    }
                    return;
                }
                
                // Remove "no requests" message if present
                const noRequestsMsg = container.querySelector('.alert-info');
                if (noRequestsMsg && displayedRequestIds.size === 0) {
                    container.innerHTML = '';
                }
                
                // Track which requests we've seen in this update
                const currentRequestIds = new Set();
                
                // Add new requests (ones we haven't displayed yet)
                data.requests.forEach((request, index) => {
                    const requestId = getRequestId(request, index);
                    currentRequestIds.add(requestId);
                    
                    // Only add if it's a new request
                    if (!displayedRequestIds.has(requestId)) {
                        const requestElement = createRequestItem(request, index);
                        
                        // Insert at the beginning if container has content, otherwise just append
                        if (container.firstChild && !container.firstChild.classList.contains('alert')) {
                            container.insertBefore(requestElement, container.firstChild);
                        } else {
                            container.appendChild(requestElement);
                        }
                        
                        // Initialize collapse for new element
                        setTimeout(() => {
                            const collapseElements = requestElement.querySelectorAll('.accordion-collapse');
                            collapseElements.forEach(element => {
                                if (!element._collapse) {
                                    new bootstrap.Collapse(element, {
                                        toggle: false
                                    });
                                }
                            });
                        }, 10);
                        
                        displayedRequestIds.add(requestId);
                    }
                });
                
                // Remove requests that are no longer in the list (if limit changed)
                // This handles the case where we keep only last 100 requests
                const existingCards = container.querySelectorAll('.request-item');
                existingCards.forEach(card => {
                    // Extract request ID from the first accordion ID in the card
                    const firstAccordion = card.querySelector('.accordion-collapse');
                    if (firstAccordion) {
                        const accordionId = firstAccordion.id;
                        // Accordion ID format: body-req-{timestamp}-{ip}-{index}-headers or body-req-{timestamp}-{ip}-{index}-body
                        // Extract the base request ID (everything after 'body-' or 'header-' and before the last '-headers' or '-body')
                        const match = accordionId.match(/^(?:body-|header-)req-(.+?)-(?:headers|body)$/);
                        if (match) {
                            const requestId = match[1]; // This is the baseRequestId without 'req-' prefix
                            if (!currentRequestIds.has(requestId)) {
                                card.remove();
                                displayedRequestIds.delete(requestId);
                            }
                        }
                    }
                });
                
                // Update displayedRequestIds to match current state
                displayedRequestIds = new Set(currentRequestIds);
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                // Only show error if container is empty
                const container = document.getElementById('dataContainer');
                if (container.children.length === 0 || container.querySelector('.alert-info')) {
                    container.innerHTML = '<div class="alert alert-danger text-center">Error loading requests. Please try again.</div>';
                }
            });
        }

        // Event listeners
        document.getElementById('clearData').addEventListener('click', () => {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'clear' })
            })
            .then(() => updateDisplay())
            .catch(error => console.error('Error clearing data:', error));
        });

        document.getElementById('toggleImages').addEventListener('click', () => {
            showImages = !showImages;
            updateDisplay();
        });

        // Initial load and periodic updates
        updateDisplay();
        // Update every 2 seconds (reduced frequency to be less disruptive)
        setInterval(updateDisplay, 2000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap tooltips after Bootstrap is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html> 