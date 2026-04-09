<script setup>
import { useProductStore } from '@/stores/productStore'
import { Monitor, Cpu, HardDrive, Zap, Box } from 'lucide-vue-next'

const store = useProductStore()
const categories = [
  { id: null, name: 'Tất cả linh kiện', icon: Box },
  { id: 'CPU', name: 'Vi xử lý (CPU)', icon: Cpu },
  { id: 'GPU', name: 'Card đồ họa (GPU)', icon: Zap },
  { id: 'Motherboard', name: 'Bo mạch chủ', icon: Monitor },
  { id: 'RAM', name: 'Bộ nhớ (RAM)', icon: HardDrive },
  { id: 'PSU', name: 'Nguồn máy tính (PSU)', icon: Zap },
  { id: 'Case', name: 'Vỏ máy (Case)', icon: Box },
  { id: 'Storage', name: 'Ổ cứng (SSD/HDD)', icon: HardDrive },
  { id: 'Monitor', name: 'Màn hình', icon: Monitor },
]
</script>

<template>
  <aside class="sidebar">
    <h4 class="sidebar-title">Lọc linh kiện</h4>
    <div class="category-list">
      <button 
        v-for="cat in categories" 
        :key="cat.name"
        class="category-item"
        :class="{ active: store.currentCategory === cat.id }"
        @click="store.setCategory(cat.id)"
      >
        <component :is="cat.icon" :size="20" />
        {{ cat.name }}
      </button>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 280px;
  flex-shrink: 0;
}

.sidebar-title {
  color: var(--text-secondary);
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 24px;
}

.category-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  background: none;
  border: 1px solid transparent;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.95rem;
  font-weight: 500;
  text-align: left;
}

.category-item:hover {
  background: #ffffff;
  color: var(--accent-primary);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.category-item.active {
  background: rgba(37, 99, 235, 0.08);
  border-color: rgba(37, 99, 235, 0.2);
  color: var(--accent-primary);
}
</style>
