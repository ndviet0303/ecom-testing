<script setup>
import { Plus, X, Monitor } from 'lucide-vue-next'

const props = defineProps({
  name: String,
  selectedProduct: Object,
})

const emit = defineEmits(['open-selector', 'remove'])

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}
</script>

<template>
  <div class="builder-slot glass-panel" :class="{ selected: !!selectedProduct }">
    <div class="slot-info">
      <div class="slot-icon">
        <Monitor :size="20" />
      </div>
      <div>
        <div class="slot-name">{{ name }}</div>
        <div v-if="!selectedProduct" class="slot-placeholder">Vui lòng chọn linh kiện</div>
        <div v-else class="selected-name">{{ selectedProduct.name }}</div>
      </div>
    </div>

    <div v-if="selectedProduct" class="selected-details">
      <span class="price-pill">{{ formatPrice(selectedProduct.base_price_cents) }}</span>
      <button class="remove-btn" @click="emit('remove')">
        <X :size="16" />
      </button>
    </div>

    <button v-else class="btn btn-primary btn-sm" @click="emit('open-selector')">
      <Plus :size="16" style="margin-right: 4px;" /> Chọn
    </button>
  </div>
</template>

<style scoped>
.builder-slot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  margin-bottom: 12px;
  transition: all 0.3s ease;
  border: 1px solid var(--border-color);
}

.builder-slot.selected {
  border-color: rgba(99, 102, 241, 0.3);
  background: rgba(99, 102, 241, 0.05);
}

.slot-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.slot-icon {
  background: var(--bg-secondary);
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
}

.slot-name {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  font-weight: 700;
}

.slot-placeholder {
  font-size: 0.95rem;
  opacity: 0.5;
}

.selected-name {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

.selected-details {
  display: flex;
  align-items: center;
  gap: 16px;
}

.price-pill {
  background: var(--bg-secondary);
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
}

.remove-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.remove-btn:hover {
  background: rgba(239, 68, 68, 0.1);
  color: var(--error);
}

.btn-sm {
  padding: 8px 16px;
  font-size: 0.85rem;
}
</style>
