#!/usr/bin/env sh
# Cập nhật DB_HOST = IPv4 Tailscale của bt-server vào backend/.env và docker/.env
# Yêu cầu: Tailscale đang chạy và máy resolve được hostname peer (vd: bt-server).

set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOSTNAME="${1:-bt-server}"

if ! command -v tailscale >/dev/null 2>&1; then
  echo "Không tìm thấy lệnh tailscale." >&2
  exit 1
fi

IP="$(tailscale ip -4 "$HOSTNAME" 2>/dev/null || true)"
if [ -z "$IP" ]; then
  echo "Không lấy được IPv4 cho peer '$HOSTNAME'. Bật Tailscale và kiểm tra tên máy (tailscale status)." >&2
  exit 1
fi

for f in "$ROOT/backend/.env" "$ROOT/docker/.env"; do
  if [ -f "$f" ]; then
    if grep -q '^DB_HOST=' "$f"; then
      sed -i.bak "s/^DB_HOST=.*/DB_HOST=$IP/" "$f" && rm -f "$f.bak"
    else
      printf '\nDB_HOST=%s\n' "$IP" >>"$f"
    fi
    echo "Đã ghi DB_HOST=$IP vào $f"
  fi
done

echo "Xong. Chạy lại: cd docker && docker compose up -d && docker exec ecm-api-app php artisan config:clear"
