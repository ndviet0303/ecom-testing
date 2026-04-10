<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/stores/authStore'
import { useToastStore } from '@/stores/toastStore'
import { Clock, MapPin, Package, ShoppingBag, User } from 'lucide-vue-next'

const auth = useAuthStore()
const toastStore = useToastStore()

const activeTab = ref('orders')

const orders = ref([])
const ordersLoading = ref(false)
const orderDetailLoading = ref(false)
const selectedOrder = ref(null)
const cancelReason = ref('')
const cancellingOrder = ref(false)

const addresses = ref([])
const addressesLoading = ref(false)
const addressFormVisible = ref(false)
const addressSaving = ref(false)
const editingAddressId = ref(null)

const accountSaving = ref(false)
const accountErrors = ref({})
const accountForm = ref({
  name: '',
  email: '',
  current_password: '',
  password: '',
  password_confirmation: '',
})

const addressErrors = ref({})
const addressForm = ref({
  label: 'Nhà riêng',
  recipient_name: '',
  phone: '',
  line1: '',
  line2: '',
  district: '',
  ward: '',
  province: '',
  is_default: false,
})

const statusLabels = {
  pending: 'Chờ thanh toán',
  paid: 'Đã thanh toán',
  processing: 'Đang xử lý',
  shipped: 'Đang giao hàng',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
}

const canCancelOrder = (status) => ['pending', 'paid'].includes(status)

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
  }).format(cents || 0)
}

const formatDate = (value) => {
  if (!value) return 'N/A'
  return new Date(value).toLocaleString('vi-VN')
}

const joinedDate = computed(() => {
  if (!auth.user?.created_at) return 'N/A'
  return new Date(auth.user.created_at).toLocaleDateString('vi-VN')
})

const roleLabel = computed(() => {
  if (auth.user?.role === 'admin') return 'Quản trị viên'
  if (auth.user?.role === 'staff') return 'Nhân viên'
  return 'Khách hàng'
})

const getStatusLabel = (status) => statusLabels[status] || status

const isValidEmail = (value) => /[^\s@]+@[^\s@]+\.[^\s@]+/.test(String(value || '').trim())

const isValidPhone = (value) => {
  const digits = String(value || '').replace(/\D/g, '')
  return digits.length >= 9 && digits.length <= 11
}

const validateAddressForm = () => {
  const errors = {}

  if (!String(addressForm.value.recipient_name || '').trim()) {
    errors.recipient_name = 'Vui lòng nhập tên người nhận.'
  }

  if (!String(addressForm.value.phone || '').trim()) {
    errors.phone = 'Vui lòng nhập số điện thoại.'
  } else if (!isValidPhone(addressForm.value.phone)) {
    errors.phone = 'Số điện thoại không hợp lệ.'
  }

  if (!String(addressForm.value.line1 || '').trim()) {
    errors.line1 = 'Vui lòng nhập số nhà, tên đường.'
  }

  if (!String(addressForm.value.district || '').trim()) {
    errors.district = 'Vui lòng nhập quận/huyện.'
  }

  if (!String(addressForm.value.province || '').trim()) {
    errors.province = 'Vui lòng nhập tỉnh/thành phố.'
  }

  addressErrors.value = errors
  return Object.keys(errors).length === 0
}

const validateAccountForm = () => {
  const errors = {}

  if (!String(accountForm.value.name || '').trim()) {
    errors.name = 'Vui lòng nhập họ và tên.'
  }

  if (!String(accountForm.value.email || '').trim()) {
    errors.email = 'Vui lòng nhập email.'
  } else if (!isValidEmail(accountForm.value.email)) {
    errors.email = 'Email không hợp lệ.'
  }

  const hasNewPassword = !!String(accountForm.value.password || '').trim()
  const hasCurrentPassword = !!String(accountForm.value.current_password || '').trim()

  if (hasNewPassword) {
    if (!hasCurrentPassword) {
      errors.current_password = 'Vui lòng nhập mật khẩu hiện tại để đổi mật khẩu.'
    }
    if (String(accountForm.value.password).length < 8) {
      errors.password = 'Mật khẩu mới phải có ít nhất 8 ký tự.'
    }
    if (accountForm.value.password !== accountForm.value.password_confirmation) {
      errors.password_confirmation = 'Xác nhận mật khẩu không khớp.'
    }
  }

  if (hasCurrentPassword && !hasNewPassword) {
    errors.password = 'Vui lòng nhập mật khẩu mới.'
  }

  accountErrors.value = errors
  return Object.keys(errors).length === 0
}

const clearAddressFieldError = (field) => {
  if (!addressErrors.value[field]) return
  addressErrors.value = {
    ...addressErrors.value,
    [field]: undefined,
  }
}

const clearAccountFieldError = (field) => {
  if (!accountErrors.value[field]) return
  accountErrors.value = {
    ...accountErrors.value,
    [field]: undefined,
  }
}

const fetchOrders = async () => {
  ordersLoading.value = true
  try {
    const response = await axios.get('/api/v1/orders', {
      params: { per_page: 20 },
    })
    orders.value = response.data.data || []
  } catch (err) {
    console.error('Lỗi tải lịch sử đơn hàng:', err)
  } finally {
    ordersLoading.value = false
  }
}

const fetchAddresses = async () => {
  addressesLoading.value = true
  try {
    const response = await axios.get('/api/v1/addresses')
    addresses.value = response.data.data || response.data || []
  } catch (err) {
    console.error('Lỗi tải sổ địa chỉ:', err)
  } finally {
    addressesLoading.value = false
  }
}

const refreshProfile = async () => {
  if (!auth.isLoggedIn) return

  await auth.fetchUser()

  accountForm.value.name = auth.user?.name || ''
  accountForm.value.email = auth.user?.email || ''
  accountForm.value.current_password = ''
  accountForm.value.password = ''
  accountForm.value.password_confirmation = ''

  await Promise.all([fetchOrders(), fetchAddresses()])
}

const showCreateAddressForm = () => {
  editingAddressId.value = null
  addressErrors.value = {}
  addressForm.value = {
    label: 'Nhà riêng',
    recipient_name: '',
    phone: '',
    line1: '',
    line2: '',
    district: '',
    ward: '',
    province: '',
    is_default: false,
  }
  addressFormVisible.value = true
}

const showEditAddressForm = (address) => {
  editingAddressId.value = address.id
  addressErrors.value = {}
  addressForm.value = {
    label: address.label || 'Nhà riêng',
    recipient_name: address.recipient_name || '',
    phone: address.phone || '',
    line1: address.line1 || '',
    line2: address.line2 || '',
    district: address.district || '',
    ward: address.ward || '',
    province: address.province || '',
    is_default: !!address.is_default,
  }
  addressFormVisible.value = true
}

const closeAddressForm = () => {
  addressFormVisible.value = false
  editingAddressId.value = null
  addressErrors.value = {}
}

const submitAddress = async () => {
  if (!validateAddressForm()) {
    toastStore.error('Vui lòng kiểm tra lại thông tin địa chỉ.')
    return
  }

  addressSaving.value = true
  try {
    const payload = {
      ...addressForm.value,
      district: String(addressForm.value.district || '').trim(),
      ward: String(addressForm.value.ward || '').trim() || String(addressForm.value.district || '').trim(),
      province: String(addressForm.value.province || '').trim(),
    }

    if (editingAddressId.value) {
      await axios.put(`/api/v1/addresses/${editingAddressId.value}`, payload)
    } else {
      await axios.post('/api/v1/addresses', payload)
    }

    await fetchAddresses()
    closeAddressForm()
    toastStore.success(editingAddressId.value ? 'Đã cập nhật địa chỉ.' : 'Đã thêm địa chỉ mới.')
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Không lưu được địa chỉ.')
  } finally {
    addressSaving.value = false
  }
}

const setDefaultAddress = async (address) => {
  try {
    await axios.put(`/api/v1/addresses/${address.id}`, {
      is_default: true,
    })
    await fetchAddresses()
    toastStore.success('Đã đặt địa chỉ mặc định.')
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Không đặt được địa chỉ mặc định.')
  }
}

const removeAddress = async (address) => {
  if (!confirm(`Xóa địa chỉ của ${address.recipient_name}?`)) return

  try {
    await axios.delete(`/api/v1/addresses/${address.id}`)
    await fetchAddresses()
    toastStore.success('Đã xóa địa chỉ.')
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Không xóa được địa chỉ.')
  }
}

const saveAccount = async () => {
  if (!validateAccountForm()) {
    toastStore.error('Vui lòng kiểm tra lại thông tin tài khoản.')
    return
  }

  accountSaving.value = true
  try {
    const payload = {
      name: accountForm.value.name,
      email: accountForm.value.email,
    }

    if (accountForm.value.password) {
      payload.current_password = accountForm.value.current_password
      payload.password = accountForm.value.password
      payload.password_confirmation = accountForm.value.password_confirmation
    }

    const response = await axios.patch('/api/auth/me', payload)
    auth.user = response.data

    accountForm.value.current_password = ''
    accountForm.value.password = ''
    accountForm.value.password_confirmation = ''
    accountErrors.value = {}
    toastStore.success('Đã cập nhật thông tin tài khoản.')
  } catch (err) {
    const validation = err.response?.data?.errors
    if (validation && typeof validation === 'object') {
      accountErrors.value = {
        ...accountErrors.value,
        ...Object.fromEntries(Object.entries(validation).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])),
      }
    }
    toastStore.error(err.response?.data?.message || 'Không cập nhật được tài khoản.')
  } finally {
    accountSaving.value = false
  }
}

const openOrderDetail = async (orderId) => {
  orderDetailLoading.value = true
  try {
    const response = await axios.get(`/api/v1/orders/${orderId}`)
    selectedOrder.value = response.data
    cancelReason.value = ''
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Không tải được chi tiết đơn hàng.')
  } finally {
    orderDetailLoading.value = false
  }
}

const closeOrderDetail = () => {
  selectedOrder.value = null
  cancelReason.value = ''
}

const cancelOrder = async () => {
  if (!selectedOrder.value) return
  if (!confirm('Bạn chắc chắn muốn hủy đơn hàng này?')) return

  cancellingOrder.value = true
  try {
    await axios.post(`/api/v1/orders/${selectedOrder.value.id}/cancel`, {
      reason: cancelReason.value || null,
    })

    await fetchOrders()
    await openOrderDetail(selectedOrder.value.id)
    toastStore.success('Đã hủy đơn hàng thành công.')
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Không hủy được đơn hàng.')
  } finally {
    cancellingOrder.value = false
  }
}

onMounted(refreshProfile)
</script>

<template>
  <div class="profile-view animate-fade-in">
    <div class="profile-layout">
      <aside class="profile-aside">
        <div class="user-card glass-panel">
          <div class="user-avatar">
            <User :size="32" />
          </div>

          <h2 style="font-size: 1.5rem; font-weight: 700;">{{ auth.user?.name }}</h2>
          <p style="color: var(--text-secondary); margin-bottom: 24px;">{{ auth.user?.email }}</p>

          <div class="user-meta">
            <div class="meta-item">
              <Clock :size="16" /> Tham gia: {{ joinedDate }}
            </div>
            <div class="meta-item">
              <MapPin :size="16" /> {{ roleLabel }}
            </div>
          </div>

          <button
            @click="auth.logout()"
            class="btn w-100"
            style="margin-top: 32px; border: 1px solid var(--border-color); color: var(--error);"
          >
            Đăng xuất
          </button>
        </div>
      </aside>

      <main class="profile-main">
        <section class="profile-tabs glass-panel">
          <button class="tab-btn" :class="{ active: activeTab === 'orders' }" @click="activeTab = 'orders'">
            <ShoppingBag :size="18" /> Đơn hàng
          </button>
          <button class="tab-btn" :class="{ active: activeTab === 'addresses' }" @click="activeTab = 'addresses'">
            <MapPin :size="18" /> Sổ địa chỉ
          </button>
          <button class="tab-btn" :class="{ active: activeTab === 'account' }" @click="activeTab = 'account'">
            <User :size="18" /> Tài khoản
          </button>
        </section>

        <section v-if="activeTab === 'orders'" class="panel glass-panel">
          <h3 class="panel-title"><ShoppingBag :size="22" /> Lịch sử đơn hàng</h3>

          <div v-if="ordersLoading" class="state-msg">Đang tải đơn hàng...</div>
          <div v-else-if="orders.length === 0" class="empty-orders">
            <Package :size="48" style="opacity: 0.2; margin-bottom: 16px;" />
            <p>Bạn chưa có đơn hàng nào.</p>
            <RouterLink to="/products" class="btn btn-primary" style="margin-top: 20px;">Mua sắm ngay</RouterLink>
          </div>

          <div v-else class="orders-list">
            <article v-for="order in orders" :key="order.id" class="order-card">
              <header class="order-header">
                <div>
                  <div class="order-id">Đơn #{{ order.id }}</div>
                  <div class="order-date">{{ formatDate(order.created_at) }}</div>
                </div>
                <div class="order-status" :class="`s-${order.status}`">
                  {{ getStatusLabel(order.status) }}
                </div>
              </header>

              <div class="order-preview">
                <div v-for="item in order.order_items?.slice(0, 3)" :key="item.id" class="mini-item">
                  {{ item.name }} (x{{ item.quantity }})
                </div>
                <div v-if="(order.order_items?.length || 0) > 3" class="more-items">
                  ...và {{ order.order_items.length - 3 }} sản phẩm khác
                </div>
              </div>

              <footer class="order-footer">
                <span>Tổng thanh toán: <strong class="gradient-text">{{ formatPrice(order.total_cents) }}</strong></span>
                <button class="btn btn-sm" @click="openOrderDetail(order.id)">Chi tiết</button>
              </footer>
            </article>
          </div>
        </section>

        <section v-if="activeTab === 'addresses'" class="panel glass-panel">
          <div class="panel-head">
            <h3 class="panel-title"><MapPin :size="22" /> Sổ địa chỉ</h3>
            <button class="btn btn-sm" @click="showCreateAddressForm">Thêm địa chỉ</button>
          </div>

          <div v-if="addressesLoading" class="state-msg">Đang tải địa chỉ...</div>
          <div v-else-if="addresses.length === 0" class="state-msg">Bạn chưa có địa chỉ nào.</div>

          <div v-else class="address-list">
            <article v-for="addr in addresses" :key="addr.id" class="address-card">
              <header class="address-head">
                <strong>{{ addr.recipient_name }} ({{ addr.phone }})</strong>
                <span v-if="addr.is_default" class="default-pill">Mặc định</span>
              </header>
              <p class="address-text">{{ addr.line1 }}, {{ addr.ward }}, {{ addr.district }}, {{ addr.province }}</p>
              <div class="address-actions">
                <button class="btn btn-sm" @click="showEditAddressForm(addr)">Sửa</button>
                <button class="btn btn-sm" @click="setDefaultAddress(addr)" :disabled="addr.is_default">Đặt mặc định</button>
                <button class="btn btn-sm danger" @click="removeAddress(addr)">Xóa</button>
              </div>
            </article>
          </div>

          <div v-if="addressFormVisible" class="form-box">
            <h4 style="margin-bottom: 12px;">{{ editingAddressId ? 'Cập nhật địa chỉ' : 'Thêm địa chỉ mới' }}</h4>
            <div class="form-grid">
              <div>
                <input v-model="addressForm.recipient_name" placeholder="Người nhận" @input="clearAddressFieldError('recipient_name')" />
                <p v-if="addressErrors.recipient_name" class="field-error">{{ addressErrors.recipient_name }}</p>
              </div>
              <div>
                <input v-model="addressForm.phone" placeholder="Số điện thoại" @input="clearAddressFieldError('phone')" />
                <p v-if="addressErrors.phone" class="field-error">{{ addressErrors.phone }}</p>
              </div>
              <div>
                <input v-model="addressForm.label" placeholder="Nhãn (Nhà riêng/Công ty)" />
              </div>
              <div>
                <input v-model="addressForm.line1" placeholder="Số nhà, tên đường" @input="clearAddressFieldError('line1')" />
                <p v-if="addressErrors.line1" class="field-error">{{ addressErrors.line1 }}</p>
              </div>
              <div>
                <input v-model="addressForm.line2" placeholder="Thông tin bổ sung" />
              </div>
              <div>
                <input v-model="addressForm.ward" placeholder="Phường/Xã" />
              </div>
              <div>
                <input v-model="addressForm.district" placeholder="Quận/Huyện" @input="clearAddressFieldError('district')" />
                <p v-if="addressErrors.district" class="field-error">{{ addressErrors.district }}</p>
              </div>
              <div>
                <input v-model="addressForm.province" placeholder="Tỉnh/Thành phố" @input="clearAddressFieldError('province')" />
                <p v-if="addressErrors.province" class="field-error">{{ addressErrors.province }}</p>
              </div>
              <label class="default-toggle">
                <input v-model="addressForm.is_default" type="checkbox" /> Đặt làm mặc định
              </label>
            </div>
            <div class="form-actions">
              <button class="btn btn-sm" :disabled="addressSaving" @click="submitAddress">{{ addressSaving ? 'Đang lưu...' : 'Lưu' }}</button>
              <button class="btn btn-sm" @click="closeAddressForm">Đóng</button>
            </div>
          </div>
        </section>

        <section v-if="activeTab === 'account'" class="panel glass-panel">
          <h3 class="panel-title"><User :size="22" /> Thông tin tài khoản</h3>

          <div class="form-grid">
            <div>
              <input v-model="accountForm.name" placeholder="Họ và tên" @input="clearAccountFieldError('name')" />
              <p v-if="accountErrors.name" class="field-error">{{ accountErrors.name }}</p>
            </div>
            <div>
              <input v-model="accountForm.email" placeholder="Email" @input="clearAccountFieldError('email')" />
              <p v-if="accountErrors.email" class="field-error">{{ accountErrors.email }}</p>
            </div>
            <div>
              <input v-model="accountForm.current_password" type="password" placeholder="Mật khẩu hiện tại (nếu đổi mật khẩu)" @input="clearAccountFieldError('current_password')" />
              <p v-if="accountErrors.current_password" class="field-error">{{ accountErrors.current_password }}</p>
            </div>
            <div>
              <input v-model="accountForm.password" type="password" placeholder="Mật khẩu mới" @input="clearAccountFieldError('password')" />
              <p v-if="accountErrors.password" class="field-error">{{ accountErrors.password }}</p>
            </div>
            <div>
              <input v-model="accountForm.password_confirmation" type="password" placeholder="Nhập lại mật khẩu mới" @input="clearAccountFieldError('password_confirmation')" />
              <p v-if="accountErrors.password_confirmation" class="field-error">{{ accountErrors.password_confirmation }}</p>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn-sm" :disabled="accountSaving" @click="saveAccount">{{ accountSaving ? 'Đang cập nhật...' : 'Lưu thông tin' }}</button>
          </div>
        </section>
      </main>
    </div>

    <div v-if="selectedOrder" class="modal-overlay" @click.self="closeOrderDetail">
      <div class="modal-content glass-panel">
        <header class="modal-header">
          <h3>Chi tiết đơn #{{ selectedOrder.id }}</h3>
          <button class="btn btn-sm" @click="closeOrderDetail">Đóng</button>
        </header>

        <div v-if="orderDetailLoading" class="state-msg">Đang tải chi tiết đơn...</div>
        <div v-else>
          <div class="detail-grid">
            <div><strong>Trạng thái:</strong> {{ getStatusLabel(selectedOrder.status) }}</div>
            <div><strong>Tạo lúc:</strong> {{ formatDate(selectedOrder.created_at) }}</div>
            <div><strong>Tạm tính:</strong> {{ formatPrice(selectedOrder.subtotal_cents) }}</div>
            <div><strong>Phí ship:</strong> {{ formatPrice(selectedOrder.shipping_cents) }}</div>
            <div><strong>Thuế:</strong> {{ formatPrice(selectedOrder.tax_cents) }}</div>
            <div><strong>Tổng:</strong> {{ formatPrice(selectedOrder.total_cents) }}</div>
          </div>

          <h4 style="margin: 16px 0 10px;">Sản phẩm</h4>
          <div class="order-preview">
            <div v-for="item in selectedOrder.order_items || []" :key="item.id" class="mini-item">
              {{ item.name }} (x{{ item.quantity }}) - {{ formatPrice(item.line_total_cents) }}
            </div>
          </div>

          <div v-if="canCancelOrder(selectedOrder.status)" class="cancel-box">
            <h4 style="margin-bottom: 8px;">Hủy đơn hàng</h4>
            <textarea v-model="cancelReason" rows="2" placeholder="Lý do hủy (tuỳ chọn)"></textarea>
            <button class="btn btn-sm danger" :disabled="cancellingOrder" @click="cancelOrder">{{ cancellingOrder ? 'Đang hủy...' : 'Hủy đơn' }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-view {
  padding: 48px 0;
}

.profile-layout {
  display: flex;
  gap: 28px;
  align-items: flex-start;
}

.profile-aside {
  width: 320px;
}

.profile-main {
  flex: 1;
  min-width: 0;
}

.user-card {
  padding: 28px;
  text-align: center;
}

.user-avatar {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: rgba(37, 99, 235, 0.1);
  color: var(--accent-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 18px;
}

.user-meta {
  display: grid;
  gap: 10px;
  text-align: left;
  background: var(--bg-secondary);
  padding: 14px;
  border-radius: 12px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.profile-tabs {
  display: flex;
  gap: 8px;
  padding: 10px;
  margin-bottom: 16px;
  overflow-x: auto;
}

.tab-btn {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-secondary);
  border-radius: 10px;
  padding: 10px 12px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  white-space: nowrap;
}

.tab-btn.active {
  color: var(--text-primary);
  border-color: var(--accent-primary);
  background: rgba(37, 99, 235, 0.08);
}

.panel {
  padding: 24px;
}

.panel-title {
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.orders-list,
.address-list {
  display: grid;
  gap: 12px;
}

.order-card,
.address-card {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 14px;
}

.order-header,
.address-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.order-id {
  font-weight: 700;
}

.order-date,
.address-text,
.more-items {
  color: var(--text-secondary);
  font-size: 0.88rem;
}

.order-status {
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 999px;
}

.s-pending {
  background: rgba(234, 179, 8, 0.1);
  color: #eab308;
}

.s-paid {
  background: rgba(34, 197, 94, 0.1);
  color: #22c55e;
}

.s-processing {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.s-shipped {
  background: rgba(168, 85, 247, 0.1);
  color: #a855f7;
}

.s-delivered {
  background: #22c55e;
  color: white;
}

.s-cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.order-preview {
  background: var(--bg-secondary);
  border-radius: 10px;
  padding: 10px;
  margin: 10px 0;
}

.mini-item {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 4px;
}

.order-footer,
.address-actions,
.form-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.default-pill {
  font-size: 0.74rem;
  border-radius: 999px;
  padding: 3px 8px;
  background: rgba(34, 197, 94, 0.12);
  color: #22c55e;
}

.form-box {
  margin-top: 16px;
  border-top: 1px solid var(--border-color);
  padding-top: 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.form-grid input,
.form-grid textarea,
.cancel-box textarea {
  width: 100%;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 10px;
  background: var(--bg-secondary);
  color: var(--text-primary);
}

.default-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
}

.field-error {
  color: #ef4444;
  font-size: 0.8rem;
  margin-top: 6px;
}

.state-msg,
.empty-orders {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px 20px;
}

.w-100 {
  width: 100%;
}

.btn-sm {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  padding: 7px 10px;
  border-radius: 8px;
  cursor: pointer;
}

.btn-sm.danger {
  border-color: rgba(239, 68, 68, 0.5);
  color: #ef4444;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 1000;
}

.modal-content {
  width: min(780px, 100%);
  max-height: 88vh;
  overflow: auto;
  padding: 20px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.cancel-box {
  margin-top: 14px;
  border-top: 1px solid var(--border-color);
  padding-top: 12px;
}

@media (max-width: 980px) {
  .profile-layout {
    flex-direction: column;
  }

  .profile-aside {
    width: 100%;
  }

  .form-grid,
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
