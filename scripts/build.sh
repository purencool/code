#!/bin/bash
set -e

# Anchor working directory to project root (one level up from scripts/)
cd "$(dirname "$0")/.."

echo "=================================================="
echo "1. Running Tests..."
echo "=================================================="

rm -Rf .agent
mkdir -p .agent
set +e

# Run tests via pncagent run test; if config.json is not initialized yet, fall back to pest
if [ -f ".agent/config.json" ]; then
    php pncagent run test 2>&1 | tee .agent/build_temp.log
    TEST_EXIT_CODE=${PIPESTATUS[0]}
else
    echo "Notice: .agent/config.json not found. Running ./vendor/bin/pest directly..."
    ./vendor/bin/pest 2>&1 | tee .agent/build_temp.log
    TEST_EXIT_CODE=${PIPESTATUS[0]}
fi

set -e

if [ $TEST_EXIT_CODE -ne 0 ]; then
    echo "=================================================="
    echo "Tests failed! Aborting the build process."
    echo "[$(date)] Build aborted due to test failures:" > .agent/build.log
    cat .agent/build_temp.log >> .agent/build.log
    rm -f .agent/build_temp.log
    echo "Log saved to: .agent/build.log"
    echo "=================================================="
    exit 1
fi

rm -f .agent/build_temp.log
echo "Tests passed!"

# Ensure dev dependencies are restored if the build errors out or is aborted
restore_dev_dependencies() {
    echo "Restoring local development dependencies..."
    composer install --quiet || true
}
trap restore_dev_dependencies EXIT

echo "=================================================="
echo "2. Optimizing Vendor & Pruning Dev Packages..."
echo "=================================================="

# Strip Pest, PHPUnit, and Mockery from the build archive
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-progress \
    --no-interaction \
    --quiet


# Ensure box.json has Gzip compression and blacklists enabled
if [ ! -f "box.json" ]; then
    cat << 'BOXEOF' > box.json
{
    "chmod": "0755",
    "compression": "GZ",
    "blacklist": [
        "vendor/**/tests",
        "vendor/**/Tests",
        "vendor/**/test",
        "vendor/**/Test",
        "vendor/**/phpunit.xml*",
        "vendor/**/*.md",
        "vendor/**/*.rst",
        "vendor/**/.github",
        "vendor/**/docs",
        "vendor/**/doc"
    ]
}
BOXEOF
fi

echo "=================================================="
echo "3. Compiling PncAgent..."
echo "=================================================="

# Clean prior build artifacts
rm -rf builds/pncagent builds/php

# Compile binary as 'pncagent' with automated build-version to avoid interactive prompts
BUILD_VER="$(date +%Y.%m.%d-%H%M)"
php pncagent app:build pncagent --build-version="${BUILD_VER}"

# Safeguard: if Box compiled it as 'builds/php', rename it to 'builds/pncagent'
if [ ! -f "builds/pncagent" ] && [ -f "builds/php" ]; then
    mv builds/php builds/pncagent
fi

if [ ! -f "builds/pncagent" ]; then
    echo "ERROR: Compilation failed. 'builds/pncagent' was not created."
    exit 1
fi

echo "Moving compiled binary to ~/.local/bin..."
mkdir -p "${HOME}/.local/bin"
cp -f builds/pncagent "${HOME}/.local/bin/pncagent"
chmod +x "${HOME}/.local/bin/pncagent"

# Also sync to /usr/local/bin if writable
if [ -w "/usr/local/bin" ]; then
    cp -f builds/pncagent /usr/local/bin/pncagent
    chmod +x /usr/local/bin/pncagent
fi

# Refresh shell command cache
hash -r 2>/dev/null || rehash 2>/dev/null || true

echo "=================================================="
echo "Optimized Binary Details:"
echo "=================================================="
ls -ahl "${HOME}/.local/bin/pncagent" | awk '{print "Path: "$9 "\nSize: "$5}'

echo "=================================================="
echo "4. Initializing Workspace & Restoring Dev..."
echo "=================================================="
"${HOME}/.local/bin/pncagent" --version
"${HOME}/.local/bin/pncagent" init pncagent

# Restore dev dependencies for active development
restore_dev_dependencies
trap - EXIT

echo "Build complete. You can now run 'pncagent' from any directory."
