# Báo Cáo Môn Kiểm Thử Phần Mềm

## Đề Tài

**Kiểm thử hệ thống ECM Project** - ứng dụng web gồm backend Laravel, frontend Vue và bộ công cụ hỗ trợ sinh/chạy test case API.

## Thông Tin Nhóm

| STT | Họ và tên    | MSSV   | Vai trò                                                         |
| --- | ------------ | ------ | --------------------------------------------------------------- |
| 1   | Thành viên 1 | MSSV 1 | Trưởng nhóm, Thiết kế và thực thi test backend/API, frontend/UI |
| 2   | Thành viên 2 | MSSV 2 |                                                                 |
| 3   | Thành viên 3 | MSSV 3 |                                                                 |

## Mục Tiêu Báo Cáo

- Tìm hiểu quy trình kiểm thử phần mềm trên một ứng dụng web thực tế.
- Xây dựng và thực thi test case cho các chức năng backend, API và giao diện.
- Sử dụng PHPUnit, Postman và Playwright để kiểm thử tự động.
- Ứng dụng module RAG Test Generator để hỗ trợ sinh test case API từ source Laravel.
- Tổng hợp kết quả, đánh giá lỗi và đề xuất hướng cải thiện chất lượng phần mềm.

## Phạm Vi Kiểm Thử

Báo cáo tập trung vào các nhóm kiểm thử sau:

- **Unit Test**: kiểm tra logic nghiệp vụ riêng lẻ trong backend.
- **Feature/API Test**: kiểm tra các endpoint và luồng xử lý chính của hệ thống.
- **UI Test**: kiểm tra thao tác người dùng trên giao diện web test runner.
- **Postman Test**: chạy bộ collection API với environment local/dev.
- **AI-assisted Test Generation**: sinh testcase API từ route, validation, migration và seeder.

## Công Nghệ Sử Dụng

| Thành phần         | Công nghệ                                      |
| ------------------ | ---------------------------------------------- |
| Backend            | Laravel 12, PHP 8.2+, PHPUnit                  |
| Frontend           | Vue 3, Vite, Pinia, Vue Router                 |
| API Testing        | Postman collection, Laravel Feature Test       |
| UI Testing         | Playwright                                     |
| AI Test Generator  | Python, RAG, Ollama/OpenAI-compatible provider |
| Quản lý môi trường | `.env`, Docker compose                         |

## Cấu Trúc Thư Mục

```text
.
├── ai/                         # Module RAG Test Generator và testcase được sinh
├── backend/                    # Source Laravel và PHPUnit test
├── docker/                     # Cấu hình Docker
├── frontend/                   # Source Vue/Vite
├── tests/
│   ├── api/                    # Postman collections, environments, test data
│   └── ui/                     # Playwright UI test runner
├── run_all.sh                  # Chạy tool UI test runner local
└── stop_all.sh                 # Dừng tool UI test runner local
```

## Các Chức Năng Được Kiểm Thử

- Đăng ký, đăng nhập và phân quyền API.
- Quản lý sản phẩm, giỏ hàng, checkout và đơn hàng.
- Coupon, shipping, tax, inventory và tính tổng tiền.
- Kiểm tra trạng thái đơn hàng, bảo hành, đổi trả và webhook thanh toán.
- Kiểm tra các API được sinh testcase từ module RAG.
- Kiểm tra giao diện chạy test và hiển thị kết quả.

## Hướng Dẫn Cài Đặt

### 1. Cài đặt backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

### 2. Cài đặt frontend

```bash
cd frontend
npm install
```

### 3. Cài đặt UI test runner

```bash
cd tests/ui
npm install
npx playwright install
```

## Hướng Dẫn Chạy Hệ Thống

### Chạy backend Laravel

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

### Chạy frontend Vue

```bash
cd frontend
npm run dev
```

### Chạy tool UI test runner

```bash
./run_all.sh
```

Mặc định tool chạy ở cổng `8765`. Dừng tool:

```bash
./stop_all.sh
```

## Hướng Dẫn Chạy Kiểm Thử

### 1. Chạy toàn bộ test backend

```bash
cd backend
php artisan test
```

### 2. Chạy Unit Test

```bash
cd backend
php artisan test --testsuite=Unit
```

### 3. Chạy Feature/API Test

```bash
cd backend
php artisan test --testsuite=Feature
```

### 4. Chạy UI Test với Playwright

```bash
cd tests/ui
npx playwright test
```

### 5. Chạy Postman Collection

Có thể import các file trong thư mục sau vào Postman:

```text
tests/api/collections/
tests/api/environments/
```

Các collection chính:

- `backend_api_full_suite.postman_collection.json`
- `ecm.postman_collection.json`
- `generated-from-rag.postman_collection.json`

## RAG Test Generator

Module `ai/src/rag_testgen` được dùng để scan API Laravel và tạo testcase JSON cho API.

### Scan route

```bash
python3.14 -m ai.src.rag_testgen.cli scan --src backend --pretty
```

### Sinh testcase dạng dry-run

```bash
python3.14 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --dry-run
```

### Sinh testcase với Ollama

```bash
python3.14 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --provider ollama \
  --model qwen2.5-coder:7b
```

### Sinh testcase với OpenAI-compatible provider

```bash
python3.14 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --provider openai \
  --model gpt-4o-mini
```

Biến môi trường cần thiết:

- `OLLAMA_BASE_URL`: mặc định `http://localhost:11434`.
- `OPENAI_API_KEY`: bắt buộc khi dùng provider OpenAI-compatible.
- `OPENAI_BASE_URL`: tùy chọn, mặc định `https://api.openai.com/v1`.

## Mẫu Bảng Test Case

| Mã TC | Chức năng | Điều kiện đầu vào | Bước thực hiện        | Kết quả mong đợi                | Kết quả thực tế | Trạng thái |
| ----- | --------- | ----------------- | --------------------- | ------------------------------- | --------------- | ---------- |
| TC01  | Đăng nhập | Tài khoản hợp lệ  | Gửi request login     | Trả về token và thông tin user  |                 |            |
| TC02  | Giỏ hàng  | Sản phẩm tồn kho  | Thêm sản phẩm vào giỏ | Giỏ hàng cập nhật đúng số lượng |                 |            |
| TC03  | Checkout  | Giỏ hàng hợp lệ   | Tạo đơn hàng          | Đơn hàng được tạo thành công    |                 |            |

## Kết Quả Kiểm Thử

| Nhóm test          | Công cụ            | Đường dẫn                     | Ghi chú                          |
| ------------------ | ------------------ | ----------------------------- | -------------------------------- |
| Unit Test          | PHPUnit            | `backend/tests/Unit`          | Kiểm tra logic nghiệp vụ         |
| Feature/API Test   | PHPUnit            | `backend/tests/Feature`       | Kiểm tra endpoint và workflow    |
| API Collection     | Postman            | `tests/api/collections`       | Kiểm tra API theo collection     |
| UI Test            | Playwright         | `tests/ui/e2e`                | Kiểm tra giao diện test runner   |
| Generated Testcase | RAG Test Generator | `ai/generated-testcases.json` | Testcase API được sinh từ source |

## Phân Công Công Việc

| Thành viên   | Công việc                                                              |
| ------------ | ---------------------------------------------------------------------- |
| Thành viên 1 | Lập kế hoạch kiểm thử, tổng hợp README/báo cáo, kiểm tra kết quả cuối  |
| Thành viên 2 | Viết và chạy Unit Test, Feature Test cho backend/API                   |
| Thành viên 3 | Chuẩn bị Postman collection, Playwright UI test và ghi nhận minh chứng |

## Kết Luận

Dự án mô phỏng quy trình kiểm thử phần mềm cho ứng dụng web gồm backend, frontend và API. Nhóm đã áp dụng nhiều mức kiểm thử khác nhau, bao gồm Unit Test, Feature/API Test, Postman Test và UI Test. Module RAG Test Generator giúp tăng tốc việc tạo testcase API, đồng thời cung cấp thêm ngữ cảnh về route, validation, schema và dữ liệu mẫu.

## Ghi Chú

- Cập nhật tên thành viên, MSSV và kết quả thực tế trước khi nộp báo cáo.
- Nếu môi trường không có `python3.14`, có thể thử bằng `python3` nếu package tương thích.
- Nên lưu ảnh chụp màn hình kết quả test trong báo cáo chính hoặc phụ lục.
