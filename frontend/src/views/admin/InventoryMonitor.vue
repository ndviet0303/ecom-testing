<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { AlertCircle, PackageCheck } from 'lucide-vue-next'

const lowStockItems = ref([])
const loading = ref(true)

const fetchInventory = async () => {
    loading.value = true
    try {
        const response = await axios.get('/api/v1/admin/inventory/low-stock')
        lowStockItems.value = response.data.data || response.data
    } catch (err) {
        console.error('Lỗi tải kho hàng:', err)
    } finally {
        loading.value = false
    }
}

onMounted(fetchInventory)
</script>

<template>
  <div class="inventory-monitor">
    <div style="margin-bottom: 32px;">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Giám sát <span class="gradient-text">Kho hàng</span></h2>
      <p style="color: var(--text-secondary);">Danh sách linh kiện có số lượng tồn kho thấp hơn ngưỡng an toàn.</p>
    </div>

    <div v-if="loading" class="state-msg">Đang kiểm tra kho...</div>

    <div v-else-if="lowStockItems.length === 0" class="empty-inventory glass-panel">
        <PackageCheck :size="48" style="color: var(--success); margin-bottom: 24px;" />
        <h3>Tất cả linh kiện đều đầy đủ!</h3>
        <p style="color: var(--text-secondary);">Không có sản phẩm nào cần nhập thêm lúc này.</p>
    </div>

    <div v-else class="inventory-grid">
        <div v-for="product in lowStockItems" :key="product.id" class="inventory-card glass-panel">
            <div class="card-header">
                <span class="sku">{{ product.sku }}</span>
                <span class="stock-badge">Còn lại: {{ product.inventory?.quantity || 0 }}</span>
            </div>
            <h3 class="name">{{ product.name }}</h3>
            <div class="warning-box">
                <AlertCircle :size="16" /> Cần nhập hàng ngay
            </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
}

.inventory-card {
    padding: 24px;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.card-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
}

.sku {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 700;
}

.stock-badge {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    font-size: 0.8rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
}

.name {
    font-size: 1.1rem;
    margin-bottom: 20px;
    height: 3em;
    overflow: hidden;
}

.warning-box {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--error);
    font-size: 0.85rem;
    font-weight: 600;
}

.empty-inventory {
    text-align: center;
    padding: 80px;
}

.state-msg { text-align: center; padding: 80px; color: var(--text-secondary); }
</style>
