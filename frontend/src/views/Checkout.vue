<script setup>
import { ref, onMounted, computed } from 'vue'
import { useCartStore } from '@/stores/cartStore'
import { useAuthStore } from '@/stores/authStore'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { MapPin, Truck, CreditCard, CheckCircle } from 'lucide-vue-next'

const cartStore = useCartStore()
const authStore = useAuthStore()
const router = useRouter()

const zones = ref([])
const addresses = ref([])
const selectedZoneId = ref(null)
const selectedAddressId = ref(null)
const note = ref('')
const loading = ref(false)

const fetchData = async () => {
  if (!authStore.isLoggedIn) {
     router.push('/login')
     return
  }
  
  try {
    const [zResp, aResp] = await Promise.all([
      axios.get('/api/v1/shipping-zones'),
      axios.get('/api/v1/addresses')
    ])
    zones.value = zResp.data.data || zResp.data
    addresses.value = aResp.data.data || aResp.data
    
    if (zones.value.length > 0) selectedZoneId.value = zones.value[0].id
    if (addresses.value.length > 0) {
        const def = addresses.value.find(a => a.is_default) || addresses.value[0]
        selectedAddressId.value = def.id
    }
  } catch (err) {
    console.error('Lỗi tải dữ liệu checkout:', err)
  }
}

const selectedZone = computed(() => zones.value.find(z => z.id === selectedZoneId.value))
const shippingFee = computed(() => {
    if (!selectedZone.value) return 0
    // Giả định cân nặng trung bình PC là 15kg (15000g)
    const weightKg = 15
    return selectedZone.value.rate_per_kg_cents * weightKg
})

const total = computed(() => cartStore.subtotal + shippingFee.value)

const placeOrder = async () => {
  loading.value = true
  try {
    const response = await axios.post('/api/v1/checkout', {
      shipping_address_id: selectedAddressId.value,
      shipping_zone_id: selectedZoneId.value,
      weight_grams: 15000, // Hardcoded weight for now
      tax_rate_basis_points: 0,
      payment_method: 'sepay_qr',
      customer_note: note.value
    })
    
    // Clear cart locally
    cartStore.items = []
    cartStore.subtotal = 0
    
    router.push({ name: 'order-success', params: { id: response.data.order.id } })
  } catch (err) {
    alert('Lỗi khi đặt hàng: ' + (err.response?.data?.message || err.message))
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

onMounted(() => {
    cartStore.fetchCart()
    fetchData()
})
</script>

<template>
  <div class="checkout-view animate-fade-in">
    <header style="margin-bottom: 40px;">
      <h2 style="font-size: 2.5rem; font-weight: 800;">Hoàn tất <span class="gradient-text">Đơn hàng</span></h2>
      <p style="color: var(--text-secondary);">Chúng tôi sẽ chuẩn bị hàng ngay khi bạn xác nhận.</p>
    </header>

    <div class="checkout-layout">
      <div class="checkout-forms">
        <section class="checkout-section glass-panel">
          <div class="section-header">
            <MapPin :size="20" color="var(--accent-primary)" />
            <h3>Địa chỉ nhận hàng</h3>
          </div>
          <div class="address-list" v-if="addresses.length > 0">
            <div 
              v-for="addr in addresses" 
              :key="addr.id" 
              class="address-item"
              :class="{ active: selectedAddressId === addr.id }"
              @click="selectedAddressId = addr.id"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>{{ addr.recipient_name }}</strong> ({{ addr.phone }})
                <p>{{ addr.line1 }}, {{ addr.ward }}, {{ addr.district }}, {{ addr.province }}</p>
              </div>
            </div>
          </div>
          <div v-else class="empty-msg">Bạn chưa có địa chỉ nào. Hãy thêm địa chỉ trong hồ sơ.</div>
        </section>

        <section class="checkout-section glass-panel">
          <div class="section-header">
            <Truck :size="20" color="var(--accent-primary)" />
            <h3>Đơn vị vận chuyển</h3>
          </div>
          <div class="zone-list">
            <div 
              v-for="zone in zones" 
              :key="zone.id" 
              class="zone-item"
              :class="{ active: selectedZoneId === zone.id }"
              @click="selectedZoneId = zone.id"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>{{ zone.name }}</strong>
                <p>Phí: {{ formatPrice(zone.rate_per_kg_cents) }} / kg</p>
              </div>
            </div>
          </div>
        </section>

        <section class="checkout-section glass-panel">
          <div class="section-header">
            <CreditCard :size="20" color="var(--accent-primary)" />
            <h3>Thanh toán & Ghi chú</h3>
          </div>
          <div style="padding: 20px;">
            <div class="payment-method active">
              <CheckCircle :size="16" /> Chuyển khoản qua quét mã QR (SePay)
            </div>
            <textarea v-model="note" placeholder="Lưu ý cho người bán... (ví dụ: Giao giờ hành chính)" class="checkout-note"></textarea>
          </div>
        </section>
      </div>

      <div class="order-summary-sidebar glass-panel">
        <h3 style="margin-bottom: 24px;">Chi tiết đơn hàng</h3>
        <div class="summary-line">
          <span>Tiền hàng</span>
          <span>{{ formatPrice(cartStore.subtotal) }}</span>
        </div>
        <div class="summary-line">
          <span>Phí vận chuyển dự kiến</span>
          <span>+ {{ formatPrice(shippingFee) }}</span>
        </div>
        
        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 20px 0;" />
        
        <div class="summary-line total">
          <span>Tổng thanh toán</span>
          <span class="gradient-text">{{ formatPrice(total) }}</span>
        </div>

        <button 
          @click="placeOrder" 
          class="btn btn-primary w-100" 
          style="margin-top: 32px; height: 56px; font-size: 1.1rem;"
          :disabled="loading || !selectedAddressId"
        >
          {{ loading ? 'Đang xử lý...' : 'Xác nhận Đặt hàng' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkout-view { padding: 60px 0; }
.checkout-layout { display: flex; gap: 40px; align-items: flex-start; }
.checkout-forms { flex-grow: 1; }

.checkout-section { padding: 32px; margin-bottom: 24px; }
.section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
.section-header h3 { font-size: 1.25rem; font-weight: 700; }

.address-item, .zone-item {
  display: flex;
  gap: 16px;
  padding: 20px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.address-item:hover, .zone-item:hover { border-color: var(--accent-primary); background: rgba(255, 255, 255, 0.02); }
.address-item.active, .zone-item.active { border-color: var(--accent-primary); background: rgba(99, 102, 241, 0.05); }

.check-mark { color: transparent; border: 2px solid var(--border-color); width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.active .check-mark { background: var(--accent-primary); border-color: var(--accent-primary); color: white; }

.address-item p, .zone-item p { font-size: 0.9rem; color: var(--text-secondary); margin-top: 4px; }

.payment-method { display: flex; align-items: center; gap: 12px; padding: 16px; border-radius: 12px; background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); font-weight: 600; border: 1px solid rgba(99, 102, 241, 0.2); }

.checkout-note { width: 100%; height: 100px; padding: 16px; margin-top: 20px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-primary); outline: none; }

.order-summary-sidebar { width: 400px; padding: 32px; position: sticky; top: 100px; }
.summary-line { display: flex; justify-content: space-between; margin-bottom: 16px; }
.summary-line.total { font-size: 1.5rem; font-weight: 800; }

.empty-msg { text-align: center; color: var(--text-secondary); padding: 40px; }
.w-100 { width: 100%; }
</style>
