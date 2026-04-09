<script setup>
import { useToastStore } from '@/stores/toastStore'
import { CheckCircle2, AlertCircle, X } from 'lucide-vue-next'

const toastStore = useToastStore()
</script>

<template>
  <div class="toast-container">
    <TransitionGroup name="toast">
      <div 
        v-for="toast in toastStore.toasts" 
        :key="toast.id" 
        class="toast-item glass-panel"
        :class="toast.type"
      >
        <div class="toast-icon">
          <CheckCircle2 v-if="toast.type === 'success'" :size="20" />
          <AlertCircle v-else :size="20" />
        </div>
        <div class="toast-message">{{ toast.message }}</div>
        <button class="toast-close" @click="toastStore.remove(toast.id)">
          <X :size="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 12px;
  pointer-events: none;
}

.toast-item {
  pointer-events: auto;
  min-width: 300px;
  max-width: 450px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-left: 4px solid var(--accent-primary);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  animation: slideIn 0.3s ease forwards;
}

.toast-item.success {
  border-left-color: #22c55e;
  background: rgba(34, 197, 94, 0.1);
}

.toast-item.success .toast-icon {
  color: #22c55e;
}

.toast-item.error {
  border-left-color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
}

.toast-item.error .toast-icon {
  color: #ef4444;
}

.toast-message {
  flex-grow: 1;
  font-size: 0.95rem;
  font-weight: 500;
  color: var(--text-primary);
}

.toast-close {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  opacity: 0.6;
  transition: opacity 0.2s;
  padding: 4px;
}

.toast-close:hover {
  opacity: 1;
}

/* Animations */
.toast-enter-active, .toast-leave-active {
  transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(40px) scale(0.9);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(20px) scale(0.95);
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(20px); }
  to { opacity: 1; transform: translateX(0); }
}
</style>
