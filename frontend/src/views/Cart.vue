<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useCartStore } from '@/stores/cartStore'
import { useToastStore } from '@/stores/toastStore'
import axios from 'axios'
import { Trash2, ShoppingBag, ArrowRight } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

const cartStore = useCartStore()
const toastStore = useToastStore()
const couponCodeInput = ref('')
const appliedCoupon = ref(null)
const couponChecking = ref(false)

const STORAGE_KEY = 'checkout_coupon_code'

const discountCents = computed(() => appliedCoupon.value?.discount_cents || 0)
const totalAfterDiscount = computed(() => Math.max(0, cartStore.subtotal - discountCents.value))

onMounted(() => {
  cartStore.fetchCart()

  const savedCode = localStorage.getItem(STORAGE_KEY)
  if (savedCode) {
    couponCodeInput.value = savedCode
  }
})

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

const changeQuantity = async (productId, nextQuantity) => {
  try {
    await cartStore.updateQuantity(productId, nextQuantity)
  } catch (err) {
    toastStore.error(err?.response?.data?.message || 'Không thể cập nhật số lượng.')
  }
}

const removeItem = async (productId) => {
  try {
    await cartStore.removeItem(productId)
  } catch (err) {
    toastStore.error(err?.response?.data?.message || 'Không thể xóa sản phẩm khỏi giỏ.')
  }
}

const applyCoupon = async () => {
  const code = String(couponCodeInput.value || '').trim()
  if (!code) {
    toastStore.error('Vui lòng nhập mã giảm giá.')
    return
  }

  couponChecking.value = true
  try {
    const response = await axios.get(`/api/v1/coupons/${encodeURIComponent(code)}/preview`, {
      params: {
        subtotal_cents: cartStore.subtotal
      }
    })

    if (!response.data?.valid) {
      toastStore.error('Mã giảm giá không hợp lệ.')
      return
    }

    if (!response.data?.eligible) {
      const minSubtotal = Number(response.data?.min_subtotal_cents || 0)
      toastStore.error(`Đơn hàng chưa đạt mức tối thiểu ${formatPrice(minSubtotal)} để dùng mã này.`)
      return
    }

    appliedCoupon.value = {
      code,
      discount_cents: Number(response.data?.discount_cents || 0),
      min_subtotal_cents: Number(response.data?.min_subtotal_cents || 0)
    }
    couponCodeInput.value = code
    localStorage.setItem(STORAGE_KEY, code)
    toastStore.success(`Đã áp dụng mã ${code}.`)
  } catch (err) {
    toastStore.error(err?.response?.data?.message || 'Không áp dụng được mã giảm giá.')
  } finally {
    couponChecking.value = false
  }
}

const clearCoupon = () => {
  appliedCoupon.value = null
  couponCodeInput.value = ''
  localStorage.removeItem(STORAGE_KEY)
}

watch(
  () => cartStore.subtotal,
  (nextSubtotal) => {
    if (!appliedCoupon.value) return
    if (nextSubtotal >= appliedCoupon.value.min_subtotal_cents) return

    const oldCode = appliedCoupon.value.code
    appliedCoupon.value = null
    localStorage.removeItem(STORAGE_KEY)
    toastStore.error(`Mã ${oldCode} đã bị gỡ vì đơn hàng không còn đủ điều kiện.`)
  }
)
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
              <button
                @click="changeQuantity(item.product_id, item.quantity - 1)"
                :disabled="item.quantity <= 1 || cartStore.isItemPending(item.product_id)"
              >-</button>
              <span :class="{ pending: cartStore.isItemPending(item.product_id) }">{{ item.quantity }}</span>
              <button
                @click="changeQuantity(item.product_id, item.quantity + 1)"
                :disabled="cartStore.isItemPending(item.product_id)"
              >+</button>
            </div>
            <button
              class="remove-btn"
              @click="removeItem(item.product_id)"
              :disabled="cartStore.isItemPending(item.product_id)"
            >
              <Trash2 :size="18" />
            </button>
          </div>
        </div>
      </div>

      <div class="cart-summary glass-panel">
        <h3 style="margin-bottom: 24px;">Tạm tính</h3>
        <div class="coupon-box">
          <label for="cart-coupon-input">Mã giảm giá</label>
          <div class="coupon-input-row">
            <input
              id="cart-coupon-input"
              v-model="couponCodeInput"
              type="text"
              placeholder="Nhập mã giảm giá"
              :disabled="couponChecking"
            />
            <button
              class="btn btn-secondary coupon-apply-btn"
              @click="applyCoupon"
              :disabled="couponChecking || !couponCodeInput.trim()"
            >
              {{ couponChecking ? '...' : 'Áp dụng' }}
            </button>
          </div>
          <div v-if="appliedCoupon" class="coupon-applied">
            <span>Đã áp dụng: <strong>{{ appliedCoupon.code }}</strong></span>
            <button type="button" class="coupon-clear-btn" @click="clearCoupon">
              Bỏ
            </button>
          </div>
        </div>

        <div class="summary-row">
          <span>Tổng tiền hàng</span>
          <span style="font-weight: 700;">{{ formatPrice(cartStore.subtotal) }}</span>
        </div>
        <div v-if="discountCents > 0" class="summary-row discount-line">
          <span>Giảm giá</span>
          <span>- {{ formatPrice(discountCents) }}</span>
        </div>
        <div class="summary-row" style="color: var(--text-secondary); font-size: 0.9rem;">
          <span>Phí vận chuyển</span>
          <span>Tính ở bước sau</span>
        </div>
        
        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 20px 0;" />
        
        <div class="summary-row total">
          <span>Tổng cộng</span>
          <span class="gradient-text">{{ formatPrice(totalAfterDiscount) }}</span>
        </div>

        <RouterLink :to="{ name: 'checkout', query: appliedCoupon?.code ? { coupon: appliedCoupon.code } : {} }" class="btn btn-primary w-100" style="margin-top: 32px; display: flex; align-items: center; justify-content: center; gap: 8px;">
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

.quantity-controls span.pending {
  opacity: 0.55;
}

.quantity-controls button:disabled,
.remove-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
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

.discount-line {
  color: #0f766e;
  font-weight: 600;
}

.coupon-box {
  padding: 14px;
  margin-bottom: 16px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: #fff;
}

.coupon-box label {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.coupon-input-row {
  display: flex;
  gap: 8px;
}

.coupon-input-row input {
  flex: 1;
  min-width: 0;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 10px 12px;
  color: var(--text-primary);
  outline: none;
}

.coupon-apply-btn {
  padding: 10px 12px;
  border-radius: 10px;
  white-space: nowrap;
}

.coupon-applied {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.coupon-clear-btn {
  border: none;
  background: none;
  color: #b91c1c;
  font-weight: 700;
  cursor: pointer;
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
