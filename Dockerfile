# Step 1: Use the OFFICIAL public PHP image from Docker Hub
# Comes with Apache web server pre-installed and configured
FROM php:8.3-apache

# Step 2: Install the helper script for PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Use the script to install required PHP extensions
# It will automatically install system dependencies
RUN install-php-extensions redis zip

# Step 3: Copy official Composer binary into container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Step 4: Set the working directory in container
WORKDIR /var/www/html

# Step 5: Copy ONLY the composer.json file
# to install dependencies without needing a local lock file
COPY composer.json ./

# Step 6: Run composer install
# This reads composer.json, installs dependencies, and creates a composer.lock file
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Step 7: Copy the rest of app source code into container
COPY . .
