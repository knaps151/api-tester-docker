<?php
return [
    'BASE_URL' => getenv('BASE_URL') ?: 'http://localhost:8080/',
    'LOG_DIRECTORY' => __DIR__ . '/logs', // Automatically resolves to the correct path in Docker
];
