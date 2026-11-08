#!/bin/bash

set -e

echo "=================================================="
echo "Starting Installation..."
echo "=================================================="
cd "$(dirname "$0")/.."

REQUIRED_PHP_VERSION="8.1.0"
echo "Checking PHP version (requires $REQUIRED_PHP_VERSION+)..."

if ! php -r "exit(version_compare(PHP_VERSION, '$REQUIRED_PHP_VERSION', '>=') ? 0 : 1);"; then
    echo "Error: PHP version $REQUIRED_PHP_VERSION or higher is required."
    echo "Current PHP version is: $(php -r 'echo PHP_VERSION;')"
    exit 1
fi

echo "PHP version is compatible."
echo "=================================================="

echo "Running composer install..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "=================================================="
echo "Running build script..."

bash  ./scripts/build.sh

echo "=================================================="
echo "Installation completed successfully."
