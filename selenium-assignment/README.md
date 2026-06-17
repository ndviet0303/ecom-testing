# Báo Cáo Bài Tập: Kiểm Thử Tự Động Với Selenium Trên Ecom.ziet.dev

## 1. Giới thiệu & Lý thuyết cơ bản về Selenium

### 1.1. Selenium là gì?
**Selenium** là một bộ công cụ kiểm thử tự động mã nguồn mở phổ biến dành cho các ứng dụng Web. Selenium cho phép giả lập các thao tác của người dùng trên trình duyệt (click, nhập văn bản, điều hướng,...) một cách tự động để kiểm tra chất lượng phần mềm.

Trong bài tập này, chúng ta sử dụng **Selenium WebDriver** (phiên bản 4.x) kết hợp với ngôn ngữ **Python** để viết kịch bản kiểm thử.

### 1.2. Các khái niệm cốt lõi đã áp dụng
1. **WebDriver**: Cung cấp giao diện để điều khiển trình duyệt Chrome. Trong Selenium 4+, công cụ **Selenium Manager** tự động tải xuống và cấu hình ChromeDriver tương thích với trình duyệt hiện tại.
2. **Locators (Bộ định vị)**:
   - `By.CSS_SELECTOR`: Định vị ô nhập email, password (`input[type='email']`, `input[type='password']`).
   - `By.XPATH`: Tìm các thành phần phức tạp hoặc có chứa text cụ thể (ví dụ: tìm kiếm nút thêm vào giỏ hàng của sản phẩm còn hàng bằng `//div[contains(@class, 'product-card') and not(contains(@class, 'out-of-stock'))]//button[contains(@class, 'action-btn')]`).
   - `By.CLASS_NAME`: Tìm badge giỏ hàng (`cart-badge`), lời chào người dùng (`user-greeting`).
3. **Waits (Cơ chế chờ đợi)**: Sử dụng **Explicit Wait** (`WebDriverWait` kết hợp với `expected_conditions`) để chờ các API và phần tử giao diện hiển thị xong, giảm thiểu tối đa tình trạng "Flaky Tests" (kiểm thử chạy lúc pass lúc fail).
4. **JavaScript Execution**: Sử dụng `driver.execute_script("arguments[0].click();", element)` cho các nút bấm nhạy cảm (như nút Thêm vào giỏ hoặc Đăng xuất) nhằm tránh lỗi click bị chặn bởi các hiệu ứng chuyển động (CSS transitions/animations) của Vue.

---

## 2. Kịch bản Kiểm thử (Test Cases Scenario)

Bài tập được thực hiện trực tiếp trên website dự án: [https://ecom.ziet.dev/](https://ecom.ziet.dev/)

### Bảng Kịch bản Chi tiết

| Mã TC | Tên ca kiểm thử | Bộ định vị (Locators) & Thao tác | Dữ liệu đầu vào | Kết quả mong đợi | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TC01** | Đăng nhập tài khoản | - Ô Email: `By.CSS_SELECTOR("input[type='email']")` <br>- Ô Mật khẩu: `By.CSS_SELECTOR("input[type='password']")`<br>- Nút Đăng nhập: `By.CSS_SELECTOR("button[type='submit']")`<br>-> Clear và nhập thông tin rồi Click | Email: `customer@ziet.dev`<br>Password: `password` | - Đăng nhập thành công.<br>- Chuyển hướng tới trang `/checkout`.<br>- Lời chào hiển thị: `"Hi, Customer Updated"`. | **PASS** |
| **TC02** | Tìm kiếm sản phẩm | - Ô Tìm kiếm: `By.CSS_SELECTOR(".search-bar input")`<br>-> Nhập từ khoá và chờ debounce | Từ khóa: `Ryzen` | - Trang hiển thị danh sách sản phẩm khớp từ khoá.<br>- Tìm thấy card sản phẩm chứa tên "AMD Ryzen...". | **PASS** |
| **TC03** | Thêm sản phẩm vào giỏ | - Nút mua: `By.XPATH` tìm nút `action-btn` của sản phẩm còn hàng đầu tiên.<br>- Badge giỏ hàng: `By.CLASS_NAME("cart-badge")`<br>-> Click nút và kiểm tra số lượng | Không có | - Icon giỏ hàng hiển thị số sản phẩm tăng lên `>= 1`. | **PASS** |
| **TC04** | Đăng xuất thành công | - Nút Logout: `By.XPATH` tìm nút `<button>` bên trong `.user-actions` của Navbar.<br>-> Click qua JS để đăng xuất. | Không có | - Đăng xuất thành công.<br>- URL quay lại trang chủ khách hàng.<br>- Thanh Navbar không còn hiển thị lời chào người dùng. | **PASS** |

---

## 3. [Phát hiện Bug] Phân tích lỗi API Checkout (HTTP 422)

Trong quá trình xây dựng kịch bản kiểm thử cho nút **Xác nhận đặt hàng** tại trang Checkout, bộ kiểm thử phát hiện lỗi hệ thống ở luồng Thanh toán:

- **Mô tả lỗi**: Khi click vào nút đặt hàng, hệ thống không chuyển hướng sang trang thành công mà giữ nguyên trạng thái.
- **Dữ liệu gỡ lỗi (Browser Console Log)**:
  ```text
  https://ecom.ziet.dev/api/v1/checkout - Failed to load resource: the server responded with a status of 422 ()
  ```
- **Nguyên nhân kỹ thuật**:
  - Ở Backend (`app/Http/Controllers/Api/CheckoutController.php`), API `/api/v1/checkout` bắt buộc phải có trường `shipping_zone_id`:
    `'shipping_zone_id' => ['required', 'integer', 'exists:shipping_zones,id']`
  - Ở Frontend (`views/Checkout.vue`), khi gửi request đặt hàng bằng Axios, payload được định nghĩa như sau:
    ```javascript
    const response = await axios.post("/api/v1/checkout", {
        fulfillment_method: fulfillmentMethod.value,
        shipping_address_id: fulfillmentMethod.value === "shipping" ? selectedAddressId.value : null,
        weight_grams: fulfillmentMethod.value === "shipping" ? 15000 : 0,
        tax_rate_basis_points: 0,
        coupon_code: appliedCoupon.value?.code || null,
        payment_method: "sepay_qr",
        customer_note: note.value,
    });
    ```
    Trường `shipping_zone_id` hoàn toàn bị thiếu trong payload của frontend, dẫn đến việc Laravel trả về lỗi validation **422 Unprocessable Entity**.
- **Khuyến nghị khắc phục**:
  - *Frontend*: Cần cập nhật `Checkout.vue` để lấy dữ liệu shipping zone từ API và gửi kèm `shipping_zone_id` khi gọi API checkout.
  - *Backend*: Cần tối ưu điều kiện validation, cho phép `shipping_zone_id` nhận giá trị `nullable` nếu hình thức nhận hàng là `pickup` (Tự đến lấy).

---

## 4. Hướng dẫn cài đặt và Chạy thử nghiệm

### 4.1. Yêu cầu hệ thống
- Máy tính đã cài đặt **Python 3.8+**.
- Trình duyệt **Google Chrome** đã được cài đặt.

### 4.2. Cài đặt các thư viện cần thiết
1. Di chuyển vào thư mục bài tập Selenium:
   ```bash
   cd "selenium-assignment"
   ```
2. Cài đặt Selenium thông qua pip:
   ```bash
   pip install -r requirements.txt
   ```

### 4.3. Chạy kịch bản kiểm thử tự động
Chạy script test bằng Python:
```bash
python test_ecom.py
```

---

## 5. Kết quả Thực thi mẫu trên Terminal

Kết quả chạy thực tế của bộ test tự động trên trình duyệt Chrome Headless:

```text
=== BẮT ĐẦU CHẠY THỬ NGHIỆM AUTOMATION TESTING TRÊN ECOM.ZIET.DEV ===

Đang thực hiện Test Case 1: Đăng nhập vào hệ thống...
[TC01 - Đăng nhập tài khoản]: PASS - Đăng nhập thành công. Lời chào hiển thị: 'Hi, Customer Updated'

Đang thực hiện Test Case 2: Tìm kiếm sản phẩm...
[TC02 - Tìm kiếm sản phẩm]: PASS - Tìm thấy sản phẩm phù hợp: 'AMD Ryzen 5 7600'

Đang thực hiện Test Case 3: Thêm sản phẩm vào giỏ hàng...
[TC03 - Thêm sản phẩm vào giỏ]: PASS - Số lượng sản phẩm trong giỏ hàng hiển thị: 1

Đang thực hiện Test Case 4: Đăng xuất khỏi hệ thống...
[TC04 - Đăng xuất thành công]: PASS - Session đã được xóa sạch và quay lại trang chủ khách.

=== HOÀN THÀNH CHẠY THỬ NGHIỆM TRÊN ECOM.ZIET.DEV ===
```
