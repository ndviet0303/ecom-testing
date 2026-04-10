<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { RefreshCcw } from 'lucide-vue-next'

const logs = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const fetchLogs = async (targetPage = 1) => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/admin/audit-logs', {
      params: { page: targetPage, per_page: 25 },
    })

    logs.value = response.data.data || []
    page.value = response.data.current_page || 1
    lastPage.value = response.data.last_page || 1
  } catch (err) {
    console.error('Lỗi tải nhật ký hệ thống:', err)
  } finally {
    loading.value = false
  }
}

const formatDateTime = (value) => {
  if (!value) return 'N/A'

  return new Date(value).toLocaleString('vi-VN')
}

const toPrettyJson = (value) => {
  if (!value) return '{}'

  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

onMounted(() => fetchLogs(1))
</script>

<template>
  <div class="audit-logs">
    <div class="head">
      <h2 style="font-size: 1.75rem; font-weight: 700;">Nhật ký <span class="gradient-text">Hệ thống</span></h2>
      <button @click="fetchLogs(page)" class="btn-refresh">
        <RefreshCcw :size="16" /> Làm mới
      </button>
    </div>

    <div v-if="loading" class="state-msg">Đang tải nhật ký...</div>

    <div v-else-if="logs.length === 0" class="state-msg glass-panel">Chưa có bản ghi nhật ký.</div>

    <div v-else class="log-list">
      <article v-for="log in logs" :key="log.id" class="log-item glass-panel">
        <header class="log-head">
          <div>
            <div class="event">{{ log.event || 'unknown.event' }}</div>
            <div class="meta">ID #{{ log.id }} · {{ formatDateTime(log.created_at) }}</div>
          </div>
          <div class="actor">{{ log.user?.email || 'system' }}</div>
        </header>

        <div class="target">{{ log.target_type || 'N/A' }} #{{ log.target_id || 'N/A' }}</div>
        <pre class="payload">{{ toPrettyJson(log.payload) }}</pre>
      </article>
    </div>

    <footer v-if="lastPage > 1" class="pager">
      <button class="btn-page" :disabled="page <= 1" @click="fetchLogs(page - 1)">Trước</button>
      <span>Trang {{ page }} / {{ lastPage }}</span>
      <button class="btn-page" :disabled="page >= lastPage" @click="fetchLogs(page + 1)">Sau</button>
    </footer>
  </div>
</template>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.btn-refresh {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
}

.log-list {
  display: grid;
  gap: 16px;
}

.log-item {
  padding: 16px;
}

.log-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 10px;
}

.event {
  font-weight: 700;
}

.meta {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.actor {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.target {
  margin-bottom: 12px;
  font-size: 0.9rem;
}

.payload {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.8rem;
  color: var(--text-secondary);
  background: rgba(0, 0, 0, 0.15);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 10px;
}

.pager {
  margin-top: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.btn-page {
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  padding: 6px 10px;
  border-radius: 8px;
  cursor: pointer;
}

.btn-page:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.state-msg {
  text-align: center;
  padding: 60px;
  color: var(--text-secondary);
}
</style>
