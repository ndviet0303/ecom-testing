<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { CheckCircle, QrCode, ArrowLeft } from 'lucide-vue-next'

const route = useRoute()
const orderId = route.params.id
const qrUrl = ref('')
const loading = ref(true)

const fetchQrCode = async () => {
    try {
        // API này chúng ta đã code ở Backend để trả về link ảnh QR
        const response = await axios.get(`/api/v1/orders/${orderId}/sepay-qr`)
        qrUrl.value = response.data.qr_url
    } catch (err) {
        console.error('Error fetching QR code:', err)
    } finally {
        loading.value = false
    }
}

onMounted(fetchQrCode)
</script>

<template>
  <div class="success-view animate-fade-in">
    <div class="success-card glass-panel">
      <div class="success-header">
        <div class="success-icon"><CheckCircle :size="48" /></div>
        <h2 class="gradient-text">Đặt hàng thành công!</h2>
        <p>Mã đơn hàng của bạn là: <strong>#{{ orderId }}</strong></p>
      </div>

      <div class="qr-section">
        <div class="qr-info">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; color: var(--accent-primary); font-weight: 700;">
            <QrCode :size="20" /> QUÉT MÃ THANH TOÁN
          </div>
          <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 24px;">
            Vui lòng sử dụng ứng dụng Ngân hàng để quét mã QR bên cạnh. Hệ thống sẽ tự động xác nhận sau khi nhận được tiền.
          </p>
          <div class="payment-details">
              <div class="detail-row"><span>Phương thức:</span> <span>Chuyển khoản (SePay)</span></div>
              <div class="detail-row"><span>Trạng thái:</span> <span class="badge-pending">Chờ thanh toán</span></div>
          </div>
        </div>

        <div class="qr-display">
          <div v-if="loading" class="qr-placeholder">Đang tạo mã QR...</div>
          <img v-else :src="qrUrl" alt="SePay QR Code" class="qr-img" />
        </div>
      </div>

      <div class="success-actions">
        <RouterLink to="/" class="btn" style="border: 1px solid var(--border-color); color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
           <ArrowLeft :size="18" /> Quay về Trang chủ
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.success-view { padding: 80px 0; display: flex; justify-content: center; }
.success-card { max-width: 800px; width: 100%; padding: 60px; text-align: center; }

.success-icon { color: var(--success); margin-bottom: 24px; }
.success-header h2 { font-size: 2.5rem; font-weight: 800; margin-bottom: 8px; }

.qr-section {
  display: flex;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border-color);
  border-radius: 20px;
  margin: 40px 0;
  overflow: hidden;
  text-align: left;
}

.qr-info { flex: 1; padding: 40px; }
.qr-display { width: 300px; background: white; display: flex; align-items: center; justify-content: center; padding: 20px; }
.qr-img { width: 100%; height: auto; }
.qr-placeholder { color: #1a1a20; font-weight: 600; font-size: 0.9rem; }

.payment-details { border-top: 1px solid var(--border-color); padding-top: 20px; display: flex; flex-direction: column; gap: 8px; }
.detail-row { display: flex; justify-content: space-between; font-size: 0.9rem; }
.badge-pending { color: #eab308; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; }

.success-actions { display: flex; justify-content: center; gap: 16px; }
</style>
