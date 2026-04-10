<script setup>
import { ref } from 'vue'
import { useBuilderStore } from '@/stores/builderStore'
import BuilderSlot from '@/components/BuilderSlot.vue'
import BuildSummary from '@/components/BuildSummary.vue'
import PartSelectorModal from '@/components/PartSelectorModal.vue'

const store = useBuilderStore()
const activeCategory = ref(null)
const isModalOpen = ref(false)

const openSelector = (category) => {
  activeCategory.value = category
  isModalOpen.value = true
}

const handleSelect = ({ slotKey, product }) => {
  store.selectPart(slotKey || activeCategory.value || product?.category, product)
  isModalOpen.value = false
  activeCategory.value = null
}
</script>

<template>
  <div class="pc-builder-view animate-fade-in">
    <header class="builder-header">
      <h2 style="font-size: 2.5rem; font-weight: 800;">Bộ lắp ráp <span class="gradient-text">Thông minh</span></h2>
      <p style="color: var(--text-secondary); max-width: 600px;">
        Hệ thống tự động kiểm tra tương thích giữa các linh kiện. Bạn chỉ việc chọn, chúng tôi lo phần kỹ thuật.
      </p>
    </header>

    <div class="builder-layout">
      <div class="slots-container">
        <BuilderSlot 
          name="Vi xử lý (CPU)" 
          :selected-product="store.slots.CPU" 
          @open-selector="openSelector('CPU')" 
          @remove="store.removePart('CPU')"
        />
        <BuilderSlot 
          name="Bo mạch chủ (Mainboard)" 
          :selected-product="store.slots.Motherboard" 
          @open-selector="openSelector('Motherboard')" 
          @remove="store.removePart('Motherboard')"
        />
        <BuilderSlot 
          name="Bộ nhớ (RAM)" 
          :selected-product="store.slots.RAM" 
          @open-selector="openSelector('RAM')" 
          @remove="store.removePart('RAM')"
        />
        <BuilderSlot 
          name="Ổ lưu trữ (SSD)" 
          :selected-product="store.slots.Storage" 
          @open-selector="openSelector('Storage')" 
          @remove="store.removePart('Storage')"
        />
        <BuilderSlot 
          name="Card đồ họa (GPU)" 
          :selected-product="store.slots.GPU" 
          @open-selector="openSelector('GPU')" 
          @remove="store.removePart('GPU')"
        />
        <BuilderSlot 
          name="Nguồn máy tính (PSU)" 
          :selected-product="store.slots.PSU" 
          @open-selector="openSelector('PSU')" 
          @remove="store.removePart('PSU')"
        />
        <BuilderSlot 
          name="Vỏ máy tính (Case)" 
          :selected-product="store.slots.Case" 
          @open-selector="openSelector('Case')" 
          @remove="store.removePart('Case')"
        />
      </div>

      <BuildSummary />
    </div>

    <!-- Modal cho việc chọn linh kiện -->
    <PartSelectorModal 
      v-if="isModalOpen"
      :is-open="isModalOpen"
      :category="activeCategory"
      @close="isModalOpen = false"
      @choose="handleSelect"
    />
  </div>
</template>

<style scoped>
.pc-builder-view {
  padding: 60px 0;
}

.builder-header {
  margin-bottom: 60px;
}

.builder-layout {
  display: flex;
  gap: 40px;
  align-items: flex-start;
}

.slots-container {
  flex-grow: 1;
}

@media (max-width: 1024px) {
  .builder-layout {
    flex-direction: column;
  }
}
</style>
