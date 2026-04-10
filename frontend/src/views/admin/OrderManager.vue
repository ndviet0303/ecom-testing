<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Eye, CheckCircle } from 'lucide-vue-next'

const orders = ref([])
const loading = ref(true)
const selectedOrder = ref(null)
const isUpdating = ref(false)
const isSavingFulfillment = ref(false)

const fetchOrders = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/admin/orders')
    orders.value = response.data.data || []
  } catch (err) {
    console.error('Lỗi tải đơn hàng:', err)
  } finally {
    loading.value = false
  }
}

const updateStatus = async (orderId, status) => {
  isUpdating.value = true
  try {
    await axios.patch(`/api/v1/admin/orders/${orderId}/status`, { status })
    await fetchOrders()
    selectedOrder.value = null
  } catch (err) {
    alert('Không thể cập nhật trạng thái: ' + (err.response?.data?.message || err.message))
  } finally {
    isUpdating.value = false
  }
}

const saveFulfillment = async () => {
  if (!selectedOrder.value) return

  isSavingFulfillment.value = true
  try {
    const payload = {
      tracking_number: selectedOrder.value.tracking_number || null,
      tracking_carrier: selectedOrder.value.tracking_carrier || null,
      internal_note: selectedOrder.value.internal_note || null,
      items: (selectedOrder.value.order_items || []).map((item) => ({
        id: item.id,
        serial_number: item.serial_number || null,
      })),
    }

    const response = await axios.patch(`/api/v1/admin/orders/${selectedOrder.value.id}/fulfillment`, payload)
    selectedOrder.value = response.data
    await fetchOrders()
  } catch (err) {
    alert('Không thể cập nhật fulfillment: ' + (err.response?.data?.message || err.message))
  } finally {
    isSavingFulfillment.value = false
  }
}

const getStatusClass = (status) => {
  switch (status) {
    case 'paid': return 'status-paid'
    case 'processing': return 'status-processing'
    case 'shipped': return 'status-shipped'
    case 'delivered': return 'status-delivered'
    case 'cancelled': return 'status-cancelled'
    default: return 'status-pending'
  }
}

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

onMounted(fetchOrders)
</script>

<template>
  <div class="order-manager">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Quản lý <span class="gradient-text">Đơn hàng</span></h2>
      <button @click="fetchOrders" class="btn" style="border: 1px solid var(--border-color); color: var(--text-primary);">Làm mới</button>
    </div>

    <div v-if="loading" class="state-msg">Đang tải đơn hàng...</div>
    
    <div v-else class="orders-table-container glass-panel">
      <table class="orders-table">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Khách hàng</th>
            <th>Ngày đặt</th>
            <th>Tổng tiền</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>
              <div style="font-weight: 600;">{{ order.user?.name || 'Guest' }}</div>
              <div style="font-size: 0.8rem; color: var(--text-secondary);">{{ order.user?.email }}</div>
            </td>
            <td>{{ new Date(order.created_at).toLocaleDateString('vi-VN') }}</td>
            <td>{{ formatPrice(order.total_cents) }}</td>
            <td>
              <span class="status-badge" :class="getStatusClass(order.status)">
                {{ order.status }}
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 12px;">
                <button class="action-btn" @click="selectedOrder = order"><Eye :size="18" /></button>
                <button class="action-btn" v-if="order.status === 'pending'" @click="updateStatus(order.id, 'paid')"><CheckCircle :size="18" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Chi tiết -->
    <div v-if="selectedOrder" class="modal-overlay" @click.self="selectedOrder = null">
      <div class="modal-content glass-panel">
        <header class="modal-header">
          <h3>Chi tiết đơn hàng #{{ selectedOrder.id }}</h3>
          <button @click="selectedOrder = null" class="close-btn">X</button>
        </header>
        
        <div class="modal-body">
          <section class="order-details-grid">
            <div>
              <label>Khách hàng:</label>
              <div class="v">{{ selectedOrder.user?.name }}</div>
            </div>
            <div>
              <label>Số điện thoại:</label>
              <div class="v">{{ selectedOrder.shipping_address?.phone || selectedOrder.shipping_address_snapshot?.phone || 'N/A' }}</div>
            </div>
          </section>

          <h4 style="margin: 24px 0 12px;">Sản phẩm</h4>
          <div class="order-items-list">
            <div v-for="item in selectedOrder.order_items" :key="item.id" class="item-row">
              <span class="qty">{{ item.quantity }}x</span>
              <span class="n">{{ item.product?.name }}</span>
              <span class="p">{{ formatPrice(item.unit_price_cents) }}</span>
            </div>
          </div>

          <div class="status-actions">
            <label>Fulfillment:</label>
            <div class="fulfillment-grid">
              <input v-model="selectedOrder.tracking_number" class="f-input" placeholder="Tracking number" />
              <input v-model="selectedOrder.tracking_carrier" class="f-input" placeholder="Carrier" />
              <textarea v-model="selectedOrder.internal_note" class="f-input" rows="2" placeholder="Internal note"></textarea>
            </div>

            <h4 style="margin: 16px 0 8px;">Serial theo từng item</h4>
            <div v-for="item in selectedOrder.order_items" :key="`serial-${item.id}`" class="serial-row">
              <span>#{{ item.id }} - {{ item.product?.name || 'Item' }}</span>
              <input v-model="item.serial_number" class="f-input" placeholder="Serial number" />
            </div>

            <button class="btn btn-sm" @click="saveFulfillment" :disabled="isSavingFulfillment">
              {{ isSavingFulfillment ? 'Đang lưu...' : 'Lưu fulfillment' }}
            </button>
          </div>

          <div class="status-actions">
            <label>Cập nhật trạng thái:</label>
            <div style="display: flex; gap: 12px; margin-top: 12px;">
              <button class="btn btn-sm" @click="updateStatus(selectedOrder.id, 'processing')" :disabled="isUpdating">Hành xử lý</button>
              <button class="btn btn-sm btn-primary" @click="updateStatus(selectedOrder.id, 'shipped')" :disabled="isUpdating">Giao hàng</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.orders-table-container { overflow-x: auto; padding: 0; }
.orders-table { width: 100%; border-collapse: collapse; }
.orders-table th, .orders-table td { padding: 20px 24px; text-align: left; border-bottom: 1px solid var(--border-color); }
.orders-table th { font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; }

.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
.status-pending { background: rgba(234, 179, 8, 0.1); color: #eab308; }
.status-paid { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.status-processing { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
.status-shipped { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
.status-delivered { background: #22c55e; color: white; }
.status-cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.action-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; transition: color 0.3s ease; }
.action-btn:hover { color: var(--accent-primary); }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { width: 90%; max-width: 600px; max-height: 85vh; padding: 32px; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }

.item-row { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }
.qty { color: var(--accent-primary); font-weight: 700; width: 30px; }
.n { flex-grow: 1; }
.p { font-weight: 600; }

.status-actions { margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 24px; }
.btn-sm { padding: 8px 16px; font-size: 0.85rem; border: 1px solid var(--border-color); background: none; color: var(--text-primary); cursor: pointer; border-radius: 8px; }

.fulfillment-grid {
  display: grid;
  gap: 10px;
  margin-top: 10px;
}

.serial-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  align-items: center;
  margin-bottom: 8px;
}

.f-input {
  width: 100%;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: transparent;
  color: var(--text-primary);
  padding: 8px 10px;
}

.state-msg { text-align: center; padding: 80px; color: var(--text-secondary); }
</style>
