#!/bin/bash
set -e

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

if [ -z "${AGENT_DIR:-}" ]; then
    if [ -f ".env" ]; then
        AGENT_DIR=$(grep -E '^AGENT_DIR=' .env | cut -d '=' -f 2 | tr -d '"'\'' ' || true)
    fi
    AGENT_DIR="${AGENT_DIR:-.agent}"
fi

if [ -z "${AGENT_TEMPLATE:-}" ] && [ -f "${AGENT_DIR}/.env" ]; then
    AGENT_TEMPLATE=$(grep -E '^AGENT_TEMPLATE=' "${AGENT_DIR}/.env" | cut -d '=' -f 2 | tr -d '"'\'' ' || true)
fi
AGENT_TEMPLATE="${AGENT_TEMPLATE:-php}"

TARGET_DIR="${AGENT_DIR}/${AGENT_TEMPLATE}"
CONFIG_FILE="${TARGET_DIR}/config.json"
[ ! -f "$CONFIG_FILE" ] && CONFIG_FILE="${AGENT_DIR}/config.json"

mkdir -p "$TARGET_DIR"

if [ -f "$CONFIG_FILE" ]; then
    php pncagent run test 2>&1 | tee "${TARGET_DIR}/build_temp.log"
else
    echo "Notice: $CONFIG_FILE not found. Running ./vendor/bin/pest directly..."
    ./vendor/bin/pest 2>&1 | tee "${TARGET_DIR}/build_temp.log"
fi

if [ ${PIPESTATUS[0]} -ne 0 ]; then
    echo "[$(date)] Build aborted due to test failures:" > "${TARGET_DIR}/build.log"
    cat "${TARGET_DIR}/build_temp.log" >> "${TARGET_DIR}/build.log"
    rm -f "${TARGET_DIR}/build_temp.log"
    echo "Log saved to: ${TARGET_DIR}/build.log"
    exit 1
fi

rm -f "${TARGET_DIR}/build_temp.log"
