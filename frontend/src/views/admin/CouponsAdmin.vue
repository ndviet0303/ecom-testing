<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const rows = ref([])
const loading = ref(true)
const saving = ref(false)
const searchQuery = ref('')
const activeFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const form = ref({
  code: '',
  discount_cents: 0,
  min_subtotal_cents: 0,
  max_uses: null,
  max_uses_per_user: null,
  expires_at: '',
  is_active: true,
})

const resetForm = () => {
  form.value = {
    code: '',
    discount_cents: 0,
    min_subtotal_cents: 0,
    max_uses: null,
    max_uses_per_user: null,
    expires_at: '',
    is_active: true,
  }
}

const fetchRows = async (targetPage = 1) => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/admin/coupons', {
      params: {
        page: targetPage,
        per_page: perPage.value,
        q: searchQuery.value || undefined,
        is_active: activeFilter.value || undefined,
      },
    })

    rows.value = response.data.data || []
    page.value = response.data.current_page || targetPage
    lastPage.value = response.data.last_page || 1
    total.value = response.data.total || rows.value.length
  } catch (err) {
    console.error('Lỗi tải coupon:', err)
  } finally {
    loading.value = false
  }
}

const createCoupon = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/admin/coupons', {
      ...form.value,
      code: String(form.value.code || '').trim(),
      expires_at: form.value.expires_at || null,
    })
    resetForm()
    await fetchRows(page.value)
  } catch (err) {
    alert('Không tạo được coupon: ' + (err.response?.data?.message || err.message))
  } finally {
    saving.value = false
  }
}

const toggleActive = async (row) => {
  try {
    await axios.put(`/api/v1/admin/coupons/${row.id}`, {
      is_active: !row.is_active,
    })
    await fetchRows(page.value)
  } catch (err) {
    alert('Không cập nhật được coupon: ' + (err.response?.data?.message || err.message))
  }
}

const deleteRow = async (row) => {
  if (!confirm(`Xóa coupon ${row.code}?`)) return

  try {
    await axios.delete(`/api/v1/admin/coupons/${row.id}`)
    await fetchRows(page.value)
  } catch (err) {
    alert('Không xóa được coupon: ' + (err.response?.data?.message || err.message))
  }
}

watch([searchQuery, activeFilter, perPage], () => {
  fetchRows(1)
})

onMounted(() => fetchRows(1))
</script>

<template>
  <div class="coupon-admin">
    <div class="head">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Quản lý <span class="gradient-text">Coupon</span></h2>
      <button class="btn" @click="fetchRows(page)">Làm mới</button>
    </div>

    <section class="filter-bar glass-panel">
      <input v-model="searchQuery" class="field" placeholder="Tìm theo mã / số tiền" />
      <select v-model="activeFilter" class="field">
        <option value="">Tất cả trạng thái</option>
        <option value="true">Đang hoạt động</option>
        <option value="false">Đã tắt</option>
      </select>
      <select v-model.number="perPage" class="field">
        <option :value="10">10 / trang</option>
        <option :value="15">15 / trang</option>
        <option :value="25">25 / trang</option>
      </select>
      <div class="result-meta">{{ total }} kết quả</div>
    </section>

    <section class="create-box glass-panel">
      <h3>Tạo coupon mới</h3>
      <div class="form-grid">
        <input v-model="form.code" class="field" placeholder="Code" />
        <input v-model.number="form.discount_cents" class="field" type="number" min="0" placeholder="Discount (cents)" />
        <input v-model.number="form.min_subtotal_cents" class="field" type="number" min="0" placeholder="Min subtotal (cents)" />
        <input v-model.number="form.max_uses" class="field" type="number" min="1" placeholder="Max uses" />
        <input v-model.number="form.max_uses_per_user" class="field" type="number" min="1" placeholder="Max uses/user" />
        <input v-model="form.expires_at" class="field" type="datetime-local" />
        <label class="toggle">
          <input v-model="form.is_active" type="checkbox" /> Active
        </label>
      </div>
      <button class="btn" :disabled="saving" @click="createCoupon">Tao coupon</button>
    </section>

    <div v-if="loading" class="state-msg">Đang tải coupon...</div>
    <div v-else-if="rows.length === 0" class="state-msg glass-panel">Chưa có coupon.</div>

    <table v-else class="table glass-panel">
      <thead>
        <tr>
          <th>Code</th>
          <th>Discount</th>
          <th>Min subtotal</th>
          <th>Active</th>
          <th>Expires</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.code }}</td>
          <td>{{ row.discount_cents }}</td>
          <td>{{ row.min_subtotal_cents }}</td>
          <td>{{ row.is_active ? 'Có' : 'Không' }}</td>
          <td>{{ row.expires_at ? new Date(row.expires_at).toLocaleString('vi-VN') : '-' }}</td>
          <td class="actions">
            <button class="btn-small" @click="toggleActive(row)">{{ row.is_active ? 'Tắt' : 'Bật' }}</button>
            <button class="btn-small danger" @click="deleteRow(row)">Xóa</button>
          </td>
        </tr>
      </tbody>
    </table>

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
}

.create-box {
  margin-bottom: 18px;
  padding: 16px;
}

.filter-bar {
  margin-bottom: 18px;
  padding: 12px;
  display: grid;
  grid-template-columns: 1.5fr 1fr 160px 1fr;
  gap: 10px;
  align-items: center;
}

.result-meta {
  color: var(--text-secondary);
  text-align: right;
  font-size: 0.9rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 12px 0;
}

.field {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: transparent;
  color: var(--text-primary);
  padding: 8px 10px;
}

.toggle {
  display: flex;
  align-items: center;
  gap: 8px;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
  text-align: left;
}

.actions {
  display: flex;
  gap: 8px;
}

.btn,
.btn-small {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  border-radius: 8px;
  padding: 7px 10px;
  cursor: pointer;
}

.btn-small.danger {
  border-color: rgba(239, 68, 68, 0.5);
  color: #ef4444;
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
  .filter-bar {
    grid-template-columns: 1fr;
  }

  .result-meta {
    text-align: left;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
