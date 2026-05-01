#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
UI_DIR="$ROOT_DIR/tests/ui"
COLLECTION_FILE="$ROOT_DIR/ai/generated-testcases.json"

BACKEND_HOST="${BACKEND_HOST:-127.0.0.1}"
BACKEND_PORT="${BACKEND_PORT:-8000}"
UI_HOST="${UI_HOST:-127.0.0.1}"
UI_PORT="${UI_PORT:-8765}"

BACKEND_URL="http://${BACKEND_HOST}:${BACKEND_PORT}"
UI_URL="http://${UI_HOST}:${UI_PORT}"

BACKEND_PID=""
UI_PID=""

cleanup() {
  local exit_code=$?
  if [[ -n "${BACKEND_PID}" ]] && kill -0 "${BACKEND_PID}" >/dev/null 2>&1; then
    kill "${BACKEND_PID}" >/dev/null 2>&1 || true
  fi
  if [[ -n "${UI_PID}" ]] && kill -0 "${UI_PID}" >/dev/null 2>&1; then
    kill "${UI_PID}" >/dev/null 2>&1 || true
  fi
  exit "${exit_code}"
}
trap cleanup EXIT INT TERM

echo "[1/3] Start Laravel backend server..."
(
  cd "$BACKEND_DIR"
  php artisan serve --host="$BACKEND_HOST" --port="$BACKEND_PORT"
) >"$ROOT_DIR/.run_backend.log" 2>&1 &
BACKEND_PID=$!

echo "[2/3] Start UI server..."
(
  cd "$UI_DIR"
  RAG_UI_HOST="$UI_HOST" RAG_UI_PORT="$UI_PORT" python3 server.py
) >"$ROOT_DIR/.run_ui.log" 2>&1 &
UI_PID=$!

echo "Waiting services to be ready..."
sleep 2

echo "[3/3] Run test suites..."
(
  cd "$BACKEND_DIR"
  php artisan test
)

if [[ ! -f "$COLLECTION_FILE" ]]; then
  echo "Collection file not found: $COLLECTION_FILE"
  echo "Generating default collection in dry-run mode..."
  (
    cd "$ROOT_DIR"
    python3 -m ai.src.rag_testgen.cli generate --src backend --out "$COLLECTION_FILE" --dry-run
  )
fi

(
  cd "$UI_DIR"
  RAG_UI_BASE_URL="$UI_URL" \
  RAG_COLLECTION_FILE="$COLLECTION_FILE" \
  npx playwright test e2e/ui.spec.js
)

echo "All done: backend tests + UI smoke passed."
