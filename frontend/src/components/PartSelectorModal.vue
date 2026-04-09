<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { X, Search, CheckCircle2 } from 'lucide-vue-next'

const props = defineProps({
  category: String,
  isOpen: Boolean
})

const emit = defineEmits(['close', 'select'])

const products = ref([])
const loading = ref(false)
const searchQuery = ref('')

const fetchProducts = async () => {
  loading.ref = true
  try {
    const response = await axios.get(`/api/v1/products?category=${props.category}`)
    products.value = response.data.data || []
  } catch (err) {
    console.error('Error fetching products for selector:', err)
  } finally {
    loading.value = false
  }
}

const filteredProducts = computed(() => {
  if (!searchQuery.value) return products.value
  return products.value.filter(p => 
    p.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

const formatPrice = (cents) => {
    return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

onMounted(fetchProducts)
</script>

<template>
  <div v-if="isOpen" class="modal-overlay" @click.self="emit('close')">
    <div class="modal-content glass-panel animate-fade-in">
      <header class="modal-header">
        <div>
          <h3>Chọn {{ category }}</h3>
          <p style="font-size: 0.85rem; color: var(--text-secondary);">Duyệt danh sách các linh kiện phù hợp</p>
        </div>
        <button class="close-btn" @click="emit('close')"><X :size="20" /></button>
      </header>

      <div class="search-bar">
        <Search :size="18" class="search-icon" />
        <input type="text" v-model="searchQuery" placeholder="Tìm kiếm tên linh kiện..." />
      </div>

      <div class="product-list">
        <div v-if="loading" class="loading-state">Đang tải data...</div>
        <div v-else-if="filteredProducts.length === 0" class="empty-state">Không tìm thấy linh kiện nào.</div>
        
        <div 
          v-for="product in filteredProducts" 
          :key="product.id" 
          class="product-item"
          @click="emit('select', product)"
        >
          <img :src="product.image_url || 'https://via.placeholder.com/60'" :alt="product.name" class="p-img" />
          <div class="p-info">
            <div class="p-name">{{ product.name }}</div>
            <div class="p-price">{{ formatPrice(product.base_price_cents) }}</div>
          </div>
          <CheckCircle2 :size="20" class="select-indicator" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  width: 90%;
  max-width: 600px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  padding: 0;
  overflow: hidden;
}

.modal-header {
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid var(--border-color);
}

.close-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
}

.search-bar {
  padding: 16px 24px;
  position: relative;
  border-bottom: 1px solid var(--border-color);
}

.search-icon {
  position: absolute;
  left: 40px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
}

.search-bar input {
  width: 100%;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  padding: 12px 12px 12px 48px;
  border-radius: 10px;
  color: var(--text-primary);
  outline: none;
}

.product-list {
  flex-grow: 1;
  overflow-y: auto;
  padding: 8px;
}

.product-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.product-item:hover {
  background: rgba(255, 255, 255, 0.05);
}

.p-img {
  width: 60px;
  height: 60px;
  object-fit: contain;
  background: #1a1a20;
  border-radius: 8px;
}

.p-info {
  flex-grow: 1;
}

.p-name {
  font-weight: 600;
  margin-bottom: 4px;
}

.p-price {
  font-size: 0.9rem;
  color: var(--accent-primary);
  font-weight: 700;
}

.select-indicator {
  opacity: 0;
  color: var(--accent-primary);
  transition: opacity 0.2s ease;
}

.product-item:hover .select-indicator {
  opacity: 0.5;
}

.loading-state, .empty-state {
  padding: 40px;
  text-align: center;
  color: var(--text-secondary);
}
</style>
