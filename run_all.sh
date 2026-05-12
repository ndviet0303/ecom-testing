#!/usr/bin/env bash
#
# Chỉ bật tool tests/ui. Playwright smoke: bấm nút trên giao diện (server gọi npx playwright).
# API production trên VPS — nhập Base URL trong tab API khi cần.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UI_DIR="$ROOT_DIR/tests/ui"
COLLECTION_FILE="$ROOT_DIR/ai/generated-testcases.json"
PYTHON_BIN="${PYTHON_BIN:-$(command -v python3.14 || command -v python3)}"

UI_PORT="${UI_PORT:-8765}"

if [[ ! -f "$COLLECTION_FILE" ]]; then
  echo "Collection file not found: $COLLECTION_FILE"
  echo "Generating default collection (dry-run)..."
  (
    cd "$ROOT_DIR"
    "$PYTHON_BIN" -m ai.src.rag_testgen.cli generate --src backend --out "$COLLECTION_FILE" --dry-run
  )
fi

echo "Khởi động tool — URL sẽ in ngay dưới. Thoát: Ctrl+C. Playwright: dùng nút trên UI."
echo ""

cd "$UI_DIR"
export RAG_TESTGEN_UI_PORT="$UI_PORT"
exec "$PYTHON_BIN" server.py
