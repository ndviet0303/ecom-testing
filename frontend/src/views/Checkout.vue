<script setup>
import { ref, onMounted, computed } from 'vue'
import { useCartStore } from '@/stores/cartStore'
import { useAuthStore } from '@/stores/authStore'
import { useToastStore } from '@/stores/toastStore'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { MapPin, Truck, CreditCard, CheckCircle } from 'lucide-vue-next'
import administrativeUnits from '@/data/vn-administrative-units.json'

const cartStore = useCartStore()
const authStore = useAuthStore()
const toastStore = useToastStore()
const router = useRouter()

const zones = ref([])
const addresses = ref([])
const fulfillmentMethod = ref('shipping')
const selectedZoneId = ref(null)
const selectedAddressId = ref(null)
const note = ref('')
const loading = ref(false)
const savingAddress = ref(false)
const showAddressForm = ref(false)
const newAddress = ref({
  label: 'Nhà riêng',
  recipient_name: '',
  phone: '',
  line1: '',
  ward: '',
  district: '',
  province: '',
  is_default: true
})
const selectedProvinceCode = ref('')
const selectedWardCode = ref('')

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
const provinceOptions = administrativeUnits
const wardOptions = computed(() => {
  const province = provinceOptions.find((item) => item.Code === selectedProvinceCode.value)
  return province?.Wards || []
})
const shippingFee = computed(() => {
    if (fulfillmentMethod.value === 'pickup' || !selectedZone.value) return 0
    const weightKg = 15
    return selectedZone.value.rate_per_kg_cents * weightKg
})

const total = computed(() => cartStore.subtotal + shippingFee.value)
const canPlaceOrder = computed(() => {
  if (loading.value || cartStore.loading || cartStore.items.length === 0) return false
  if (fulfillmentMethod.value === 'pickup') return true
  return !!selectedAddressId.value && !!selectedZoneId.value
})

const resetAddressForm = () => {
  newAddress.value = {
    label: 'Nhà riêng',
    recipient_name: '',
    phone: '',
    line1: '',
    ward: '',
    district: '',
    province: '',
    is_default: true
  }
  selectedProvinceCode.value = ''
  selectedWardCode.value = ''
}

const syncAddressSelection = () => {
  const province = provinceOptions.find((item) => item.Code === selectedProvinceCode.value)
  const ward = wardOptions.value.find((item) => item.Code === selectedWardCode.value)

  newAddress.value.province = province?.FullName || ''
  newAddress.value.ward = ward?.FullName || ''
  // Dataset 2025 uses 2 administrative tiers. Keep backend compatibility by mirroring ward into district.
  newAddress.value.district = ward?.FullName || ''
}

const handleProvinceChange = (event) => {
  selectedProvinceCode.value = event.target.value
  selectedWardCode.value = ''
  syncAddressSelection()
}

const handleWardChange = (event) => {
  selectedWardCode.value = event.target.value
  syncAddressSelection()
}

const submitNewAddress = async () => {
  syncAddressSelection()

  if (!newAddress.value.province || !newAddress.value.ward) {
    toastStore.error('Chọn Tỉnh/Thành phố và Phường/Xã trước khi lưu địa chỉ.')
    return
  }

  savingAddress.value = true
  try {
    const response = await axios.post('/api/v1/addresses', newAddress.value)
    const created = response.data
    addresses.value = [created, ...addresses.value.filter((addr) => addr.id !== created.id)]
    if (created.is_default) {
      addresses.value = addresses.value.map((addr) => ({
        ...addr,
        is_default: addr.id === created.id
      }))
    }
    selectedAddressId.value = created.id
    showAddressForm.value = false
    resetAddressForm()
    toastStore.success('Đã thêm địa chỉ mới.')
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Không thể thêm địa chỉ.')
  } finally {
    savingAddress.value = false
  }
}

const placeOrder = async () => {
  if (!canPlaceOrder.value) return

  loading.value = true
  try {
    const response = await axios.post('/api/v1/checkout', {
      fulfillment_method: fulfillmentMethod.value,
      shipping_address_id: fulfillmentMethod.value === 'shipping' ? selectedAddressId.value : null,
      shipping_zone_id: fulfillmentMethod.value === 'shipping' ? selectedZoneId.value : null,
      weight_grams: fulfillmentMethod.value === 'shipping' ? 15000 : 0,
      tax_rate_basis_points: 0,
      payment_method: 'sepay_qr',
      customer_note: note.value
    }, {
      headers: { 'X-Cart-Token': cartStore.cartToken }
    })
    
    // Clear cart locally
    cartStore.items = []
    cartStore.subtotal = 0
    
    router.push({ name: 'order-success', params: { id: response.data.order.id } })
  } catch (err) {
    const validationErrors = err.response?.data?.errors
    const firstValidationMessage = validationErrors
      ? Object.values(validationErrors).flat()[0]
      : null

    toastStore.error(firstValidationMessage || err.response?.data?.message || 'Lỗi khi đặt hàng.')
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

onMounted(async () => {
    await cartStore.fetchCart()
    await fetchData()
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
            <Truck :size="20" color="var(--accent-primary)" />
            <h3>Hình thức nhận hàng</h3>
          </div>
          <div class="zone-list">
            <div
              class="zone-item"
              :class="{ active: fulfillmentMethod === 'shipping' }"
              @click="fulfillmentMethod = 'shipping'"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>Giao hàng tận nơi</strong>
                <p>Chọn địa chỉ và khu vực vận chuyển.</p>
              </div>
            </div>
            <div
              class="zone-item"
              :class="{ active: fulfillmentMethod === 'pickup' }"
              @click="fulfillmentMethod = 'pickup'"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>Tự đến lấy</strong>
                <p>Nhận tại cửa hàng, không tính phí ship.</p>
              </div>
            </div>
          </div>
        </section>

        <section v-if="fulfillmentMethod === 'shipping'" class="checkout-section glass-panel">
          <div class="section-header">
            <MapPin :size="20" color="var(--accent-primary)" />
            <h3>Địa chỉ nhận hàng</h3>
            <button class="link-btn" @click="showAddressForm = !showAddressForm">
              {{ showAddressForm ? 'Đóng' : 'Thêm địa chỉ' }}
            </button>
          </div>
          <div v-if="showAddressForm" class="address-form">
            <div class="address-label-options">
              <button
                type="button"
                class="label-chip"
                :class="{ active: newAddress.label === 'Nhà riêng' }"
                @click="newAddress.label = 'Nhà riêng'"
              >
                Nhà riêng
              </button>
              <button
                type="button"
                class="label-chip"
                :class="{ active: newAddress.label === 'Công ty' }"
                @click="newAddress.label = 'Công ty'"
              >
                Công ty
              </button>
            </div>
            <input v-model="newAddress.recipient_name" type="text" placeholder="Người nhận" />
            <input v-model="newAddress.phone" type="text" placeholder="Số điện thoại" />
            <input v-model="newAddress.line1" type="text" placeholder="Số nhà, tên đường" />
            <select :value="selectedProvinceCode" @change="handleProvinceChange">
              <option value="">Chọn Tỉnh/Thành phố</option>
              <option v-for="province in provinceOptions" :key="province.Code" :value="province.Code">
                {{ province.FullName }}
              </option>
            </select>
            <select :value="selectedWardCode" @change="handleWardChange" :disabled="!selectedProvinceCode">
              <option value="">Chọn Phường/Xã</option>
              <option v-for="ward in wardOptions" :key="ward.Code" :value="ward.Code">
                {{ ward.FullName }}
              </option>
            </select>
            <button
              type="button"
              class="default-toggle"
              :class="{ active: newAddress.is_default }"
              @click="newAddress.is_default = !newAddress.is_default"
            >
              <span class="toggle-knob">
                <CheckCircle v-if="newAddress.is_default" :size="14" />
              </span>
              <span class="toggle-copy">
                <strong>Đặt làm địa chỉ mặc định</strong>
                <small>Địa chỉ này sẽ được chọn sẵn cho lần mua tiếp theo.</small>
              </span>
            </button>
            <button class="btn btn-primary" @click="submitNewAddress" :disabled="savingAddress">
              {{ savingAddress ? 'Đang lưu...' : 'Lưu địa chỉ' }}
            </button>
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
          <span>{{ fulfillmentMethod === 'pickup' ? 'Nhận tại cửa hàng' : 'Phí vận chuyển dự kiến' }}</span>
          <span>{{ fulfillmentMethod === 'pickup' ? formatPrice(0) : `+ ${formatPrice(shippingFee)}` }}</span>
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
          :disabled="!canPlaceOrder"
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
.link-btn {
  margin-left: auto;
  background: none;
  border: none;
  color: var(--accent-primary);
  cursor: pointer;
  font-weight: 700;
}
.address-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}
.address-label-options {
  grid-column: 1 / -1;
  display: flex;
  gap: 10px;
}
.label-chip {
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-primary);
  border-radius: 999px;
  padding: 12px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.label-chip.active {
  border-color: var(--accent-primary);
  background: rgba(99, 102, 241, 0.12);
  color: var(--accent-primary);
}
.address-form input,
.address-form select {
  width: 100%;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 14px 16px;
  color: var(--text-primary);
  outline: none;
}
.address-form select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.address-form > :nth-child(4) {
  grid-column: 1 / -1;
}
.default-toggle {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--border-color);
  background: #fff;
  border-radius: 14px;
  padding: 14px 16px;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
}
.default-toggle.active {
  border-color: var(--accent-primary);
  background: rgba(99, 102, 241, 0.08);
}
.toggle-knob {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  border: 2px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: center;
  color: transparent;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.default-toggle.active .toggle-knob {
  border-color: var(--accent-primary);
  background: var(--accent-primary);
  color: #fff;
}
.toggle-copy {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.toggle-copy strong {
  color: var(--text-primary);
  font-size: 0.95rem;
}
.toggle-copy small {
  color: var(--text-secondary);
  font-size: 0.82rem;
}
.address-form .btn {
  grid-column: 1 / -1;
}
@media (max-width: 1024px) {
  .checkout-layout {
    flex-direction: column;
  }
  .order-summary-sidebar {
    width: 100%;
    position: static;
  }
}
@media (max-width: 720px) {
  .address-form {
    grid-template-columns: 1fr;
  }
}
.w-100 { width: 100%; }
</style>
