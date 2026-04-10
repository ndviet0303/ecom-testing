<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useBuilderStore } from '@/stores/builderStore'
import { useCartStore } from '@/stores/cartStore'
import { useToastStore } from '@/stores/toastStore'
import { AlertTriangle, CheckCircle, Zap, Wallet, ShoppingCart, RefreshCcw } from 'lucide-vue-next'

const store = useBuilderStore()
const cartStore = useCartStore()
const toastStore = useToastStore()
const router = useRouter()
const addingToCart = ref(false)

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

const addToCart = async () => {
  if (addingToCart.value || store.selectedIds.length === 0 || !store.validation.isValid) return

  addingToCart.value = true
  try {
    const added = await cartStore.addBuildToCart(store.slots)
    if (!added) return

    toastStore.success('Đã thêm bộ máy vào giỏ hàng.')
    router.push('/cart')
  } catch (err) {
    toastStore.error('Không thể thêm bộ máy vào giỏ hàng.')
  } finally {
    addingToCart.value = false
  }
}
</script>

<template>
  <div class="build-summary glass-panel sticky-top">
    <h4 style="margin-bottom: 24px; font-weight: 700;">Tổng quan bộ máy</h4>

    <div class="stat-card">
      <div class="stat-icon psu"><Zap :size="18" /></div>
      <div class="stat-content">
        <label>Công suất dự kiến</label>
        <div class="value">{{ store.validation.estimatedWattage }} W</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon price"><Wallet :size="18" /></div>
      <div class="stat-content">
        <label>Tổng giá trị</label>
        <div class="value">{{ formatPrice(store.totalPrice) }}</div>
      </div>
    </div>

    <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;" />

    <div class="compatibility-box">
      <div v-if="store.validation.loading" class="loading-status">Đang kiểm tra tương thích...</div>
      
      <div v-else-if="store.validation.isValid && store.selectedIds.length > 0" class="success-status">
        <CheckCircle :size="18" />
        Tương thích hoàn hảo!
      </div>

      <div v-else-if="store.validation.errors.length > 0" class="error-container">
        <div class="error-header">
          <AlertTriangle :size="18" />
          Phát hiện xung đột
        </div>
        <ul class="error-list">
          <li v-for="(err, idx) in store.validation.errors" :key="idx">{{ err }}</li>
        </ul>
      </div>

      <div v-else class="empty-status">Hãy chọn linh kiện để kiểm tra</div>
    </div>

    <button 
      @click="addToCart"
      class="btn btn-primary w-100" 
      style="margin-top: 32px; display: flex; align-items: center; justify-content: center; gap: 8px;" 
      :disabled="addingToCart || store.validation.loading || !store.validation.isValid || store.selectedIds.length === 0"
    >
      <RefreshCcw v-if="addingToCart" class="animate-spin" :size="18" />
      <ShoppingCart v-else :size="18" />
      {{ addingToCart ? 'Đang thêm vào giỏ...' : 'Thêm bộ máy vào giỏ' }}
    </button>
  </div>
</template>

<style scoped>
.build-summary {
  padding: 32px;
  width: 340px;
  position: sticky;
  top: 100px;
}

.stat-card {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}

.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon.psu { background: rgba(234, 179, 8, 0.1); color: #eab308; }
.stat-icon.price { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

.stat-content label {
  display: block;
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  margin-bottom: 2px;
}

.stat-content .value {
  font-size: 1.15rem;
  font-weight: 700;
}

.compatibility-box {
  min-height: 80px;
}

.success-status {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--success);
  font-weight: 600;
  padding: 12px;
  background: rgba(34, 197, 94, 0.1);
  border-radius: 10px;
}

.error-container {
  padding: 16px;
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 12px;
}

.error-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--error);
  font-weight: 700;
  margin-bottom: 12px;
}

.error-list {
  list-style: none;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.error-list li {
  margin-bottom: 6px;
  position: relative;
  padding-left: 14px;
}

.error-list li::before {
  content: '•';
  position: absolute;
  left: 0;
  color: var(--error);
}

.empty-status {
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-style: italic;
}

.w-100 { width: 100%; }
</style>
