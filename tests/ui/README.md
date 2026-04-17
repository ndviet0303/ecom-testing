# Automation Tester UI

Web app local cho demo automation testing, đặt trong `tests/ui`.

## Chạy

```bash
cd tests/ui
python3 server.py
```

Mặc định app sẽ thử chạy tại:

```text
http://127.0.0.1:8765
```

Nếu cổng này đang bận, server sẽ tự nhảy sang cổng trống tiếp theo như `8766`, `8767`, ...

## Chức năng

- generate collection testcase từ `backend` + `frontend`
- chọn file `collection testcase` JSON
- preview collection đã load
- chạy `API automation` theo collection đã chọn
- chạy `UI automation` bằng Playwright smoke cho chính web app này
- xem log và kết quả pass/fail ngay trên trình duyệt

## Ghi chú

- UI này dùng Python standard library, không cần cài thêm dependency
- nó gọi trực tiếp CLI trong thư mục `ai`
- dependency Playwright nằm riêng trong `tests/ui/package.json`
- để demo nhanh nên dùng `Dry run` khi generate collection
- để chạy API test thật, backend của bạn phải đang chạy ở base URL tương ứng
