#!/bin/bash

# run-api-tests.sh
# Requires newman: npm install -g newman

set -e

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
COLLECTION="$BASE_DIR/collections/backend_api_full_suite.postman_collection.json"
ENVIRONMENT="$BASE_DIR/environments/local.postman_environment.json"

echo ">>> Khởi chạy bộ API Test Suite cho Backend..."

# Kiểm tra newman
if ! command -v newman &> /dev/null
then
    echo "Newman chưa được cài đặt. Đang thử cài đặt qua npm..."
    npm install -g newman
fi

# Chạy test
newman run "$COLLECTION" \
    -e "$ENVIRONMENT" \
    --reporters cli \
    --color on

echo ">>> Tất cả API test đã hoàn tất thành công!"
