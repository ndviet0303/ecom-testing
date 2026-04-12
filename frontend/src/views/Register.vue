<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useCartStore } from '@/stores/cartStore'
import { useToastStore } from '@/stores/toastStore'
import { useRouter } from 'vue-router'
import { UserPlus, User } from 'lucide-vue-next'

const auth = useAuthStore()
const cartStore = useCartStore()
const toastStore = useToastStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const error = ref('')

const handleRegister = async () => {
  error.value = ''

  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Mật khẩu xác nhận không khớp.'
    return
  }

  const result = await auth.register({ ...form.value })

  if (result.success) {
    toastStore.success('Đăng ký thành công. Chào mừng bạn đến với ZIET.PC!')
    await cartStore.fetchCart()
    await router.push('/checkout')
    return
  }

  error.value = result.message || 'Đăng ký thất bại.'
}
</script>

<template>
  <div class="register-view animate-fade-in">
    <div class="register-card glass-panel">
      <div class="register-header">
        <div class="user-icon"><User :size="24" /></div>
        <h2>Tạo tài khoản <span class="gradient-text">ZIET.PC</span></h2>
        <p>Đăng ký để lưu đơn hàng và quản lý địa chỉ nhận hàng.</p>
      </div>

      <form @submit.prevent="handleRegister" class="register-form">
        <div class="form-group">
          <label>Họ và tên</label>
          <input v-model="form.name" type="text" required placeholder="Nguyễn Văn A" />
        </div>

        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" required placeholder="example@ziet.dev" />
        </div>

        <div class="form-group">
          <label>Mật khẩu</label>
          <input v-model="form.password" type="password" required minlength="8" placeholder="Tối thiểu 8 ký tự" />
        </div>

        <div class="form-group">
          <label>Xác nhận mật khẩu</label>
          <input v-model="form.password_confirmation" type="password" required minlength="8" placeholder="Nhập lại mật khẩu" />
        </div>

        <div v-if="error" class="error-msg">{{ error }}</div>

        <button type="submit" class="btn btn-primary w-100" :disabled="auth.loading">
          <span v-if="auth.loading">Đang tạo tài khoản...</span>
          <span v-else style="display: flex; align-items: center; justify-content: center; gap: 8px;">
            Tạo tài khoản <UserPlus :size="18" />
          </span>
        </button>
      </form>

      <div class="register-footer">
        Đã có tài khoản? <RouterLink to="/login">Đăng nhập ngay</RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.register-view {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.register-card {
  width: 100%;
  max-width: 480px;
  padding: 44px;
}

.register-header {
  text-align: center;
  margin-bottom: 28px;
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

.register-header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 8px;
}

.register-header p {
  color: var(--text-secondary);
  font-size: 0.95rem;
}

.register-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
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

.register-footer {
  margin-top: 28px;
  text-align: center;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.register-footer a {
  color: var(--accent-primary);
  text-decoration: none;
  font-weight: 600;
}

.w-100 {
  width: 100%;
}
</style>
