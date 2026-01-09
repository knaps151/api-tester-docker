FROM php:8.2-apache

# Note: curl extension is already included in php:apache image
# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create logs directory and set permissions
RUN mkdir -p /var/www/html/logs && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod 664 /var/www/html/templates.json 2>/dev/null || true && \
    chmod 755 /var/www/html/logs

# Create templates.json if it doesn't exist
RUN touch /var/www/html/templates.json && \
    chown www-data:www-data /var/www/html/templates.json && \
    chmod 664 /var/www/html/templates.json

# Expose port 80
EXPOSE 80

# Apache runs in foreground by default in this image
CMD ["apache2-foreground"]

