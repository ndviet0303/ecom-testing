<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useCartStore } from '@/stores/cartStore'
import { 
  ShieldCheck, 
  ShoppingCart, 
  ArrowLeft, 
  Truck, 
  CheckCircle, 
  Info,
  ChevronRight,
  RefreshCcw
} from 'lucide-vue-next'

const route = useRoute()
const cartStore = useCartStore()
const product = ref(null)
const loading = ref(true)
const quantity = ref(1)
const isAdding = ref(false)

const fetchProduct = async () => {
  loading.value = true
  try {
    const response = await axios.get(`/api/v1/products/${route.params.id}`)
    product.value = response.data
  } catch (err) {
    console.error('Lỗi khi tải chi tiết sản phẩm:', err)
  } finally {
    loading.value = false
  }
}

const formatPrice = (cents) => {
  if (!cents) return '0 đ'
  const vnd = (cents / 100) * 25000
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0
  }).format(vnd)
}

const addToCart = async () => {
  isAdding.value = true
  await cartStore.addToCart(product.value.id, quantity.value)
  isAdding.value = false
}

onMounted(fetchProduct)

const specGroups = computed(() => {
  if (!product.value?.specs) return []
  return Object.entries(product.value.specs).map(([key, value]) => ({
    label: key.replace(/_/g, ' ').toUpperCase(),
    value: Array.isArray(value) ? value.join(' / ') : value
  }))
})
</script>

<template>
  <div class="product-detail-view container animate-fade-in">
    <div v-if="loading" class="detail-skeleton">
      <div class="skeleton-image"></div>
      <div class="skeleton-info">
        <div class="skeleton-line title"></div>
        <div class="skeleton-line price"></div>
        <div class="skeleton-line desc"></div>
        <div class="skeleton-line btn"></div>
      </div>
    </div>

    <div v-else-if="product" class="product-content">
      <nav class="breadcrumb">
        <router-link to="/products" class="back-link">
          <ArrowLeft :size="18" /> Quay lại danh sách
        </router-link>
        <div class="crumb-separator"><ChevronRight :size="14" /></div>
        <span class="current-crumb">{{ product.category }}</span>
      </nav>

      <div class="main-grid">
        <!-- Left: Image Gallery -->
        <div class="product-visuals">
          <div class="main-image-wrapper glass-panel">
            <img :src="product.image_url" :alt="product.name" />
          </div>
          <div class="visual-badges">
            <div class="visual-badge">
              <ShieldCheck :size="20" />
              <span>Bảo hành {{ product.warranty_months }} tháng chính hãng</span>
            </div>
          </div>
        </div>

        <!-- Right: Product Info -->
        <div class="product-info-panel">
          <div class="brand-badge">{{ product.brand }}</div>
          <h1 class="detail-title">{{ product.name }}</h1>
          
          <div class="status-row">
            <div class="stock-info" :class="{ 'out-of-stock': product.inventory?.on_hand <= 0 }">
              <CheckCircle v-if="product.inventory?.on_hand > 0" :size="16" />
              <Info v-else :size="16" />
              {{ product.inventory?.on_hand > 0 ? 'Còn hàng' : 'Hết hàng' }}
            </div>
            <div class="sku-info">SKU: {{ product.sku }}</div>
          </div>

          <div class="price-section glass-panel">
            <div class="price-tag">
              <span class="label">Giá niêm yết:</span>
              <span class="value">{{ formatPrice(product.base_price_cents) }}</span>
            </div>
            <div class="shipping-info">
              <Truck :size="18" />
              <span>Miễn phí vận chuyển toàn quốc cho đơn hàng từ 10.000.000đ</span>
            </div>
          </div>

          <div class="purchase-actions" v-if="product.inventory?.on_hand > 0">
            <div class="quantity-selector glass-panel">
              <button @click="quantity > 1 && quantity--" :disabled="quantity <= 1">-</button>
              <input type="number" v-model="quantity" />
              <button @click="quantity++">+</button>
            </div>
            <button class="add-cart-btn btn-primary" @click="addToCart" :disabled="isAdding">
              <ShoppingCart v-if="!isAdding" :size="20" />
              <RefreshCcw v-else class="animate-spin" :size="20" />
              {{ isAdding ? 'Đang thêm...' : 'Thêm vào giỏ hàng' }}
            </button>
          </div>

          <div class="tech-specs-preview">
            <h3>Thông số kỹ thuật nổi bật</h3>
            <div class="spec-grid">
              <div v-for="spec in specGroups.slice(0, 6)" :key="spec.label" class="spec-card glass-panel">
                <span class="spec-label">{{ spec.label }}</span>
                <span class="spec-value">{{ spec.value }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="full-description glass-panel">
        <div class="tabs">
          <button class="tab-btn active">Mô tả sản phẩm</button>
          <button class="tab-btn">Đánh giá (0)</button>
        </div>
        <div class="tab-content">
          <p>{{ product.description || 'Đang cập nhật nội dung cho sản phẩm này...' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.product-detail-view {
  padding-top: 40px;
  padding-bottom: 100px;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
  color: var(--text-secondary);
}

.back-link {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-primary);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s;
}

.back-link:hover {
  color: var(--accent-primary);
}

.main-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  margin-bottom: 80px;
}

/* Visuals */
.main-image-wrapper {
  height: 500px;
  padding: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(30,30,35,0.6) 0%, rgba(20,20,25,0.8) 100%);
}

.main-image-wrapper img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.visual-badges {
  margin-top: 24px;
}

.visual-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: rgba(var(--accent-primary-rgb), 0.1);
  border: 1px solid rgba(var(--accent-primary-rgb), 0.2);
  border-radius: 12px;
  color: var(--accent-primary);
  font-weight: 600;
}

/* Info Panel */
.brand-badge {
  display: inline-block;
  padding: 4px 12px;
  background: rgba(255,255,255,0.05);
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--accent-primary);
  margin-bottom: 16px;
}

.detail-title {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 24px;
}

.status-row {
  display: flex;
  gap: 24px;
  margin-bottom: 32px;
  font-size: 0.9rem;
}

.stock-info {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #4ade80;
}

.stock-info.out-of-stock {
  color: #f87171;
}

.price-section {
  padding: 32px;
  margin-bottom: 32px;
  background: rgba(255,255,255,0.02);
}

.price-tag {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 20px;
}

.price-tag .label {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.price-tag .value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--accent-primary);
}

.shipping-info {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.purchase-actions {
  display: flex;
  gap: 16px;
  margin-bottom: 48px;
}

.quantity-selector {
  display: flex;
  align-items: center;
  background: rgba(255,255,255,0.05);
  border-radius: 12px;
  overflow: hidden;
}

.quantity-selector button {
  width: 50px;
  height: 56px;
  background: none;
  border: none;
  color: white;
  font-size: 1.2rem;
  cursor: pointer;
}

.quantity-selector input {
  width: 60px;
  background: none;
  border: none;
  text-align: center;
  color: white;
  font-weight: 700;
  font-size: 1.1rem;
}

.add-cart-btn {
  flex-grow: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  font-weight: 700;
  font-size: 1.1rem;
}

.tech-specs-preview h3 {
  font-size: 1.1rem;
  margin-bottom: 20px;
  color: var(--text-primary);
}

.spec-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.spec-card {
  padding: 16px;
  display: flex;
  flex-direction: column;
  background: rgba(255,255,255,0.02);
}

.spec-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.spec-value {
  font-weight: 600;
  color: var(--text-primary);
}

.full-description {
  padding: 40px;
}

.tabs {
  display: flex;
  gap: 40px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  margin-bottom: 32px;
}

.tab-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  padding-bottom: 16px;
  font-weight: 600;
  cursor: pointer;
  position: relative;
}

.tab-btn.active {
  color: var(--accent-primary);
}

.tab-btn.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  width: 100%;
  height: 2px;
  background: var(--accent-primary);
}

.tab-content {
  line-height: 1.8;
  color: var(--text-secondary);
}

/* Skeletons */
.detail-skeleton {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
}

.skeleton-image {
  height: 500px;
  background: rgba(255,255,255,0.05);
  border-radius: 20px;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 968px) {
  .main-grid {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}
</style>
