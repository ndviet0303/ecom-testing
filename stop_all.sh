#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

BACKEND_PORT="${BACKEND_PORT:-8000}"
UI_PORT="${UI_PORT:-8765}"

kill_port_processes() {
  local port="$1"
  local pids
  pids="$(lsof -ti tcp:"$port" 2>/dev/null || true)"
  if [[ -n "$pids" ]]; then
    echo "Stopping processes on port $port: $pids"
    # shellcheck disable=SC2086
    kill $pids 2>/dev/null || true
  else
    echo "No process found on port $port"
  fi
}

echo "Stopping services started by this project..."
kill_port_processes "$BACKEND_PORT"
kill_port_processes "$UI_PORT"

echo "Done."
