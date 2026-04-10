<script setup>
import { onMounted } from 'vue'
import { RouterView, RouterLink } from 'vue-router'
import { ShoppingCart, Cpu, User, LogOut } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cartStore'
import { useAuthStore } from '@/stores/authStore'
import ToastContainer from '@/components/ToastContainer.vue'

const cartStore = useCartStore()
const authStore = useAuthStore()

onMounted(() => {
  cartStore.fetchCart()
  authStore.applyAuthHeader()
  authStore.fetchUser()
})
</script>

<template>
  <nav class="glass-panel main-nav">
    <div class="nav-logo">
      <RouterLink to="/" class="logo-link">
        <div class="logo-icon">
          <Cpu :size="20" color="white" />
        </div>
        <h1 class="gradient-text logo-text">ZIET.PC</h1>
      </RouterLink>
    </div>

    <div class="nav-links">
      <RouterLink to="/" class="nav-link">Home</RouterLink>
      <RouterLink to="/builder" class="nav-link">PC Builder</RouterLink>
      <RouterLink to="/products" class="nav-link">Products</RouterLink>
      <RouterLink v-if="authStore.user?.role === 'admin' || authStore.user?.role === 'staff'" to="/admin" class="nav-link">Dashboard</RouterLink>
    </div>

    <div class="nav-actions">
      <RouterLink to="/cart" class="nav-icon-btn cart-btn">
        <ShoppingCart :size="20" />
        <span v-if="cartStore.itemCount > 0" class="cart-badge">{{ cartStore.itemCount }}</span>
      </RouterLink>
      
      <div v-if="authStore.isLoggedIn" class="user-actions">
         <span class="user-greeting">Hi, {{ authStore.user?.name }}</span>
         <RouterLink to="/profile" class="nav-icon-btn"><User :size="20" /></RouterLink>
         <button @click="authStore.logout()" class="nav-icon-btn"><LogOut :size="18" /></button>
      </div>
      <RouterLink v-else to="/login" class="nav-icon-btn"><User :size="20" /></RouterLink>
    </div>
  </nav>

  <main class="container">
    <RouterView />
  </main>

  <ToastContainer />
</template>

<style scoped>
.main-nav {
  margin: 20px;
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 20px;
  z-index: 100;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.nav-logo, .logo-link, .nav-links, .nav-actions, .user-actions {
  display: flex;
  align-items: center;
}

.logo-link {
  text-decoration: none;
  gap: 12px;
}

.logo-icon {
  background: var(--accent-primary);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-text {
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -1px;
}

.nav-links {
  gap: 32px;
}

.nav-link {
  color: var(--text-secondary);
  text-decoration: none;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.nav-link:hover, .router-link-active {
  color: var(--accent-primary);
}

.nav-actions {
  gap: 16px;
}

.user-actions {
  gap: 12px;
}

.user-greeting {
  font-size: 0.85rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.nav-icon-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav-icon-btn:hover {
  color: var(--accent-primary);
  transform: translateY(-1px);
}

.cart-btn {
  position: relative;
}

.cart-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: var(--accent-primary);
  color: white;
  font-size: 0.7rem;
  font-weight: 700;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #ffffff;
}
</style>
