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

def run_tests():
    print(f"\n{Colors.BLUE}{Colors.BOLD}=== BẮT ĐẦU CHẠY THỬ NGHIỆM AUTOMATION TESTING VỚI SELENIUM ==={Colors.END}\n")
    
    # Thiết lập Chrome Options
    chrome_options = Options()
    # Chạy ở chế độ không giao diện (headless) nếu được yêu cầu (tiện lợi khi chạy CI/CD hoặc không có màn hình)
    # chrome_options.add_argument("--headless")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--start-maximized")
    
    # Khởi tạo WebDriver (Selenium 4+ tự động tải và quản lý ChromeDriver)
    driver = webdriver.Chrome(options=chrome_options)
    wait = WebDriverWait(driver, 10) # Thời gian chờ tối đa 10 giây cho Explicit Wait
    
    try:
        # ----------------------------------------------------
        # TEST CASE 1: Đăng nhập thành công với tài khoản hợp lệ
        # ----------------------------------------------------
        print("Đang thực hiện Test Case 1: Đăng nhập...")
        driver.get("https://www.saucedemo.com/")
        
        # Chờ trang login load xong
        username_field = wait.until(EC.presence_of_element_located((By.ID, "user-name")))
        password_field = driver.find_element(By.ID, "password")
        login_button = driver.find_element(By.ID, "login-button")
        
        # Nhập thông tin đăng nhập
        username_field.send_keys("standard_user")
        password_field.send_keys("secret_sauce")
        login_button.click()
        
        # Xác nhận đăng nhập thành công bằng cách kiểm tra URL hoặc tiêu đề trang chính
        wait.until(EC.url_contains("/inventory.html"))
        header_title = wait.until(EC.presence_of_element_located((By.CLASS_NAME, "title")))
        
        tc1_passed = "products" in header_title.text.lower()
        log_test_case("TC01 - Đăng nhập hợp lệ", tc1_passed, f"Đăng nhập thành công, đã chuyển hướng đến {driver.current_url}")
        
        if not tc1_passed:
            return

        # ----------------------------------------------------
        # TEST CASE 2: Thêm sản phẩm vào giỏ hàng & Xác minh số lượng
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 2: Thêm sản phẩm vào giỏ hàng...")
        
        # Tìm các nút "Add to cart" cho sản phẩm cụ thể
        # Ở đây chúng ta chọn "Sauce Labs Backpack" và "Sauce Labs Bolt T-Shirt"
        add_backpack_btn = wait.until(EC.element_to_be_clickable((By.ID, "add-to-cart-sauce-labs-backpack")))
        add_tshirt_btn = driver.find_element(By.ID, "add-to-cart-sauce-labs-bolt-t-shirt")
        
        # Click thêm vào giỏ hàng
        add_backpack_btn.click()
        add_tshirt_btn.click()
        
        # Kiểm tra badge hiển thị số lượng sản phẩm trên icon giỏ hàng
        cart_badge = wait.until(EC.presence_of_element_located((By.CLASS_NAME, "shopping_cart_badge")))
        cart_count = cart_badge.text
        
        tc2_passed = (cart_count == "2")
        log_test_case("TC02 - Thêm sản phẩm vào giỏ hàng", tc2_passed, f"Số lượng sản phẩm trong giỏ hàng hiển thị: {cart_count} (Kỳ vọng: 2)")

        # ----------------------------------------------------
        # TEST CASE 3: Đi tới giỏ hàng, điền thông tin và hoàn tất Thanh toán (Checkout)
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 3: Thanh toán đơn hàng (Checkout)...")
        
        # Click vào icon giỏ hàng để chuyển hướng sang trang Cart
        cart_link = driver.find_element(By.CLASS_NAME, "shopping_cart_link")
        cart_link.click()
        
        # Chờ trang Cart hiển thị và click Checkout
        checkout_btn = wait.until(EC.element_to_be_clickable((By.ID, "checkout")))
        checkout_btn.click()
        
        # Chờ trang điền thông tin hiển thị
        first_name = wait.until(EC.presence_of_element_located((By.ID, "first-name")))
        last_name = driver.find_element(By.ID, "last-name")
        postal_code = driver.find_element(By.ID, "postal-code")
        continue_btn = driver.find_element(By.ID, "continue")
        
        # Nhập thông tin người nhận hàng mẫu
        first_name.send_keys("Viet")
        last_name.send_keys("Nghiem")
        postal_code.send_keys("10000")
        continue_btn.click()
        
        # Chờ sang trang Overview (Xem lại đơn hàng)
        finish_btn = wait.until(EC.element_to_be_clickable((By.ID, "finish")))
        finish_btn.click()
        
        # Chờ trang Complete hiển thị và verify thông điệp thành công
        complete_header = wait.until(EC.presence_of_element_located((By.CLASS_NAME, "complete-header")))
        success_message = complete_header.text
        
        tc3_passed = "thank you for your order" in success_message.lower()
        log_test_case("TC03 - Thanh toán đơn hàng", tc3_passed, f"Thông điệp nhận được: '{success_message}'")

        # ----------------------------------------------------
        # TEST CASE 4: Đăng xuất khỏi hệ thống
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 4: Đăng xuất khỏi hệ thống...")
        
        # Click vào Menu Burger để mở thanh điều hướng bên trái
        burger_menu = wait.until(EC.element_to_be_clickable((By.ID, "react-burger-menu-btn")))
        burger_menu.click()
        
        # Chờ 1 giây để menu slide out hoàn toàn
        time.sleep(1)
        
        # Chờ nút Logout xuất hiện và click qua Javascript để tránh lỗi click bị chặn bởi hiệu ứng chuyển động
        logout_link = wait.until(EC.presence_of_element_located((By.ID, "logout_sidebar_link")))
        driver.execute_script("arguments[0].click();", logout_link)
        
        # Xác minh đã quay trở lại trang login (URL chứa gốc hoặc có nút Login)
        wait.until(EC.presence_of_element_located((By.ID, "login-button")))
        current_url = driver.current_url
        
        tc4_passed = "saucedemo.com" in current_url and "/inventory.html" not in current_url
        log_test_case("TC04 - Đăng xuất thành công", tc4_passed, "Quay lại trang login thành công.")

    except Exception as e:
        print(f"\n{Colors.RED}Đã xảy ra lỗi trong quá trình chạy test:{Colors.END} {str(e)}")
        # Chụp ảnh màn hình khi lỗi xảy ra để debug
        os.makedirs("screenshots", exist_ok=True)
        driver.save_screenshot("screenshots/error_screenshot.png")
        print("Đã lưu ảnh lỗi tại 'screenshots/error_screenshot.png'")
        
    finally:
        # Chờ 3 giây trước khi đóng trình duyệt để người dùng kịp quan sát
        time.sleep(3)
        driver.quit()
        print(f"\n{Colors.BLUE}{Colors.BOLD}=== HOÀN THÀNH CHẠY THỬ NGHIỆM ==={Colors.END}\n")

if __name__ == "__main__":
    run_tests()
