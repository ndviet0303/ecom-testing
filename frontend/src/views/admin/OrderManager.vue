<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import {
  Eye,
  RefreshCcw,
  Box,
  Truck,
  CheckCircle2,
  Save,
  X,
  Wallet,
} from "lucide-vue-next";
import { useToastStore } from "@/stores/toastStore";

const toastStore = useToastStore();

const orders = ref([]);
const loading = ref(true);
const loadError = ref("");
const selectedOrder = ref(null);
const isUpdating = ref(false);
const isSavingFulfillment = ref(false);

const statusLabelMap = {
  pending: "Cho thanh toan",
  paid: "Da thanh toan",
  packed: "Da xu ly",
  shipped: "Dang giao",
  delivered: "Da giao",
  cancelled: "Da huy",
};

const allowedTransitions = {
  pending: ["paid", "cancelled"],
  paid: ["packed", "cancelled"],
  packed: ["shipped"],
  shipped: ["delivered"],
  delivered: [],
  cancelled: [],
};

const parseApiError = (err, fallback) => {
  const message = err?.response?.data?.message;
  const errors = err?.response?.data?.errors;
  if (typeof message === "string" && message.trim() !== "") return message;
  if (errors && typeof errors === "object") {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) return String(first[0]);
  }
  return fallback;
};

const orderItems = (order) => order?.order_items || order?.orderItems || [];

const normalizeOrder = (order) => ({
  ...order,
  order_items: orderItems(order),
});

const formatPrice = (cents) => {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(Number(cents || 0));
};

const formatDateTime = (value) => {
  if (!value) return "-";
  return new Date(value).toLocaleString("vi-VN");
};

const getStatusClass = (status) => `status-${status || "pending"}`;

const statusLabel = (status) => statusLabelMap[status] || status || "Unknown";

const canTransition = (fromStatus, toStatus) => {
  return (allowedTransitions[fromStatus] || []).includes(toStatus);
};

const openOrder = (order) => {
  selectedOrder.value = normalizeOrder(structuredClone(order));
};

const closeOrder = () => {
  if (isUpdating.value || isSavingFulfillment.value) return;
  selectedOrder.value = null;
};

const fetchOrders = async () => {
  loading.value = true;
  loadError.value = "";
  try {
    const response = await axios.get("/api/v1/admin/orders");
    orders.value = (response.data.data || []).map(normalizeOrder);
  } catch (err) {
    loadError.value = parseApiError(err, "Khong the tai danh sach don hang.");
    toastStore.error(loadError.value);
  } finally {
    loading.value = false;
  }
};

const syncOrderInList = (updatedOrder) => {
  const normalized = normalizeOrder(updatedOrder);
  const idx = orders.value.findIndex((row) => row.id === normalized.id);
  if (idx >= 0) {
    orders.value[idx] = normalized;
  }
  if (selectedOrder.value?.id === normalized.id) {
    selectedOrder.value = structuredClone(normalized);
  }
};

const updateStatus = async (orderId, status) => {
  isUpdating.value = true;
  try {
    const response = await axios.patch(
      `/api/v1/admin/orders/${orderId}/status`,
      {
        status,
      },
    );
    syncOrderInList(response.data);
    toastStore.success("Da cap nhat trang thai don hang.");
  } catch (err) {
    toastStore.error(parseApiError(err, "Khong the cap nhat trang thai."));
  } finally {
    isUpdating.value = false;
  }
};

const triggerTransition = async (toStatus) => {
  if (!selectedOrder.value) return;

  if (toStatus === "shipped") {
    const tracking = String(selectedOrder.value.tracking_number || "").trim();
    if (!tracking) {
      toastStore.error("Can nhap ma van don truoc khi giao hang.");
      return;
    }
  }

  await updateStatus(selectedOrder.value.id, toStatus);
};

const saveFulfillment = async () => {
  if (!selectedOrder.value) return;

  isSavingFulfillment.value = true;
  try {
    const payload = {
      tracking_number: selectedOrder.value.tracking_number || null,
      tracking_carrier: selectedOrder.value.tracking_carrier || null,
      internal_note: selectedOrder.value.internal_note || null,
      items: orderItems(selectedOrder.value).map((item) => ({
        id: item.id,
        serial_number: item.serial_number || null,
      })),
    };

    const response = await axios.patch(
      `/api/v1/admin/orders/${selectedOrder.value.id}/fulfillment`,
      payload,
    );

    syncOrderInList(response.data);
    toastStore.success("Da luu thong tin xu ly don.");
  } catch (err) {
    toastStore.error(parseApiError(err, "Khong the luu thong tin xu ly."));
  } finally {
    isSavingFulfillment.value = false;
  }
};

onMounted(fetchOrders);
</script>

<template>
  <div class="order-manager">
    <header class="page-head">
      <div>
        <h2>Quan ly <span class="gradient-text">Don hang</span></h2>
        <p>Theo doi va xu ly don theo dung luong van hanh.</p>
      </div>
      <button class="refresh-btn" @click="fetchOrders" :disabled="loading">
        <RefreshCcw :size="16" />
        Lam moi
      </button>
    </header>

    <div v-if="loading" class="state-msg">Dang tai don hang...</div>
    <div v-else-if="loadError" class="state-msg state-error">
      {{ loadError }}
    </div>

    <div v-else class="orders-table-container glass-panel">
      <table class="orders-table">
        <thead>
          <tr>
            <th>Ma don</th>
            <th>Khach hang</th>
            <th>Ngay dat</th>
            <th>Tong tien</th>
            <th>Trang thai</th>
            <th>Hanh dong</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>
              <div class="customer-name">{{ order.user?.name || "Guest" }}</div>
              <div class="customer-email">{{ order.user?.email || "-" }}</div>
            </td>
            <td>{{ formatDateTime(order.created_at) }}</td>
            <td>{{ formatPrice(order.total_cents) }}</td>
            <td>
              <span class="status-badge" :class="getStatusClass(order.status)">
                {{ statusLabel(order.status) }}
              </span>
            </td>
            <td>
              <div class="table-actions">
                <button
                  class="icon-btn"
                  @click="openOrder(order)"
                  title="Xem chi tiet"
                >
                  <Eye :size="17" />
                </button>
                <button
                  v-if="canTransition(order.status, 'packed')"
                  class="mini-btn"
                  @click="updateStatus(order.id, 'packed')"
                  :disabled="isUpdating"
                >
                  Xu ly
                </button>
                <button
                  v-if="canTransition(order.status, 'shipped')"
                  class="mini-btn mini-btn-primary"
                  @click="openOrder(order)"
                >
                  Giao hang
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedOrder" class="modal-overlay" @click.self="closeOrder">
      <div class="modal-content glass-panel">
        <header class="modal-header">
          <div>
            <h3>Don hang #{{ selectedOrder.id }}</h3>
            <p>{{ formatDateTime(selectedOrder.created_at) }}</p>
          </div>
          <div class="modal-head-right">
            <span
              class="status-badge"
              :class="getStatusClass(selectedOrder.status)"
            >
              {{ statusLabel(selectedOrder.status) }}
            </span>
            <button class="close-btn" @click="closeOrder">
              <X :size="16" />
            </button>
          </div>
        </header>

        <section class="summary-grid">
          <article class="summary-card">
            <label>Khach hang</label>
            <div>{{ selectedOrder.user?.name || "Guest" }}</div>
            <small>{{ selectedOrder.user?.email || "-" }}</small>
          </article>
          <article class="summary-card">
            <label>Lien he giao hang</label>
            <div>
              {{
                selectedOrder.shipping_address?.phone ||
                selectedOrder.shipping_address_snapshot?.phone ||
                "-"
              }}
            </div>
            <small>
              {{
                selectedOrder.shipping_address_snapshot?.line1 ||
                selectedOrder.shipping_address?.line1 ||
                "Khong co dia chi"
              }}
            </small>
          </article>
          <article class="summary-card summary-card-money">
            <label>Tong thanh toan</label>
            <div>{{ formatPrice(selectedOrder.total_cents) }}</div>
            <small>
              <Wallet :size="14" />
              Tam tinh {{ formatPrice(selectedOrder.subtotal_cents) }}
            </small>
          </article>
        </section>

        <section class="block">
          <div class="block-title">San pham trong don</div>
          <div class="order-items-list">
            <div
              v-for="item in orderItems(selectedOrder)"
              :key="item.id"
              class="item-row"
            >
              <div class="item-main">
                <div class="item-name">
                  {{ item.name || item.product?.name || "San pham" }}
                </div>
                <div class="item-sub">SKU: {{ item.sku || "-" }}</div>
              </div>
              <div class="item-qty">x{{ item.quantity }}</div>
              <div class="item-price">
                {{
                  formatPrice(
                    (item.quantity || 0) * (item.unit_price_cents || 0),
                  )
                }}
              </div>
            </div>
          </div>
        </section>

        <section class="block">
          <div class="block-title">Thong tin xu ly va giao hang</div>
          <div class="form-grid">
            <input
              v-model="selectedOrder.tracking_number"
              class="f-input"
              placeholder="Ma van don"
            />
            <input
              v-model="selectedOrder.tracking_carrier"
              class="f-input"
              placeholder="Don vi van chuyen"
            />
            <textarea
              v-model="selectedOrder.internal_note"
              class="f-input"
              rows="2"
              placeholder="Ghi chu noi bo"
            ></textarea>
          </div>

          <div class="serial-list">
            <div class="serial-title">Serial theo item</div>
            <div
              v-for="item in orderItems(selectedOrder)"
              :key="`serial-${item.id}`"
              class="serial-row"
            >
              <span
                >#{{ item.id }} -
                {{ item.name || item.product?.name || "Item" }}</span
              >
              <input
                v-model="item.serial_number"
                class="f-input"
                placeholder="Serial number"
              />
            </div>
          </div>
        </section>

        <footer class="modal-footer">
          <button
            class="btn-outline"
            @click="saveFulfillment"
            :disabled="isSavingFulfillment"
          >
            <Save :size="15" />
            {{ isSavingFulfillment ? "Dang luu..." : "Luu thong tin" }}
          </button>

          <div class="status-action-group">
            <button
              v-if="canTransition(selectedOrder.status, 'pending')"
              class="btn-status"
              :disabled="isUpdating"
              @click="triggerTransition('pending')"
            >
              Pending
            </button>
            <button
              v-if="canTransition(selectedOrder.status, 'paid')"
              class="btn-status"
              :disabled="isUpdating"
              @click="triggerTransition('paid')"
            >
              <CheckCircle2 :size="15" />
              Xac nhan da thanh toan
            </button>
            <button
              v-if="canTransition(selectedOrder.status, 'packed')"
              class="btn-status"
              :disabled="isUpdating"
              @click="triggerTransition('packed')"
            >
              <Box :size="15" />
              Xu ly don
            </button>
            <button
              v-if="canTransition(selectedOrder.status, 'shipped')"
              class="btn-status btn-status-primary"
              :disabled="isUpdating"
              @click="triggerTransition('shipped')"
            >
              <Truck :size="15" />
              Giao hang
            </button>
            <button
              v-if="canTransition(selectedOrder.status, 'delivered')"
              class="btn-status"
              :disabled="isUpdating"
              @click="triggerTransition('delivered')"
            >
              Da giao thanh cong
            </button>
          </div>
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 28px;
}

.page-head h2 {
  font-size: 1.75rem;
  font-weight: 750;
  margin: 0;
}

.page-head p {
  margin: 8px 0 0;
  color: var(--text-secondary);
}

.refresh-btn {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  padding: 10px 14px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.refresh-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.orders-table-container {
  overflow-x: auto;
  padding: 0;
}

.orders-table {
  width: 100%;
  border-collapse: collapse;
}

.orders-table th,
.orders-table td {
  padding: 18px 20px;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.orders-table th {
  font-size: 0.78rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.06em;
}

.customer-name {
  font-weight: 650;
}

.customer-email {
  font-size: 0.82rem;
  color: var(--text-secondary);
}

.status-badge {
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
}

.status-pending {
  background: rgba(234, 179, 8, 0.12);
  color: #d97706;
}

.status-paid {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.status-packed {
  background: rgba(37, 99, 235, 0.11);
  color: #2563eb;
}

.status-shipped {
  background: rgba(14, 116, 144, 0.12);
  color: #0e7490;
}

.status-delivered {
  background: #16a34a;
  color: #ffffff;
}

.status-cancelled {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.table-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.icon-btn,
.mini-btn {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.icon-btn {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.mini-btn {
  padding: 7px 11px;
  font-size: 0.78rem;
  font-weight: 600;
}

.mini-btn-primary {
  background: rgba(37, 99, 235, 0.12);
  color: #2563eb;
  border-color: rgba(37, 99, 235, 0.32);
}

.icon-btn:hover,
.mini-btn:hover {
  transform: translateY(-1px);
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(9, 11, 20, 0.74);
  backdrop-filter: blur(5px);
  display: grid;
  place-items: center;
  z-index: 1200;
  padding: 24px;
}

.modal-content {
  width: min(980px, 100%);
  max-height: 90vh;
  overflow: auto;
  padding: 24px;
  border-radius: 16px;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.modal-header p {
  margin: 6px 0 0;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.modal-head-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.close-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.summary-card {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 14px;
  display: grid;
  gap: 6px;
}

.summary-card label {
  color: var(--text-secondary);
  font-size: 0.78rem;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.summary-card div {
  font-weight: 650;
}

.summary-card small {
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.summary-card-money div {
  font-size: 1.15rem;
}

.block {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 14px;
}

.block-title {
  font-weight: 700;
  margin-bottom: 12px;
}

.order-items-list {
  display: grid;
}

.item-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 14px;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px dashed var(--border-color);
}

.item-row:last-child {
  border-bottom: none;
}

.item-name {
  font-weight: 620;
}

.item-sub {
  color: var(--text-secondary);
  font-size: 0.82rem;
  margin-top: 4px;
}

.item-qty {
  font-weight: 700;
  color: var(--accent-primary);
}

.item-price {
  font-weight: 680;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.form-grid textarea {
  grid-column: span 2;
}

.f-input {
  width: 100%;
  border: 1px solid var(--border-color);
  border-radius: 9px;
  background: transparent;
  color: var(--text-primary);
  padding: 10px;
}

.serial-list {
  margin-top: 12px;
}

.serial-title {
  font-size: 0.86rem;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.serial-row {
  display: grid;
  grid-template-columns: minmax(170px, 1fr) 1fr;
  gap: 10px;
  align-items: center;
  margin-bottom: 8px;
}

.modal-footer {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
  margin-top: 8px;
  flex-wrap: wrap;
}

.btn-outline,
.btn-status {
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  padding: 9px 12px;
  font-weight: 620;
  font-size: 0.86rem;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.btn-status-primary {
  background: rgba(37, 99, 235, 0.14);
  border-color: rgba(37, 99, 235, 0.36);
  color: #2563eb;
}

.btn-outline:disabled,
.btn-status:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.status-action-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.state-msg {
  text-align: center;
  padding: 80px;
  color: var(--text-secondary);
}

.state-error {
  color: #ef4444;
}

@media (max-width: 960px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .form-grid textarea {
    grid-column: span 1;
  }

  .serial-row {
    grid-template-columns: 1fr;
  }

  .modal-content {
    padding: 18px;
  }
}
</style>
