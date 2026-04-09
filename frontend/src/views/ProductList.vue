<script setup>
import { onMounted, ref, watch, onUnmounted } from 'vue'
import { useProductStore } from '@/stores/productStore'
import CategorySidebar from '@/components/CategorySidebar.vue'
import ProductCard from '@/components/ProductCard.vue'
import ProductSkeleton from '@/components/ProductSkeleton.vue'
import { RefreshCcw, Search, SlidersHorizontal, ChevronLeft, ChevronRight, X } from 'lucide-vue-next'

const store = useProductStore()
const searchQuery = ref('')
const isFilterOpen = ref(false)
const sentinel = ref(null)
let debounceTimer = null
let observer = null

watch(searchQuery, (newVal) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    store.setSearch(newVal)
  }, 500)
})

onMounted(() => {
  store.fetchProducts()
  
  // Infinite Scroll Observer
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && !store.loadingMore && store.pagination.currentPage < store.pagination.lastPage) {
      store.loadMore()
    }
  }, { threshold: 0.1 })
  
  if (sentinel.value) observer.observe(sentinel.value)
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<template>
  <div class="product-explorer container">
    <header class="explorer-header animate-fade-in">
      <div class="header-main">
        <h1 class="page-title">Hardware <span class="gradient-text">Showcase</span></h1>
        <p class="page-description">Lựa chọn những linh kiện tối tân nhất cho dàn PC gaming đỉnh cao của bạn.</p>
      </div>
      
      <div class="header-actions">
        <div class="search-bar glass-panel">
          <Search :size="18" />
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Tìm kiếm linh kiện (ví dụ: RTX 4070, Ryzen 9)..." 
          />
        </div>
        <button @click="isFilterOpen = true" class="filter-toggle glass-panel">
          <SlidersHorizontal :size="18" />
          <span>Lọc nâng cao</span>
        </button>
      </div>
    </header>

    <div class="explorer-content">
      <CategorySidebar />

      <div class="product-main">
        <div v-if="!store.loading && store.products.length > 0" class="results-info">
          Tìm thấy <strong>{{ store.pagination.total }}</strong> linh kiện 
          <span v-if="searchQuery">cho "{{ searchQuery }}"</span>
        </div>

        <div v-if="store.loading" class="product-grid">
          <ProductSkeleton v-for="i in 12" :key="i" />
        </div>

        <div v-else-if="store.error" class="state-container error animate-fade-in">
          <div class="error-box glass-panel">
            <p>{{ store.error }}</p>
            <button @click="store.fetchProducts(store.currentCategory)" class="btn btn-primary">Thử lại</button>
          </div>
        </div>

        <div v-else-if="store.products.length === 0" class="state-container animate-fade-in">
          <div class="empty-state">
            <Search :size="48" style="opacity: 0.2; margin-bottom: 20px;" />
            <p>Không tìm thấy sản phẩm nào phù hợp với lựa chọn của bạn.</p>
            <button @click="searchQuery = ''; store.fetchProducts(null)" class="btn btn-secondary" style="margin-top: 20px;">Xóa tất cả bộ lọc</button>
          </div>
        </div>

        <div v-else>
          <div class="product-grid">
            <ProductCard 
              v-for="product in store.products" 
              :key="product.id" 
              :product="product" 
            />
          </div>

          <!-- Sentinel for infinite scroll -->
          <div ref="sentinel" class="scroll-sentinel"></div>

          <div class="navigation-footer">
            <div v-if="store.pagination.currentPage < store.pagination.lastPage" class="load-more-container">
              <button 
                @click="store.loadMore()" 
                class="load-more-btn glass-panel"
                :disabled="store.loadingMore"
              >
                <RefreshCcw v-if="store.loadingMore" class="animate-spin" :size="20" />
                <span v-else>Tải thêm linh kiện</span>
              </button>
            </div>

            <!-- Numeric Pagination -->
            <div class="pagination-bar glass-panel" v-if="store.pagination.lastPage > 1">
              <button 
                class="page-btn" 
                :disabled="store.pagination.currentPage === 1"
                @click="store.fetchProducts(store.currentCategory, store.searchQuery, store.pagination.currentPage - 1)"
              >
                <ChevronLeft :size="18" />
              </button>
              
              <div class="page-numbers">
                <button 
                  v-for="page in store.pagination.lastPage" 
                  :key="page"
                  class="page-num"
                  :class="{ active: page === store.pagination.currentPage }"
                  @click="store.fetchProducts(store.currentCategory, store.searchQuery, page)"
                  v-show="Math.abs(page - store.pagination.currentPage) < 3 || page === 1 || page === store.pagination.lastPage"
                >
                  {{ page }}
                </button>
              </div>

              <button 
                class="page-btn" 
                :disabled="store.pagination.currentPage === store.pagination.lastPage"
                 @click="store.fetchProducts(store.currentCategory, store.searchQuery, store.pagination.currentPage + 1)"
              >
                <ChevronRight :size="18" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Drawer -->
    <Teleport to="body">
      <Transition name="drawer">
        <div v-if="isFilterOpen" class="drawer-overlay" @click.self="isFilterOpen = false">
          <div class="filter-drawer glass-panel">
            <div class="drawer-header">
              <h3>Bộ lọc nâng cao</h3>
              <button @click="isFilterOpen = false" class="close-btn"><X :size="24" /></button>
            </div>
            
            <div class="drawer-content">
              <div class="filter-group">
                <label>Khoảng giá (VNĐ)</label>
                <div class="price-inputs">
                  <input type="number" placeholder="Từ" />
                  <input type="number" placeholder="Đến" />
                </div>
              </div>

              <div class="filter-group">
                <label>Thương hiệu</label>
                <div class="brand-grid">
                  <button class="brand-chip">Intel</button>
                  <button class="brand-chip">AMD</button>
                  <button class="brand-chip">NVIDIA</button>
                  <button class="brand-chip">ASUS</button>
                </div>
              </div>
            </div>

            <div class="drawer-footer">
              <button @click="isFilterOpen = false" class="btn btn-secondary">Đặt lại</button>
              <button @click="isFilterOpen = false" class="btn btn-primary">Áp dụng bộ lọc</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.product-explorer {
  padding-top: 60px;
  padding-bottom: 100px;
}

.explorer-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 60px;
  gap: 40px;
  flex-wrap: wrap;
}

.header-main {
  flex: 1;
  min-width: 300px;
}

.page-title {
  font-size: 3rem;
  font-weight: 800;
  margin-bottom: 16px;
  letter-spacing: -1px;
}

.page-description {
  color: var(--text-secondary);
  font-size: 1.1rem;
  max-width: 600px;
}

.header-actions {
  display: flex;
  gap: 16px;
}

.search-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 20px;
  height: 54px;
  width: 320px;
  background: #ffffff;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.search-bar input {
  background: none;
  border: none;
  color: var(--text-primary);
  width: 100%;
  font-size: 0.95rem;
}

.search-bar input:focus {
  outline: none;
}

.filter-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 24px;
  height: 54px;
  background: #ffffff;
  border: 1px solid var(--border-color);
  color: var(--text-primary);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.filter-toggle:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: var(--accent-primary);
}

.explorer-content {
  display: flex;
  gap: 60px;
}

.product-main {
  flex-grow: 1;
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 32px;
}

@media (min-width: 1400px) {
  .product-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.results-info {
  margin-bottom: 24px;
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-weight: 500;
}

.results-info strong {
  color: var(--accent-primary);
}

.navigation-footer {
  margin-top: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 32px;
}

.load-more-container {
  width: 100%;
  display: flex;
  justify-content: center;
}

.load-more-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 40px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
  font-weight: 700;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.load-more-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.08);
  border-color: var(--accent-primary);
  transform: translateY(-2px);
}

.pagination-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  background: #ffffff;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  border-radius: 12px;
}

.page-numbers {
  display: flex;
  gap: 4px;
}

.page-btn, .page-num {
  width: 40px;
  height: 40px;
  background: none;
  border: 1px solid transparent;
  color: var(--text-secondary);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 600;
}

.page-btn:hover:not(:disabled), .page-num:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text-primary);
}

.page-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.page-num.active {
  background: var(--accent-primary);
  color: white;
  border-color: var(--accent-primary);
}

.empty-state {
  text-align: center;
  padding: 80px 40px;
  color: var(--text-secondary);
}

.scroll-sentinel {
  height: 20px;
  width: 100%;
}

/* Filter Drawer */
.drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(8px);
  z-index: 1000;
  display: flex;
  justify-content: flex-end;
}

.filter-drawer {
  width: 400px;
  height: 100%;
  background: rgba(23, 23, 28, 0.95);
  border-left: 1px solid rgba(255, 255, 255, 0.05);
  display: flex;
  flex-direction: column;
}

.drawer-header {
  padding: 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.drawer-content {
  padding: 32px;
  flex-grow: 1;
  overflow-y: auto;
}

.filter-group {
  margin-bottom: 32px;
}

.filter-group label {
  display: block;
  margin-bottom: 16px;
  font-weight: 600;
  color: var(--text-secondary);
}

.price-inputs {
  display: flex;
  gap: 12px;
}

.price-inputs input {
  width: 100%;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
  border-radius: 8px;
}

.brand-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.brand-chip {
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
  border-radius: 8px;
  cursor: pointer;
}

.brand-chip:hover {
  border-color: var(--accent-primary);
}

.drawer-footer {
  padding: 32px;
  display: flex;
  gap: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.drawer-footer .btn {
  flex: 1;
  padding: 14px;
}

.close-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
}

/* Transitions */
.drawer-enter-active, .drawer-leave-active {
  transition: all 0.4s ease;
}

.drawer-enter-from, .drawer-leave-to {
  opacity: 0;
}

.drawer-enter-from .filter-drawer, .drawer-leave-to .filter-drawer {
  transform: translateX(100%);
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.state-container {
  min-height: 400px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
}

.error-box {
  padding: 40px;
  text-align: center;
  border: 1px solid rgba(248, 113, 113, 0.2);
}

.error-box p {
  color: #f87171;
  margin-bottom: 24px;
}

@media (max-width: 1200px) {
  .explorer-content {
    flex-direction: column;
  }
}

@media (max-width: 768px) {
  .page-title {
    font-size: 2.25rem;
  }
  
  .explorer-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .search-bar {
    width: 100%;
  }
}
</style>
