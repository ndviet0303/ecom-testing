<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const rows = ref([])
const loading = ref(true)
const saving = ref(false)
const editingId = ref(null)
const searchQuery = ref('')
const categoryFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const form = ref({
  sku: '',
  name: '',
  category: 'CPU',
  base_price_cents: 100000,
  sale_price_cents: null,
  brand: '',
  image_url: '',
  initial_on_hand: 0,
  low_stock_threshold: 0,
})

const categories = ['CPU', 'Mainboard', 'RAM', 'GPU', 'SSD', 'PSU', 'Case', 'Cooling', 'Other']

const fetchRows = async (targetPage = 1) => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/products', {
      params: {
        page: targetPage,
        per_page: perPage.value,
        q: searchQuery.value || undefined,
        category: categoryFilter.value || undefined,
      },
    })
    rows.value = response.data.data || []
    page.value = response.data.current_page || targetPage
    lastPage.value = response.data.last_page || 1
    total.value = response.data.total || rows.value.length
  } catch (err) {
    console.error('Lỗi tải sản phẩm:', err)
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  form.value = {
    sku: '',
    name: '',
    category: 'CPU',
    base_price_cents: 100000,
    sale_price_cents: null,
    brand: '',
    image_url: '',
    initial_on_hand: 0,
    low_stock_threshold: 0,
  }
}

const createProduct = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/admin/products', {
      ...form.value,
      sku: String(form.value.sku || '').trim(),
      name: String(form.value.name || '').trim(),
      brand: form.value.brand || null,
      image_url: form.value.image_url || null,
      sale_price_cents: form.value.sale_price_cents || null,
    })

    resetForm()
    await fetchRows(page.value)
  } catch (err) {
    alert('Không tạo được sản phẩm: ' + (err.response?.data?.message || err.message))
  } finally {
    saving.value = false
  }
}

const beginEdit = (row) => {
  editingId.value = row.id
}

const saveRow = async (row) => {
  try {
    await axios.put(`/api/v1/admin/products/${row.id}`, {
      name: row.name,
      category: row.category,
      brand: row.brand,
      base_price_cents: row.base_price_cents,
      sale_price_cents: row.sale_price_cents || null,
      image_url: row.image_url || null,
      low_stock_threshold: row.inventory?.low_stock_threshold ?? 0,
    })
    editingId.value = null
    await fetchRows(page.value)
  } catch (err) {
    alert('Không cập nhật được: ' + (err.response?.data?.message || err.message))
  }
}

const deleteRow = async (row) => {
  if (!confirm(`Xóa sản phẩm ${row.name}?`)) return

  try {
    await axios.delete(`/api/v1/admin/products/${row.id}`)
    await fetchRows(page.value)
  } catch (err) {
    alert('Không xóa được: ' + (err.response?.data?.message || err.message))
  }
}

watch([searchQuery, categoryFilter, perPage], () => {
  fetchRows(1)
})

onMounted(() => fetchRows(1))
</script>

<template>
  <div class="products-admin">
    <div class="head">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Quản lý <span class="gradient-text">Sản phẩm</span></h2>
      <button class="btn" @click="fetchRows(page)">Làm mới</button>
    </div>

    <section class="filter-bar glass-panel">
      <input v-model="searchQuery" class="field" placeholder="Tìm theo tên / SKU" />
      <select v-model="categoryFilter" class="field">
        <option value="">Tất cả danh mục</option>
        <option v-for="c in categories" :key="`f-${c}`" :value="c">{{ c }}</option>
      </select>
      <select v-model.number="perPage" class="field">
        <option :value="10">10 / trang</option>
        <option :value="15">15 / trang</option>
        <option :value="25">25 / trang</option>
      </select>
      <div class="result-meta">{{ total }} kết quả</div>
    </section>

    <section class="create-box glass-panel">
      <h3>Tạo sản phẩm mới</h3>
      <div class="form-grid">
        <input v-model="form.sku" class="field" placeholder="SKU" />
        <input v-model="form.name" class="field" placeholder="Tên sản phẩm" />
        <select v-model="form.category" class="field">
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model.number="form.base_price_cents" class="field" type="number" min="1" placeholder="Base price (cents)" />
        <input v-model.number="form.sale_price_cents" class="field" type="number" min="0" placeholder="Sale price (cents)" />
        <input v-model="form.brand" class="field" placeholder="Brand" />
        <input v-model="form.image_url" class="field" placeholder="Image URL" />
        <input v-model.number="form.initial_on_hand" class="field" type="number" min="0" placeholder="Initial on hand" />
        <input v-model.number="form.low_stock_threshold" class="field" type="number" min="0" placeholder="Low stock threshold" />
      </div>
      <button class="btn" :disabled="saving" @click="createProduct">Tạo sản phẩm</button>
    </section>

    <div v-if="loading" class="state-msg">Đang tải danh sách sản phẩm...</div>

    <div v-else-if="rows.length === 0" class="state-msg glass-panel">Không có sản phẩm nào phù hợp bộ lọc.</div>

    <table v-else class="table glass-panel">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Tên</th>
          <th>Giá</th>
          <th>Tồn kho</th>
          <th>Danh mục</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.sku }}</td>
          <td>
            <input v-if="editingId === row.id" v-model="row.name" class="field" />
            <span v-else>{{ row.name }}</span>
          </td>
          <td>
            <input v-if="editingId === row.id" v-model.number="row.base_price_cents" class="field" type="number" min="1" />
            <span v-else>{{ row.base_price_cents }}</span>
          </td>
          <td>{{ row.inventory?.on_hand ?? 0 }}</td>
          <td>
            <select v-if="editingId === row.id" v-model="row.category" class="field">
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <span v-else>{{ row.category }}</span>
          </td>
          <td class="actions">
            <button v-if="editingId !== row.id" class="btn-small" @click="beginEdit(row)">Sửa</button>
            <button v-else class="btn-small" @click="saveRow(row)">Lưu</button>
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
  width: 100%;
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
