<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { X, Search, CheckCircle2 } from 'lucide-vue-next'
import { useBuilderStore } from '@/stores/builderStore'

const props = defineProps({
  category: String,
  isOpen: Boolean
})

const emit = defineEmits(['close', 'choose'])
const store = useBuilderStore()

const products = ref([])
const loading = ref(false)
const searchQuery = ref('')
const onlyCompatible = ref(false)

const normalizeValue = (value) => String(value || '').trim().toLowerCase()

const isEqualSpec = (left, right) => {
  if (!left || !right) return true
  return normalizeValue(left) === normalizeValue(right)
}

const getEstimatedWattage = (parts) => {
  const cpuTdp = Number(parts.CPU?.specs?.tdp || 0)
  const gpuTdp = Number(parts.GPU?.specs?.tbp || parts.GPU?.specs?.tdp || 0)

  return 50 + cpuTdp + gpuTdp
}

const isCompatibleWithCurrentBuild = (product) => {
  const specs = product?.specs || {}
  const category = normalizeValue(props.category)
  const slots = store.slots

  if (category.includes('cpu')) {
    return isEqualSpec(specs.socket, slots.Motherboard?.specs?.socket)
  }

  if (category.includes('motherboard') || category.includes('mainboard')) {
    return isEqualSpec(specs.socket, slots.CPU?.specs?.socket)
      && isEqualSpec(specs.ram_type, slots.RAM?.specs?.ram_type)
  }

  if (category.includes('ram')) {
    return isEqualSpec(specs.ram_type, slots.Motherboard?.specs?.ram_type)
  }

  if (category.includes('storage') || category.includes('ssd') || category.includes('hdd')) {
    return true
  }

  if (category.includes('gpu') || category.includes('vga')) {
    const caseClearance = Number(slots.Case?.specs?.gpu_clearance_mm || 0)
    const gpuLength = Number(specs.length_mm || 0)
    const estimatedWattage = getEstimatedWattage({
      ...slots,
      GPU: product
    })
    const psuWattage = Number(slots.PSU?.specs?.wattage || 0)
    const hasCaseMatch = !caseClearance || !gpuLength || gpuLength <= caseClearance
    const hasPsuMatch = !psuWattage || psuWattage >= Math.ceil(estimatedWattage * 1.2)

    return hasCaseMatch && hasPsuMatch
  }

  if (category.includes('psu') || category.includes('power supply')) {
    const estimatedWattage = getEstimatedWattage(slots)
    const wattage = Number(specs.wattage || 0)
    return !estimatedWattage || !wattage || wattage >= Math.ceil(estimatedWattage * 1.2)
  }

  if (category.includes('case') || category.includes('chassis')) {
    const caseClearance = Number(specs.gpu_clearance_mm || 0)
    const gpuLength = Number(slots.GPU?.specs?.length_mm || 0)
    return !caseClearance || !gpuLength || gpuLength <= caseClearance
  }

  return true
}

const fetchProducts = async () => {
  if (!props.category || !props.isOpen) return

  loading.value = true
  try {
    const response = await axios.get('/api/v1/products', {
      params: {
        category: props.category
      }
    })
    products.value = response.data.data || []
  } catch (err) {
    console.error('Error fetching products for selector:', err)
  } finally {
    loading.value = false
  }
}

const filteredProducts = computed(() => {
  return products.value.filter((product) => {
    const matchesSearch = !searchQuery.value
      || product.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesCompatibility = !onlyCompatible.value || isCompatibleWithCurrentBuild(product)

    return matchesSearch && matchesCompatibility
  })
})

const formatPrice = (cents) => {
    return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

watch(
  () => [props.isOpen, props.category],
  ([isOpen]) => {
    if (!isOpen) {
      searchQuery.value = ''
      onlyCompatible.value = false
      return
    }

    fetchProducts()
  },
  { immediate: true }
)
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

      <label class="compatibility-toggle">
        <input v-model="onlyCompatible" type="checkbox" />
        <span>Chỉ hiển thị đồ match với cấu hình hiện tại</span>
      </label>

      <div class="product-list">
        <div v-if="loading" class="loading-state">Đang tải data...</div>
        <div v-else-if="filteredProducts.length === 0" class="empty-state">
          {{ onlyCompatible ? 'Không có linh kiện nào tương thích với cấu hình hiện tại.' : 'Không tìm thấy linh kiện nào.' }}
        </div>
        
        <div 
          v-for="product in filteredProducts" 
          :key="product.id" 
          class="product-item"
          @click="emit('choose', { slotKey: props.category, product })"
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

.compatibility-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 24px;
  border-bottom: 1px solid var(--border-color);
  color: var(--text-primary);
  font-size: 0.92rem;
  font-weight: 600;
}

.compatibility-toggle input {
  width: 16px;
  height: 16px;
  accent-color: var(--accent-primary);
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
