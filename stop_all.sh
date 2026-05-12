#!/usr/bin/env bash
#
# Dừng process đang giữ port của tool tests/ui (giống run_all: UI_PORT hoặc RAG_TESTGEN_UI_PORT).

set -euo pipefail

PORT="${UI_PORT:-${RAG_TESTGEN_UI_PORT:-8765}}"

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

echo "Stopping tests/ui (port ${PORT})..."
kill_port_processes "$PORT"

echo "Done."
