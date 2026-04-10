<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useCartStore } from '@/stores/cartStore'
import { useToastStore } from '@/stores/toastStore'
import { useRouter } from 'vue-router'
import { LogIn, User } from 'lucide-vue-next'

const auth = useAuthStore()
const cartStore = useCartStore()
const toastStore = useToastStore()
const router = useRouter()

const email = ref('customer@ziet.dev')
const password = ref('password')
const error = ref('')

const handleLogin = async () => {
  error.value = ''
  const success = await auth.login(email.value, password.value)
  if (success) {
    try {
      await cartStore.mergeGuestCart()
    } catch (err) {
      toastStore.error('Không thể đồng bộ giỏ hàng cũ. Hệ thống sẽ tải giỏ hàng hiện tại của tài khoản.')
    }
    await cartStore.fetchCart()
    router.push('/checkout')
  } else {
    error.value = 'Email hoặc mật khẩu không chính xác.'
  }
}
</script>

<template>
  <div class="login-view animate-fade-in">
    <div class="login-card glass-panel">
      <div class="login-header">
        <div class="user-icon"><User :size="24" /></div>
        <h2>Đăng nhập <span class="gradient-text">ZIET.PC</span></h2>
        <p>Truy cập tài khoản để tiếp tục thanh toán.</p>
      </div>

      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label>Email</label>
          <input type="email" v-model="email" required placeholder="example@ziet.dev" />
        </div>
        
        <div class="form-group">
          <label>Mật khẩu</label>
          <input type="password" v-model="password" required placeholder="••••••••" />
        </div>

        <div v-if="error" class="error-msg">{{ error }}</div>

        <button type="submit" class="btn btn-primary w-100" :disabled="auth.loading">
          <span v-if="auth.loading">Đang đăng nhập...</span>
          <span v-else style="display: flex; align-items: center; justify-content: center; gap: 8px;">
            Đăng nhập ngay <LogIn :size="18" />
          </span>
        </button>
      </form>
      
      <div class="login-footer">
        Chưa có tài khoản? <a href="#">Đăng ký ngay</a>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-view {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login-card {
  width: 100%;
  max-width: 440px;
  padding: 48px;
}

.login-header {
  text-align: center;
  margin-bottom: 32px;
}

.user-icon {
  background: rgba(99, 102, 241, 0.1);
  width: 56px;
  height: 56px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent-primary);
  margin: 0 auto 20px;
}

.login-header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 8px;
}

.login-header p {
  color: var(--text-secondary);
  font-size: 0.95rem;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 8px;
  color: var(--text-secondary);
}

.form-group input {
  width: 100%;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  padding: 14px;
  border-radius: 12px;
  color: var(--text-primary);
  outline: none;
  transition: border-color 0.3s ease;
}

.form-group input:focus {
  border-color: var(--accent-primary);
}

.error-msg {
  color: var(--error);
  font-size: 0.85rem;
  text-align: center;
}

.login-footer {
  margin-top: 32px;
  text-align: center;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.login-footer a {
  color: var(--accent-primary);
  text-decoration: none;
  font-weight: 600;
}

.w-100 { width: 100%; }
</style>
