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
    print(f"\n{Colors.BLUE}{Colors.BOLD}=== BẮT ĐẦU CHẠY THỬ NGHIỆM AUTOMATION TESTING TRÊN ECOM.ZIET.DEV ==={Colors.END}\n")
    
    # Thiết lập Chrome Options
    chrome_options = Options()
    chrome_options.add_argument("--headless")
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
        
        if not tc3_passed:
            return

        # ----------------------------------------------------
        # TEST CASE 4: Đăng xuất khỏi hệ thống
        # ----------------------------------------------------
        print("\nĐang thực hiện Test Case 4: Đăng xuất khỏi hệ thống...")
        
        # Click nút Logout nằm ở Navbar bên phải
        logout_btn = wait.until(EC.element_to_be_clickable((By.XPATH, "//div[contains(@class, 'user-actions')]/button")))
        driver.execute_script("arguments[0].click();", logout_btn)
        
        # Chờ quay về trang chủ và verify không còn lời chào
        wait.until(EC.url_matches("https://ecom.ziet.dev/"))
        time.sleep(2)
        
        user_actions_present = len(driver.find_elements(By.CLASS_NAME, "user-actions")) == 0
        tc4_passed = user_actions_present
        log_test_case("TC04 - Đăng xuất thành công", tc4_passed, "Session đã được xóa sạch và quay lại trang chủ khách.")

    except Exception as e:
        import traceback
        print(f"\n{Colors.RED}Đã xảy ra lỗi trong quá trình chạy test:{Colors.END}")
        traceback.print_exc()
        
        # In các logs của trình duyệt để gỡ lỗi
        try:
            print("\n=== BROWSER CONSOLE LOGS ===")
            browser_logs = driver.get_log('browser')
            if browser_logs:
                for entry in browser_logs:
                    print(entry)
            else:
                print("(Không có log nào trong browser console)")
            print("============================\n")
        except Exception as e_logs:
            print(f"Không lấy được console logs: {str(e_logs)}")
        
        # Chụp ảnh màn hình khi lỗi xảy ra để debug
        os.makedirs("screenshots", exist_ok=True)
        driver.save_screenshot("screenshots/ecom_error_screenshot.png")
        print("Đã lưu ảnh lỗi tại 'screenshots/ecom_error_screenshot.png'")
        
    finally:
        # Tắt trình duyệt
        time.sleep(2)
        driver.quit()
        print(f"\n{Colors.BLUE}{Colors.BOLD}=== HOÀN THÀNH CHẠY THỬ NGHIỆM TRÊN ECOM.ZIET.DEV ==={Colors.END}\n")

if __name__ == "__main__":
    run_tests()
