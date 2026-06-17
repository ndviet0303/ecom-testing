import time
import os
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options

# Cấu hình màu sắc hiển thị trên Terminal để báo cáo kết quả đẹp mắt hơn
class Colors:
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    END = '\033[0m'
    BOLD = '\033[1m'

def log_test_case(name, passed, message=""):
    status = f"{Colors.GREEN}PASS{Colors.END}" if passed else f"{Colors.RED}FAIL{Colors.END}"
    detail = f" - {message}" if message else ""
    print(f"{Colors.BOLD}[{name}]{Colors.END}: {status}{detail}")

def take_screenshot(driver, filename):
    """Hàm bổ trợ chụp ảnh màn hình và lưu vào thư mục screenshots"""
    dir_path = "selenium-assignment/screenshots"
    os.makedirs(dir_path, exist_ok=True)
    full_path = os.path.join(dir_path, filename)
    driver.save_screenshot(full_path)
    print(f"   -> [SCREENSHOT] Đã chụp giao diện lưu tại: {full_path}")

def run_tests():
    print(f"\n{Colors.BLUE}{Colors.BOLD}=== BẮT ĐẦU CHẠY THỬ NGHIỆM AUTOMATION TESTING TRÊN ECOM.ZIET.DEV ==={Colors.END}\n")
    
    # Thiết lập Chrome Options
    chrome_options = Options()
    chrome_options.add_argument("--headless") # Chạy headless trên server
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--start-maximized")
    
    # Khởi tạo WebDriver (Selenium 4+ tự động tải và quản lý ChromeDriver)
    driver = webdriver.Chrome(options=chrome_options)
    wait = WebDriverWait(driver, 15) # Thời gian chờ tối đa 15 giây cho Explicit Wait
    
    try:
        # ----------------------------------------------------
        # TEST CASE 1: Đăng nhập thành công với tài khoản hợp lệ
        # ----------------------------------------------------
        print("Đang thực hiện Test Case 1: Đăng nhập vào hệ thống...")
        driver.get("https://ecom.ziet.dev/login")
        
        # Chụp ảnh trang đăng nhập trước khi điền thông tin
        take_screenshot(driver, "01_login_page.png")
        
        # Tìm ô nhập email và password
        email_field = wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "input[type='email']")))
        password_field = driver.find_element(By.CSS_SELECTOR, "input[type='password']")
        login_button = driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        
        # Xoá trường và nhập thông tin đăng nhập mặc định của khách hàng
        email_field.clear()
        email_field.send_keys("customer@ziet.dev")
        
        password_field.clear()
        password_field.send_keys("password")
        login_button.click()
        
        # Sau khi đăng nhập thành công, hệ thống chuyển hướng sang /checkout
        wait.until(EC.url_contains("/checkout"))
        
        # Xác nhận đăng nhập thành công bằng cách kiểm tra hiển thị lời chào trên header
        user_greeting = wait.until(EC.presence_of_element_located((By.CLASS_NAME, "user-greeting")))
        
        tc1_passed = "hi, " in user_greeting.text.lower()
        log_test_case("TC01 - Đăng nhập tài khoản", tc1_passed, f"Đăng nhập thành công. Lời chào hiển thị: '{user_greeting.text}'")
        
        # Chụp ảnh giao diện sau khi đăng nhập thành công (Trang Checkout)
        take_screenshot(driver, "02_login_success.png")
        
        if not tc1_passed:
            return

        # ----------------------------------------------------
        # TEST CASE 2: Tìm kiếm sản phẩm trong danh mục
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 2: Tìm kiếm sản phẩm...")
        driver.get("https://ecom.ziet.dev/products")
        
        # Chờ thanh tìm kiếm xuất hiện
        search_input = wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".search-bar input")))
        
        # Tìm kiếm CPU Ryzen
        search_input.clear()
        search_input.send_keys("Ryzen")
        
        # Chờ 2 giây để debounce và kết quả tìm kiếm hiển thị
        time.sleep(2)
        
        # Xác minh sản phẩm tìm kiếm hiển thị chứa chữ "Ryzen"
        product_names = driver.find_elements(By.CLASS_NAME, "product-name")
        tc2_passed = False
        found_product_name = ""
        for p in product_names:
            if "ryzen" in p.text.lower():
                tc2_passed = True
                found_product_name = p.text
                break
        
        # Chụp ảnh trang sản phẩm hiển thị kết quả tìm kiếm Ryzen
        take_screenshot(driver, "03_search_ryzen.png")
        log_test_case("TC02 - Tìm kiếm sản phẩm", tc2_passed, f"Tìm thấy sản phẩm phù hợp: '{found_product_name}'")
        
        if not tc2_passed:
            return

        # ----------------------------------------------------
        # TEST CASE 3: Thêm sản phẩm vào giỏ hàng
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 3: Thêm sản phẩm vào giỏ hàng...")
        
        # Lấy nút "Thêm vào giỏ hàng" (action-btn) của sản phẩm Ryzen vừa tìm thấy
        add_to_cart_btn = wait.until(EC.presence_of_element_located((By.XPATH, "//div[contains(@class, 'product-card') and not(contains(@class, 'out-of-stock'))]//button[contains(@class, 'action-btn')]")))
        driver.execute_script("arguments[0].click();", add_to_cart_btn)
        
        # Chờ và kiểm tra badge giỏ hàng hiển thị ít nhất 1 sản phẩm
        cart_badge = wait.until(EC.presence_of_element_located((By.CLASS_NAME, "cart-badge")))
        cart_count = cart_badge.text
        
        tc3_passed = int(cart_count) >= 1
        log_test_case("TC03 - Thêm sản phẩm vào giỏ", tc3_passed, f"Số lượng sản phẩm trong giỏ hàng hiển thị: {cart_count}")
        
        # Chụp ảnh giỏ hàng hiển thị badge số lượng sản phẩm mới cập nhật
        take_screenshot(driver, "04_added_to_cart.png")
        
        if not tc3_passed:
            return

        # ----------------------------------------------------
        # TEST CASE 4: Minh họa phát hiện lỗi đặt hàng (Checkout Defect Validation)
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 4: Kiểm thử lỗi Xác nhận Đặt hàng (Bug Demonstration)...")
        driver.get("https://ecom.ziet.dev/checkout")
        
        # Chọn hình thức "Tự đến lấy" (pickup) để kích hoạt nút Xác nhận Đặt hàng
        pickup_option = wait.until(EC.presence_of_element_located((By.XPATH, "//div[contains(@class, 'zone-item') and .//strong[text()='Tự đến lấy']]")))
        driver.execute_script("arguments[0].click();", pickup_option)
        time.sleep(3)
        
        # Tìm nút đặt hàng
        confirm_btn = wait.until(EC.presence_of_element_located((By.XPATH, "//div[contains(@class, 'order-summary-sidebar')]//button[contains(@class, 'btn-primary')]")))
        wait.until(lambda d: confirm_btn.is_enabled())
        
        # Click đặt hàng qua Javascript
        driver.execute_script("arguments[0].click();", confirm_btn)
        
        # Chờ thông điệp báo lỗi từ Toast (lỗi 422 validation trường shipping_zone_id) xuất hiện trên màn hình
        error_toast = wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".toast-item.error")))
        error_message = error_toast.find_element(By.CLASS_NAME, "toast-message").text
        
        # Chụp ảnh minh chứng lỗi xuất hiện trên giao diện
        take_screenshot(driver, "05_checkout_validation_error.png")
        
        tc4_passed = "dữ liệu không hợp lệ" in error_message.lower() or "bắt buộc" in error_message.lower() or "shipping" in error_message.lower()
        log_test_case("TC04 - Phát hiện lỗi API Checkout (Bug Demonstration)", tc4_passed, f"Tìm thấy bug xác thực: '{error_message}'")

        # ----------------------------------------------------
        # TEST CASE 5: Đăng xuất khỏi hệ thống
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 5: Đăng xuất khỏi hệ thống...")
        
        # Click nút Logout nằm ở Navbar bên phải
        logout_btn = wait.until(EC.element_to_be_clickable((By.XPATH, "//div[contains(@class, 'user-actions')]/button")))
        driver.execute_script("arguments[0].click();", logout_btn)
        
        # Chờ quay về trang chủ và verify không còn lời chào
        wait.until(EC.url_matches("https://ecom.ziet.dev/"))
        time.sleep(2)
        
        user_actions_present = len(driver.find_elements(By.CLASS_NAME, "user-actions")) == 0
        tc5_passed = user_actions_present
        log_test_case("TC05 - Đăng xuất thành công", tc5_passed, "Session đã được xóa sạch và quay lại trang chủ khách.")
        
        # Chụp ảnh trang chủ sau khi đã logout
        take_screenshot(driver, "06_logout_success.png")

    except Exception as e:
        import traceback
        print(f"\n{Colors.RED}Đã xảy ra lỗi hệ thống trong quá trình chạy test:{Colors.END}")
        traceback.print_exc()
        
        # Chụp ảnh màn hình khi lỗi đột xuất để gỡ lỗi
        os.makedirs("selenium-assignment/screenshots", exist_ok=True)
        driver.save_screenshot("selenium-assignment/screenshots/unexpected_error.png")
        print("Đã lưu ảnh lỗi đột xuất tại 'selenium-assignment/screenshots/unexpected_error.png'")
        
    finally:
        # Tắt trình duyệt
        time.sleep(2)
        driver.quit()
        print(f"\n{Colors.BLUE}{Colors.BOLD}=== HOÀN THÀNH CHẠY THỬ NGHIỆM TRÊN ECOM.ZIET.DEV ==={Colors.END}\n")

if __name__ == "__main__":
    run_tests()
