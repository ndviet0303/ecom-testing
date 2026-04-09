<script setup>
import { RouterView } from 'vue-router'
import AdminSidebar from '@/components/AdminSidebar.vue'
</script>

<template>
  <div class="admin-layout animate-fade-in">
    <AdminSidebar />
    <main class="admin-main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.admin-layout {
  display: flex;
  gap: 40px;
  padding: 40px 0;
  align-items: flex-start;
}

.admin-main {
  flex-grow: 1;
  min-height: 80vh;
}
</style>
