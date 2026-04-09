<script setup>
import { ShoppingBag, Box, History, LayoutDashboard } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
</script>

<template>
  <aside class="admin-sidebar glass-panel">
    <div style="padding: 32px 24px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
      <h3 style="font-size: 1.1rem; font-weight: 700;">Admin <span class="gradient-text">Panel</span></h3>
    </div>

    <nav class="admin-nav">
      <RouterLink to="/admin" class="nav-item" exact-active-class="active">
        <LayoutDashboard :size="20" /> Dashboard
      </RouterLink>
      
      <RouterLink to="/admin/orders" class="nav-item" active-class="active">
        <ShoppingBag :size="20" /> Quản lý Đơn hàng
      </RouterLink>

      <RouterLink to="/admin/analytics" class="nav-item" active-class="active">
        <History :size="20" /> Báo cáo doanh thu
      </RouterLink>

      <RouterLink to="/admin/inventory" class="nav-item" active-class="active">
        <Box :size="20" /> Theo dõi Kho
      </RouterLink>

      <RouterLink to="/admin/audit-logs" class="nav-item" active-class="active">
        <History :size="20" /> Nhật ký hệ thống
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.admin-sidebar {
  width: 260px;
  height: calc(100vh - 120px);
  position: sticky;
  top: 100px;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.admin-nav {
  display: flex;
  flex-direction: column;
  padding: 0 16px;
  gap: 8px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  text-decoration: none;
  color: var(--text-secondary);
  font-weight: 500;
  transition: all 0.3s ease;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text-primary);
}

.nav-item.active {
  background: rgba(99, 102, 241, 0.1);
  color: var(--accent-primary);
  border: 1px solid rgba(99, 102, 241, 0.2);
}
</style>
