<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { TrendingUp, ShoppingBag, CreditCard, Award } from 'lucide-vue-next'

const stats = ref({})
const trend = ref([])
const topProducts = ref([])
const loading = ref(true)

const fetchData = async () => {
    loading.value = true
    try {
        const [salesResp, topResp] = await Promise.all([
            axios.get('/api/v1/admin/analytics/sales'),
            axios.get('/api/v1/admin/analytics/top-products')
        ])
        stats.value = salesResp.data.stats
        trend.value = salesResp.data.trend
        topProducts.value = topResp.data
    } catch (err) {
        console.error('Lỗi tải báo cáo:', err)
    } finally {
        loading.value = false
    }
}

const formatPrice = (cents) => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(cents || 0)
}

onMounted(fetchData)
</script>

<template>
  <div class="sales-analytics">
    <div style="margin-bottom: 32px;">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Báo cáo <span class="gradient-text">Doanh thu</span></h2>
      <p style="color: var(--text-secondary);">Phân tích hiệu quả kinh doanh trong 30 ngày qua.</p>
    </div>

    <div v-if="loading" class="state-msg">Đang tính toán số liệu...</div>

    <div v-else>
      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card glass-panel">
          <div class="stat-icon r"><CreditCard :size="20" /></div>
          <div>
            <label>Tổng doanh thu</label>
            <div class="v">{{ formatPrice(stats.total_revenue) }}</div>
          </div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-icon o"><ShoppingBag :size="20" /></div>
          <div>
            <label>Tổng đơn hàng</label>
            <div class="v">{{ stats.total_orders }}</div>
          </div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-icon p"><TrendingUp :size="20" /></div>
          <div>
            <label>Tăng trưởng</label>
            <div class="v" style="color: var(--success);">+{{ stats.monthly_growth }}%</div>
          </div>
        </div>
      </div>

      <div class="analytics-layout">
        <div class="chart-container glass-panel">
          <h3 style="margin-bottom: 24px;">Xu hướng 30 ngày</h3>
          <div class="bar-chart">
            <div v-for="day in trend" :key="day.date" class="bar-wrapper" :title="`${day.date}: ${formatPrice(day.revenue)}`">
                <div class="bar" :style="{ height: (day.revenue / 5000000) * 100 + '%' }"></div>
            </div>
            <div v-if="trend.length === 0" class="empty-chart">Chưa có dữ liệu giao dịch</div>
          </div>
          <div class="chart-label">Biểu đồ doanh thu dự trên các đơn hàng đã thanh toán</div>
        </div>

        <div class="top-products glass-panel">
          <h3 style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
            <Award :size="20" color="var(--accent-primary)" /> Bán chạy nhất
          </h3>
          <div class="product-list">
            <div v-for="(p, idx) in topProducts" :key="p.product_id" class="top-item">
              <span class="rank">{{ idx + 1 }}</span>
              <img :src="p.product.image_url" alt="" class="p-img" />
              <div class="p-info">
                <div class="p-name">{{ p.product.name }}</div>
                <div class="p-meta">Đã bán: {{ p.total_sold }} | {{ formatPrice(p.total_revenue) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-bottom: 40px;
}

.stat-card {
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 20px;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon.r { background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); }
.stat-icon.o { background: rgba(34, 197, 94, 0.1); color: var(--success); }
.stat-icon.p { background: rgba(168, 85, 247, 0.1); color: #a855f7; }

.stat-card label { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px; display: block; }
.stat-card .v { font-size: 1.4rem; font-weight: 800; }

.analytics-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 40px;
}

.chart-container { padding: 32px; height: 400px; display: flex; flex-direction: column; }
.bar-chart {
  flex-grow: 1;
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding-top: 40px;
  border-bottom: 2px solid var(--border-color);
}

.bar-wrapper { flex: 1; height: 100%; display: flex; align-items: flex-end; position: relative; }
.bar {
  width: 100%;
  background: linear-gradient(to top, var(--accent-primary), var(--accent-secondary));
  border-radius: 4px 4px 0 0;
  transition: all 0.5s ease;
  min-height: 4px;
}

.empty-chart { width: 100%; text-align: center; color: var(--text-secondary); padding-bottom: 100px; }
.chart-label { margin-top: 16px; font-size: 0.8rem; color: var(--text-secondary); text-align: center; }

.top-products { padding: 32px; }
.top-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid var(--border-color);
}

.rank { font-weight: 800; color: var(--accent-primary); font-size: 1.2rem; width: 24px; }
.p-img { width: 48px; height: 48px; border-radius: 8px; object-fit: contain; background: #1a1a20; }
.p-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.p-meta { font-size: 0.75rem; color: var(--text-secondary); }

.state-msg { text-align: center; padding: 80px; color: var(--text-secondary); }
</style>
