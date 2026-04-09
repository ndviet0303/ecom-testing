<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { ShoppingBag, Package, User, MapPin, Clock } from 'lucide-vue-next'
import axios from 'axios'

const auth = useAuthStore()
const orders = ref([])
const loading = ref(true)

const fetchOrderHistory = async () => {
    loading.value = true
    try {
        const response = await axios.get('/api/v1/orders')
        orders.value = response.data.data || []
    } catch (err) {
        console.error('Lỗi tải lịch sử đơn hàng:', err)
    } finally {
        loading.value = false
    }
}

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Chờ thanh toán',
        'paid': 'Đã thanh toán',
        'processing': 'Đang xử lý',
        'shipped': 'Đang giao hàng',
        'delivered': 'Đã giao',
        'cancelled': 'Đã hủy'
    }
    return labels[status] || status
}

onMounted(() => {
    if (auth.isLoggedIn) {
        auth.fetchUser()
        fetchOrderHistory()
    }
})
</script>

<template>
  <div class="profile-view animate-fade-in">
    <div class="profile-layout">
      <!-- Sidebar Info -->
      <aside class="profile-aside">
        <div class="user-card glass-panel">
          <div class="user-avatar">
            <User :size="32" />
          </div>
          <h2 style="font-size: 1.5rem; font-weight: 700;">{{ auth.user?.name }}</h2>
          <p style="color: var(--text-secondary); margin-bottom: 24px;">{{ auth.user?.email }}</p>
          
          <div class="user-meta">
              <div class="meta-item">
                  <Clock :size="16" /> Tham gia: {{ new Date(auth.user?.created_at).toLocaleDateString() }}
              </div>
              <div class="meta-item">
                  <MapPin :size="16" /> {{ auth.user?.role === 'admin' ? 'Quản trị viên' : 'Khách hàng' }}
              </div>
          </div>

          <button @click="auth.logout()" class="btn w-100" style="margin-top: 32px; border: 1px solid var(--border-color); color: var(--error);">
              Đăng xuất
          </button>
        </div>
      </aside>

      <!-- Main Content -->
      <main class="profile-main">
        <section class="order-history glass-panel">
          <h3 style="margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
            <ShoppingBag :size="24" color="var(--accent-primary)" /> Lịch sử đơn hàng
          </h3>

          <div v-if="loading" class="state-msg">Đang tải đơn hàng của bạn...</div>
          
          <div v-else-if="orders.length === 0" class="empty-orders">
              <Package :size="48" style="opacity: 0.2; margin-bottom: 16px;" />
              <p>Bạn chưa có đơn hàng nào.</p>
              <RouterLink to="/products" class="btn btn-primary" style="margin-top: 20px;">Mua sắm ngay</RouterLink>
          </div>

          <div v-else class="orders-list">
            <div v-for="order in orders" :key="order.id" class="order-card">
              <div class="order-header">
                <div>
                  <div class="order-id">Đơn hàng #{{ order.id }}</div>
                  <div class="order-date">{{ new Date(order.created_at).toLocaleString('vi-VN') }}</div>
                </div>
                <div class="order-status" :class="'s-' + order.status">
                    {{ getStatusLabel(order.status) }}
                </div>
              </div>

              <div class="order-preview">
                  <div v-for="item in order.order_items.slice(0, 3)" :key="item.id" class="mini-item">
                      {{ item.product?.name }} (x{{ item.quantity }})
                  </div>
                  <div v-if="order.order_items.length > 3" style="font-size: 0.8rem; color: var(--text-secondary);">...và {{ order.order_items.length - 3 }} linh kiện khác</div>
              </div>

              <div class="order-footer">
                  <span>Tổng thanh toán: <strong class="gradient-text">{{ formatPrice(order.total_cents) }}</strong></span>
                  <button class="btn btn-sm" style="border: 1px solid var(--border-color); color: var(--text-primary);">Chi tiết</button>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>
</template>

<style scoped>
.profile-view { padding: 60px 0; }
.profile-layout { display: flex; gap: 40px; align-items: flex-start; }
.profile-aside { width: 320px; }
.profile-main { flex-grow: 1; }

.user-card { padding: 40px; text-align: center; }
.user-avatar { 
    width: 80px; height: 80px; border-radius: 20px; 
    background: rgba(99, 102, 241, 0.1); color: var(--accent-primary);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
}

.user-meta { display: flex; flex-direction: column; gap: 12px; text-align: left; background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 12px; }
.meta-item { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; color: var(--text-secondary); }

.order-history { padding: 40px; }
.order-card { 
    border: 1px solid var(--border-color); border-radius: 16px; 
    padding: 24px; margin-bottom: 20px; transition: all 0.3s ease;
}
.order-card:hover { border-color: var(--accent-primary); background: rgba(99, 102, 241, 0.02); }

.order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.order-id { font-weight: 700; font-size: 1.1rem; }
.order-date { font-size: 0.85rem; color: var(--text-secondary); }

.order-status { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; padding: 4px 12px; border-radius: 20px; }
.s-pending { background: rgba(234, 179, 8, 0.1); color: #eab308; }
.s-paid { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.s-delivered { background: #22c55e; color: white; }
.s-cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.order-preview { padding: 16px; background: rgba(255, 255, 255, 0.02); border-radius: 12px; margin-bottom: 20px; }
.mini-item { font-size: 0.9rem; margin-bottom: 4px; color: var(--text-secondary); }

.order-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 20px; }

.state-msg, .empty-orders { text-align: center; padding: 60px; color: var(--text-secondary); }
.w-100 { width: 100%; }
.btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 8px; }
</style>
