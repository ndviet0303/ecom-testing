<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const rows = ref([])
const loading = ref(true)
const statusFilter = ref('')
const updatingId = ref(null)
const searchQuery = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const statuses = ['pending', 'approved', 'rejected', 'received', 'refunded']

const fetchRows = async (targetPage = 1) => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/admin/return-requests', {
      params: {
        page: targetPage,
        per_page: perPage.value,
        status: statusFilter.value || undefined,
        q: searchQuery.value || undefined,
      },
    })

    rows.value = response.data.data || []
    page.value = response.data.current_page || targetPage
    lastPage.value = response.data.last_page || 1
    total.value = response.data.total || rows.value.length
  } catch (err) {
    console.error('Lỗi tải yêu cầu trả hàng:', err)
  } finally {
    loading.value = false
  }
}

const updateStatus = async (row, status) => {
  updatingId.value = row.id
  try {
    await axios.patch(`/api/v1/admin/return-requests/${row.id}`, {
      status,
      staff_note: row.staff_note || null,
    })
    await fetchRows(page.value)
  } catch (err) {
    alert('Không cập nhật được: ' + (err.response?.data?.message || err.message))
  } finally {
    updatingId.value = null
  }
}

watch([statusFilter, perPage], () => {
  fetchRows(1)
})

let searchTimer = null
watch(searchQuery, () => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    fetchRows(1)
  }, 300)
})

onMounted(() => fetchRows(1))
</script>

<template>
  <div class="return-admin">
    <div class="head">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Yêu cầu <span class="gradient-text">Trả hàng</span></h2>
      <div class="head-actions">
        <input v-model="searchQuery" class="field" placeholder="Tìm theo đơn, item, người dùng" />
        <select v-model="statusFilter" class="field">
          <option value="">Tất cả trạng thái</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-model.number="perPage" class="field">
          <option :value="10">10 / trang</option>
          <option :value="15">15 / trang</option>
          <option :value="25">25 / trang</option>
        </select>
        <button class="btn" @click="fetchRows(page)">Làm mới</button>
      </div>
    </div>

    <div class="result-meta">{{ total }} kết quả</div>

    <div v-if="loading" class="state-msg">Đang tải yêu cầu...</div>

    <div v-else-if="rows.length === 0" class="state-msg glass-panel">Không có yêu cầu trả hàng.</div>

    <div v-else class="list">
      <article v-for="row in rows" :key="row.id" class="item glass-panel">
        <header class="item-head">
          <div>
            <div class="title">#{{ row.id }} · Đơn #{{ row.order_id }} · Item #{{ row.order_item_id }}</div>
            <div class="meta">{{ row.user?.email || 'N/A' }} · Số lượng: {{ row.quantity }}</div>
          </div>
          <span class="badge">{{ row.status }}</span>
        </header>

        <p class="reason">Lý do: {{ row.reason || 'Không có' }}</p>

        <label class="field-label">Ghi chú staff</label>
        <textarea v-model="row.staff_note" class="field area" rows="2"></textarea>

        <div class="actions">
          <button
            v-for="s in statuses"
            :key="s"
            class="btn-small"
            :disabled="updatingId === row.id || s === row.status"
            @click="updateStatus(row, s)"
          >
            {{ s }}
          </button>
        </div>
      </article>
    </div>

    <footer v-if="lastPage > 1" class="pager">
      <button class="btn" :disabled="page <= 1" @click="fetchRows(page - 1)">Trước</button>
      <span>Trang {{ page }} / {{ lastPage }}</span>
      <button class="btn" :disabled="page >= lastPage" @click="fetchRows(page + 1)">Sau</button>
    </footer>
  </div>
</template>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  gap: 12px;
}

.head-actions {
  display: flex;
  gap: 8px;
}

.result-meta {
  color: var(--text-secondary);
  margin-bottom: 10px;
  font-size: 0.9rem;
}

.list {
  display: grid;
  gap: 14px;
}

.item {
  padding: 16px;
}

.item-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.title {
  font-weight: 700;
}

.meta {
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.badge {
  font-size: 0.75rem;
  padding: 6px 10px;
  border: 1px solid var(--border-color);
  border-radius: 999px;
  text-transform: uppercase;
}

.reason {
  margin: 0 0 10px;
  color: var(--text-secondary);
}

.field-label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.85rem;
}

.field {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  border-radius: 8px;
  padding: 8px 10px;
}

.area {
  width: 100%;
}

.actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
  flex-wrap: wrap;
}

.btn {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  border-radius: 8px;
  padding: 8px 12px;
  cursor: pointer;
}

.btn-small {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  border-radius: 8px;
  padding: 6px 10px;
  cursor: pointer;
  text-transform: uppercase;
  font-size: 0.75rem;
}

.btn-small:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.state-msg {
  text-align: center;
  padding: 56px;
  color: var(--text-secondary);
}

.pager {
  margin-top: 16px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
}

@media (max-width: 900px) {
  .head {
    align-items: flex-start;
    flex-direction: column;
  }

  .head-actions {
    width: 100%;
    flex-direction: column;
  }
}
</style>
