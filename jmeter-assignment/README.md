# BÁO CÁO KIỂM THỬ HIỆU NĂNG BẰNG JMETER

## Thông tin chung

- Tên bài thực hành: Học công cụ kiểm thử JMeter và thực hành kiểm thử tải API
- Chủ đề: Kiểm thử hiệu năng API bằng Apache JMeter
- Ngày thực hiện: 03/06/2026
- Người thực hiện: `Nghiêm Đức Việt - 23010636`

## Tài liệu tham khảo

- Video yêu cầu của đề bài: https://www.youtube.com/watch?v=NTyY8wKSvik
- Apache JMeter User Manual: https://jmeter.apache.org/usermanual/get-started.html
- Apache JMeter Component Reference: https://jmeter.apache.org/usermanual/component_reference.html
- Apache JMeter Best Practices: https://jmeter.apache.org/usermanual/best-practices.html

## 1. Mục tiêu

Tìm hiểu và thực hành sử dụng Apache JMeter để kiểm thử hiệu năng API, bao gồm:

- Tạo Test Plan và Thread Group.
- Cấu hình HTTP Request để gửi request đến API.
- Sử dụng biến, CSV Data Set Config và Response Assertion.
- Chạy kiểm thử tải với nhiều người dùng ảo.
- Đọc các chỉ số cơ bản như response time, throughput, error rate.
- Xuất báo cáo kết quả sau khi chạy test.

## 2. Môi trường kiểm thử

| Thành phần    | Giá trị                                                          |
| ------------- | ---------------------------------------------------------------- |
| Công cụ       | Apache JMeter                                                    |
| API demo      | `https://jsonplaceholder.typicode.com`                           |
| Test plan     | [`jsonplaceholder-load-test.jmx`](jsonplaceholder-load-test.jmx) |
| Dữ liệu test  | [`data/post_ids.csv`](data/post_ids.csv)                         |
| Kiểu kiểm thử | Load testing cơ bản                                              |

## 3. Nội dung đã học về JMeter

### Test Plan

Test Plan là nơi chứa toàn bộ kịch bản kiểm thử. Trong bài này, Test Plan gồm Thread Group, CSV Data Set Config, HTTP Request, Response Assertion và các Listener.

![alt text](<CleanShot 2026-06-03 at 16.24.48@2x.png>)

### Thread Group

Thread Group dùng để mô phỏng người dùng ảo. Mỗi thread đại diện cho một user đang gửi request đến hệ thống.

Cấu hình trong bài:

- Number of Threads: `10`
- Ramp-up Period: `10` giây
- Loop Count: `3`

Nghĩa là JMeter sẽ tăng dần 10 user trong 10 giây, mỗi user lặp lại kịch bản 3 lần.

![alt text](<CleanShot 2026-06-03 at 16.24.48@2x-1.png>)

### HTTP Request

HTTP Request dùng để gửi yêu cầu đến API.

Request chính trong bài:

- Method: `GET`
- Protocol: `https`
- Server: `jsonplaceholder.typicode.com`
- Path: `/posts/${post_id}`

Biến `post_id` được lấy từ file CSV để mỗi lần request có thể gọi một bài viết khác nhau.

![alt text](<CleanShot 2026-06-03 at 16.26.08@2x.png>)

### CSV Data Set Config

CSV Data Set Config dùng để đọc dữ liệu từ file `data/post_ids.csv`.

Nội dung file CSV:

```csv
post_id
1
2
3
4
5
```

![alt text](<CleanShot 2026-06-03 at 16.26.25@2x.png>)

### Response Assertion

Response Assertion dùng để kiểm tra kết quả trả về của API. Trong bài này, assertion kiểm tra response có chứa trường `"id"` để xác nhận API trả về dữ liệu bài viết.

![alt text](<CleanShot 2026-06-03 at 16.26.43@2x.png>)

### Listener

Các Listener được sử dụng:

- View Results Tree: xem chi tiết từng request.
- Summary Report: xem tổng hợp số lượng request, thời gian phản hồi, throughput và tỉ lệ lỗi.

## 4. Kịch bản kiểm thử

### Kịch bản 1: Kiểm thử tải API lấy bài viết

- Tên kịch bản: Load test API lấy thông tin bài viết.
- Mục đích: Kiểm tra API có phản hồi ổn định khi có nhiều user gửi request cùng lúc.
- Số user ảo: `10`
- Ramp-up: `10` giây
- Số lần lặp: `3`
- Tổng số request dự kiến: `30`
- API: `GET https://jsonplaceholder.typicode.com/posts/${post_id}`

Kết quả mong đợi:

- Request gửi thành công.
- Response có status code `200`.
- Response có dữ liệu JSON của bài viết.
- Response time nằm trong mức chấp nhận được.
- Error rate bằng `0%` hoặc rất thấp.

![alt text](<CleanShot 2026-06-03 at 16.28.32@2x.png>)

![alt text](<CleanShot 2026-06-03 at 16.28.36@2x.png>)

## 5. Kết quả kiểm thử

Bảng kết quả ghi nhận từ Summary Report sau khi chạy test trên JMeter:

| Chỉ số                | Kết quả ghi nhận |
| --------------------- | ---------------- |
| Tổng số request       | 90               |
| Average response time | 115 ms           |
| Min response time     | 52 ms            |
| Max response time     | 288 ms           |
| Std. Dev.             | 76.30 ms         |
| Throughput            | 1.3 request/giây |
| Error rate            | 0.00%            |
| Received KB/sec       | 1.89             |
| Sent KB/sec           | 0.17             |
| Avg. Bytes            | 1494.3 bytes     |

## 6. Nhận xét

Qua bài thực hành, em đã nắm được cách tạo một Test Plan cơ bản trong JMeter và cách mô phỏng nhiều người dùng ảo cùng truy cập API. JMeter phù hợp để kiểm thử hiệu năng, kiểm tra độ ổn định và phát hiện các vấn đề liên quan đến thời gian phản hồi khi hệ thống có nhiều request.

So với Postman, JMeter tập trung hơn vào kiểm thử tải và đo hiệu năng. Postman thuận tiện để kiểm thử chức năng API từng request, còn JMeter phù hợp khi cần mô phỏng nhiều user và tổng hợp các chỉ số như throughput, response time và error rate.

## 7. Kết luận

Bài thực hành đã hoàn thành các yêu cầu chính:

- Đã tìm hiểu công cụ Apache JMeter.
- Đã tạo Test Plan để kiểm thử API.
- Đã sử dụng CSV Data Set Config, HTTP Request, Assertion và Listener.
- Đã viết báo cáo trong file `README.md`.
- Sản phẩm có thể đẩy lên Github Repo và nộp link repo theo yêu cầu của đề bài.
