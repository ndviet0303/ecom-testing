<script setup>
import { onMounted } from 'vue'
import { useCartStore } from '@/stores/cartStore'
import { Trash2, ShoppingBag, ArrowRight } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

const cartStore = useCartStore()

onMounted(() => {
  cartStore.fetchCart()
})

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}
</script>

<template>
  <div class="cart-view animate-fade-in">
    <header style="margin-bottom: 40px;">
      <h2 style="font-size: 2.5rem; font-weight: 800;">Giỏ hàng <span class="gradient-text">Của bạn</span></h2>
      <p style="color: var(--text-secondary);">Kiểm tra lại linh kiện trước khi tiến hành thanh toán.</p>
    </header>

    <div v-if="cartStore.items.length === 0" class="empty-cart glass-panel">
      <div class="empty-icon"><ShoppingBag :size="48" /></div>
      <h3>Giỏ hàng đang trống</h3>
      <p style="margin-bottom: 24px; color: var(--text-secondary);">Hãy chọn những linh kiện tốt nhất cho PC của bạn.</p>
      <RouterLink to="/products" class="btn btn-primary">Khám phá sản phẩm</RouterLink>
    </div>

    <div v-else class="cart-layout">
      <div class="cart-items">
        <div v-for="item in cartStore.items" :key="item.id" class="cart-item glass-panel">
          <img :src="item.product.image_url" :alt="item.product.name" class="item-img" />
          <div class="item-info">
            <div class="item-category">{{ item.product.category }}</div>
            <div class="item-name">{{ item.product.name }}</div>
            <div class="item-price">{{ formatPrice(item.unit_price_cents) }}</div>
          </div>
          
          <div class="item-actions">
            <div class="quantity-controls">
              <button @click="cartStore.updateQuantity(item.product_id, item.quantity - 1)" :disabled="item.quantity <= 1">-</button>
              <span>{{ item.quantity }}</span>
              <button @click="cartStore.updateQuantity(item.product_id, item.quantity + 1)">+</button>
            </div>
            <button class="remove-btn" @click="cartStore.removeItem(item.product_id)">
              <Trash2 :size="18" />
            </button>
          </div>
        </div>
      </div>

      <div class="cart-summary glass-panel">
        <h3 style="margin-bottom: 24px;">Tạm tính</h3>
        <div class="summary-row">
          <span>Tổng tiền hàng</span>
          <span style="font-weight: 700;">{{ formatPrice(cartStore.subtotal) }}</span>
        </div>
        <div class="summary-row" style="color: var(--text-secondary); font-size: 0.9rem;">
          <span>Phí vận chuyển</span>
          <span>Tính ở bước sau</span>
        </div>
        
        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 20px 0;" />
        
        <div class="summary-row total">
          <span>Tổng cộng</span>
          <span class="gradient-text">{{ formatPrice(cartStore.subtotal) }}</span>
        </div>

        <RouterLink to="/checkout" class="btn btn-primary w-100" style="margin-top: 32px; display: flex; align-items: center; justify-content: center; gap: 8px;">
          Thanh toán ngay <ArrowRight :size="18" />
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cart-view { padding: 60px 0; }

.cart-layout {
  display: flex;
  gap: 40px;
  align-items: flex-start;
}

.cart-items { flex-grow: 1; }

.cart-item {
  display: flex;
  align-items: center;
  padding: 24px;
  margin-bottom: 16px;
  gap: 24px;
}

.item-img {
  width: 100px;
  height: 100px;
  object-fit: contain;
  background: #1a1a20;
  border-radius: 12px;
}

.item-info { flex-grow: 1; }

.item-category {
  font-size: 0.75rem;
  color: var(--accent-primary);
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.item-name {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 8px;
}

.item-price {
  font-weight: 700;
  font-size: 1.1rem;
}

.item-actions {
  display: flex;
  align-items: center;
  gap: 24px;
}

.quantity-controls {
  display: flex;
  align-items: center;
  background: var(--bg-secondary);
  border-radius: 10px;
  padding: 4px;
}

.quantity-controls button {
  background: none;
  border: none;
  color: var(--text-primary);
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 1.2rem;
}

.quantity-controls span {
  width: 40px;
  text-align: center;
  font-weight: 600;
}

.remove-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  transition: color 0.3s ease;
}

.remove-btn:hover { color: var(--error); }

.cart-summary {
  width: 360px;
  padding: 32px;
  position: sticky;
  top: 100px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.summary-row.total {
  font-size: 1.4rem;
  font-weight: 800;
}

.empty-cart {
  padding: 80px;
  text-align: center;
}

.empty-icon {
  color: var(--accent-primary);
  margin-bottom: 24px;
  opacity: 0.5;
}

.w-100 { width: 100%; }
</style>
