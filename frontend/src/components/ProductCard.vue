<script setup>
import { ref, computed } from 'vue'
import { 
  ShieldCheck, 
  ArrowRight, 
  ShoppingCart, 
  Cpu, 
  HardDrive, 
  Zap, 
  Monitor, 
  Box,
  RefreshCcw,
  CheckCircle2
} from 'lucide-vue-next'
import { useCartStore } from '@/stores/cartStore'
import { useToastStore } from '@/stores/toastStore'
import { useRouter } from 'vue-router'

const props = defineProps({
  product: {
    type: Object,
    required: true
  }
})

const cartStore = useCartStore()
const toastStore = useToastStore()
const router = useRouter()
const isAdding = ref(false)
const addedToCart = ref(false)

const formatPrice = (cents) => {
  // Giả định backend là USD cents, đổi sang VND (x 250)
  const vnd = (cents / 100) * 25000
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0
  }).format(vnd)
}

const handleImageError = (e) => {
  e.target.src = 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=400&auto=format&fit=crop'
}

const addToCart = async () => {
  if (isAdding.value) return
  isAdding.value = true
  try {
    await cartStore.addToCart(props.product.id)
    addedToCart.value = true
    toastStore.success(`Đã thêm ${props.product.name} vào giỏ hàng!`)
    setTimeout(() => {
      addedToCart.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to add to cart:', err)
  } finally {
    isAdding.value = false
  }
}

const getSpecLabels = computed(() => {
  const specs = props.product.specs || {}
  const items = []
  
  switch (props.product.category) {
    case 'CPU':
      if (specs.cores) items.push({ label: 'Nhân', value: specs.cores, icon: Cpu })
      if (specs.boost) items.push({ label: 'Boost', value: `${specs.boost}GHz`, icon: Zap })
      if (specs.socket) items.push({ label: 'Socket', value: specs.socket, icon: Box })
      if (specs.tdp) items.push({ label: 'TDP', value: `${specs.tdp}W`, icon: Zap })
      break
    case 'GPU':
      if (specs.vram) items.push({ label: 'VRAM', value: specs.vram, icon: Monitor })
      if (specs.chipset) items.push({ label: 'Chipset', value: specs.chipset.split(' ')[0], icon: Cpu })
      if (specs.length_mm) items.push({ label: 'Dài', value: `${specs.length_mm}mm`, icon: Box })
      break
    case 'Motherboard':
      if (specs.socket) items.push({ label: 'Socket', value: specs.socket, icon: Box })
      if (specs.ram_type) items.push({ label: 'RAM', value: specs.ram_type, icon: Box })
      break
    case 'RAM':
      if (specs.speed?.[1]) items.push({ label: 'Bus', value: `${specs.speed[1]}MHz`, icon: Zap })
      if (specs.modules) items.push({ label: 'Dung lượng', value: specs.modules, icon: Box })
      if (specs.ram_type) items.push({ label: 'Loại', value: specs.ram_type, icon: Box })
      break
    case 'Storage':
      if (specs.capacity) items.push({ label: 'Dung lượng', value: `${specs.capacity}GB`, icon: HardDrive })
      if (specs.type) items.push({ label: 'Loại', value: specs.type, icon: Box })
      break
    case 'Monitor':
      if (specs.screen_size) items.push({ label: 'Kích thước', value: `${specs.screen_size}"`, icon: Monitor })
      if (specs.refresh_rate) items.push({ label: 'Tần số', value: `${specs.refresh_rate}Hz`, icon: Zap })
      break
    default:
      // Fallback for general specs if available
      if (specs.socket) items.push({ label: 'Socket', value: specs.socket, icon: Box })
  }
  return items.slice(0, 4) // Show max 4 specs for layout consistency
})

const inStock = computed(() => (props.product.inventory?.on_hand || 0) > 0)
</script>

<template>
  <div class="product-card glass-panel animate-fade-in" :class="{ 'out-of-stock': !inStock }">
    <router-link :to="{ name: 'product-detail', params: { id: product.id } }" class="product-link">
      <div class="product-image">
        <img 
          :src="product.image_url" 
          :alt="product.name" 
          @error="handleImageError"
          loading="lazy"
        />
        <div class="badges-container">
          <div v-if="product.warranty_months" class="badge warranty">
            <ShieldCheck :size="12" />
            <span>{{ product.warranty_months }}T BH</span>
          </div>
          <div v-if="!inStock" class="badge out-stock">
            Hết hàng
          </div>
        </div>
      </div>
    </router-link>

    <div class="product-info">
      <div class="meta-row">
        <span class="category-tag">{{ product.category }}</span>
        <div v-if="inStock" class="stock-indicator">
          <span class="dot"></span>
          <span>Sẵn hàng</span>
        </div>
      </div>
      
      <router-link :to="{ name: 'product-detail', params: { id: product.id } }" class="product-name-link">
        <h3 class="product-name" :title="product.name">{{ product.name }}</h3>
      </router-link>

      <div class="product-specs-grid">
        <div v-for="spec in getSpecLabels" :key="spec.label" class="spec-item">
          <component :is="spec.icon" :size="14" class="spec-icon" />
          <div class="spec-content">
            <span class="spec-label">{{ spec.label }}</span>
            <span class="spec-value">{{ spec.value }}</span>
          </div>
        </div>
      </div>
      
      <div class="product-footer">
        <div class="price-section">
          <span class="price-label">Giá niêm yết</span>
          <span class="product-price">{{ formatPrice(product.base_price_cents) }}</span>
        </div>
        
        <button 
          class="action-btn" 
          @click.stop="addToCart" 
          :disabled="!inStock || isAdding"
          :class="{ 'is-loading': isAdding, 'is-added': addedToCart }"
        >
          <template v-if="isAdding">
            <RefreshCcw class="animate-spin" :size="20" />
          </template>
          <template v-else-if="addedToCart">
            <CheckCircle2 :size="20" />
          </template>
          <template v-else>
            <ShoppingCart :size="20" />
          </template>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.product-card {
  height: 100%;
  display: flex;
  flex-direction: column;
  transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
  background: #ffffff;
  border: 1px solid var(--border-color);
  position: relative;
  overflow: hidden;
  cursor: pointer;
}

.product-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.05), transparent 60%);
  opacity: 0;
  transition: opacity 0.5s ease;
}

.product-card:hover {
  transform: translateY(-8px);
  border-color: var(--accent-primary);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
}

.product-card:hover::before {
  opacity: 1;
}

.product-link {
  text-decoration: none;
  display: block;
}

.product-image {
  height: 200px;
  position: relative;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  border-bottom: 1px solid var(--border-color);
}

.product-image img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
  filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.1));
}

.product-card:hover .product-image img {
  transform: scale(1.1) rotate(2deg);
}

.badges-container {
  position: absolute;
  top: 12px;
  left: 12px;
  right: 12px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  pointer-events: none;
}

.badge {
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 0.7rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
  backdrop-filter: blur(8px);
}

.badge.warranty {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #10b981;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

.badge.out-stock {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #ef4444;
  margin-left: auto;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
}

.product-info {
  padding: 20px;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 1;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.category-tag {
  font-size: 0.65rem;
  font-weight: 800;
  color: var(--accent-primary);
  text-transform: uppercase;
  letter-spacing: 1.5px;
  background: rgba(99, 102, 241, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.stock-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.7rem;
  color: #4ade80;
  font-weight: 500;
}

.stock-indicator .dot {
  width: 6px;
  height: 6px;
  background: #10b981;
  border-radius: 50%;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.2); }
  100% { opacity: 1; transform: scale(1); }
}

.product-name-link {
  text-decoration: none;
  color: inherit;
}

.product-name {
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.4;
  height: 2.8em;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  margin-bottom: 16px;
  color: var(--text-primary);
  transition: color 0.3s ease;
}

.product-card:hover .product-name {
  color: var(--accent-primary);
}

.product-specs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 20px;
}

.spec-item {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--bg-secondary);
  padding: 8px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.spec-icon {
  color: var(--text-secondary);
  opacity: 0.7;
}

.spec-content {
  display: flex;
  flex-direction: column;
}

.spec-label {
  font-size: 0.6rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.spec-value {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.product-footer {
  margin-top: auto;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}

.price-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.price-label {
  font-size: 0.7rem;
  color: var(--text-secondary);
}

.product-price {
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -0.5px;
}

.action-btn {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid var(--border-color);
  color: var(--text-primary);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.action-btn:hover:not(:disabled) {
  background: var(--accent-primary);
  border-color: var(--accent-primary);
  color: #ffffff;
  transform: scale(1.1);
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.2);
}

.action-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.action-btn.is-added {
  background: #22c55e;
  border-color: #22c55e;
}

.action-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
  filter: grayscale(1);
}

.out-of-stock {
  opacity: 0.8;
}

.out-of-stock .product-image {
  filter: grayscale(0.5);
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fadeIn 0.5s ease forwards;
}
</style>
