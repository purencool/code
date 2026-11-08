#!/bin/bash

set -e

echo "Running PHPUnit tests..."

# Execute PHPUnit using the binary installed by Composer
./vendor/bin/pest
