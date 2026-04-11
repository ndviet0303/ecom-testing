<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useProductStore } from "@/stores/productStore";
import {
  Cpu,
  Zap,
  ShieldCheck,
  ArrowRight,
  ShoppingCart,
  MousePointer2,
} from "lucide-vue-next";
import ProductCard from "@/components/ProductCard.vue";
import heroImage from "@/assets/pc-builder-hero.png";

const router = useRouter();
const productStore = useProductStore();
const featuredProducts = ref([]);

onMounted(async () => {
  await productStore.fetchProducts();
  // Lấy 4 sản phẩm đầu tiên làm sản phẩm nổi bật
  featuredProducts.value = productStore.products.slice(0, 4);
});

const startBuilding = () => {
  router.push("/builder");
};
</script>

<template>
  <div class="home-container animate-fade-in">
    <!-- Background Blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <!-- Hero Section -->
    <section class="hero-section">
      <div class="hero-content">
        <div class="badge">Công nghệ Intelligent PC Builder v2.0</div>
        <h1 class="hero-title">
          Kiến tạo <br />
          <span class="gradient-text text-glow">Siêu Máy Tính</span> <br />
          Trong Tầm Tay.
        </h1>
        <p class="hero-subtitle">
          Khám phá hệ thống lắp ráp PC đầu tiên tại Việt Nam tích hợp AI kiểm
          tra tương thích thời gian thực. Bắt đầu hành trình build máy ước mơ
          của bạn ngay hôm nay.
        </p>

        <div class="cta-group">
          <button
            @click="startBuilding"
            class="btn btn-primary cta-btn build-btn"
          >
            Bắt đầu lắp máy <ArrowRight :size="20" />
          </button>
          <button @click="router.push('/products')" class="btn secondary-btn">
            Xem linh kiện
          </button>
        </div>

        <div class="hero-stats">
          <div class="stat-item">
            <div class="stat-val">5K+</div>
            <div class="stat-label">Cấu hình đã lắp</div>
          </div>
          <div class="stat-item">
            <div class="stat-val">100%</div>
            <div class="stat-label">Tương thích</div>
          </div>
          <div class="stat-item">
            <div class="stat-val">24/7</div>
            <div class="stat-label">Hỗ trợ kỹ thuật</div>
          </div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="image-wrapper glass-panel">
          <img :src="heroImage" alt="Ziet PC Premium Build" class="hero-img" />
          <div class="floating-badge top">
            <Cpu :size="18" /> Intel Core i9-14900K
          </div>
          <div class="floating-badge bottom">
            <Zap :size="18" /> RTX 4090 OC Edition
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features-grid">
      <div class="feature-card glass-panel">
        <div class="f-icon"><Cpu :size="24" /></div>
        <h3>Tương thích Tuyệt đối</h3>
        <p>
          Hệ thống tự động loại bỏ các linh kiện không phù hợp với Socket, kích
          thước hoặc công suất nguồn.
        </p>
      </div>
      <div class="feature-card glass-panel">
        <div class="f-icon"><Zap :size="24" /></div>
        <h3>Hiệu năng Tối ưu</h3>
        <p>
          Đề xuất cấu hình cân bằng nhất dựa trên ngân sách và nhu cầu sử dụng
          của bạn.
        </p>
      </div>
      <div class="feature-card glass-panel">
        <div class="f-icon"><ShieldCheck :size="24" /></div>
        <h3>Bảo hành 1-đổi-1</h3>
        <p>
          Chúng tôi cam kết chất lượng linh kiện chính hãng với chế độ bảo hành
          vàng cho mọi bộ máy.
        </p>
      </div>
    </section>

    <!-- Featured Products -->
    <section class="featured-products">
      <div class="section-header">
        <h2 class="section-title">
          Linh kiện <span class="gradient-text">Mới nhất</span>
        </h2>
        <RouterLink to="/products" class="view-all"
          >Xem tất cả <ArrowRight :size="16"
        /></RouterLink>
      </div>

      <div class="products-grid">
        <ProductCard
          v-for="product in featuredProducts"
          :key="product.id"
          :product="product"
        />
      </div>
    </section>

    <!-- How it works -->
    <section class="workflow-section glass-panel">
      <div class="workflow-content">
        <h2 style="font-size: 2.2rem; margin-bottom: 32px">
          Quy trình tạo nên <span class="gradient-text">Kiệt tác</span>
        </h2>
        <div class="steps">
          <div class="step">
            <div class="step-num">01</div>
            <h4>Chọn linh kiện</h4>
            <p>Từ CPU đến Case, mọi thứ đều sẵn sàng trong kho.</p>
          </div>
          <div class="step">
            <div class="step-num">02</div>
            <h4>Kiểm tra tương thích</h4>
            <p>Hệ thống sẽ "quét" toàn bộ lỗi vật lý và điện năng.</p>
          </div>
          <div class="step">
            <div class="step-num">03</div>
            <h4>Xác nhận & Thanh toán</h4>
            <p>Chọn địa chỉ và thanh toán nhanh qua mã QR SePay.</p>
          </div>
        </div>
        <button
          @click="startBuilding"
          class="btn btn-primary"
          style="margin-top: 40px; width: 240px; height: 56px"
        >
          Bắt đầu lắp ngay
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home-container {
  position: relative;
  padding-bottom: 120px;
}

/* Background Decorations */
.blob {
  position: absolute;
  width: 500px;
  height: 500px;
  background: radial-gradient(
    circle,
    rgba(37, 99, 235, 0.1) 0%,
    rgba(255, 255, 255, 0) 70%
  );
  filter: blur(80px);
  z-index: -1;
  border-radius: 50%;
}
.blob-1 {
  top: -100px;
  left: -100px;
}
.blob-2 {
  top: 400px;
  right: -100px;
  background: radial-gradient(
    circle,
    rgba(14, 165, 233, 0.08) 0%,
    rgba(255, 255, 255, 0) 70%
  );
}

/* Hero Section */
.hero-section {
  display: flex;
  align-items: center;
  gap: 80px;
  padding: 100px 0;
  min-height: 85vh;
}

.hero-content {
  flex: 1.2;
}
.hero-visual {
  flex: 1;
}

.badge {
  display: inline-block;
  padding: 8px 16px;
  background: rgba(37, 99, 235, 0.08);
  border: 1px solid rgba(37, 99, 235, 0.15);
  border-radius: 30px;
  color: var(--accent-primary);
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 24px;
}

.hero-title {
  font-size: 4.8rem;
  font-weight: 900;
  line-height: 1.1;
  margin-bottom: 32px;
  letter-spacing: -2px;
}

.text-glow {
  text-shadow: none;
}

.hero-subtitle {
  font-size: 1.25rem;
  color: var(--text-secondary);
  margin-bottom: 48px;
  max-width: 600px;
  line-height: 1.6;
}

.cta-group {
  display: flex;
  gap: 20px;
  margin-bottom: 60px;
}

.cta-btn {
  height: 60px;
  padding: 0 40px;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: 12px;
}

.secondary-btn {
  background: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-primary);
  height: 60px;
  padding: 0 32px;
}
.secondary-btn:hover {
  border-color: var(--accent-primary);
}

.hero-stats {
  display: flex;
  gap: 40px;
}

.stat-val {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 4px;
}
.stat-label {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

/* Hero Visual */
.image-wrapper {
  position: relative;
  padding: 12px;
  border-radius: 32px;
  transform: perspective(1000px) rotateY(-10deg) rotateX(5deg);
  box-shadow: 0 40px 100px rgba(0, 0, 0, 0.08);
  background: #ffffff;
}

.hero-img {
  width: 100%;
  border-radius: 24px;
  display: block;
}

.floating-badge {
  position: absolute;
  padding: 12px 20px;
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(0, 0, 0, 0.05);
  border-radius: 16px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-primary);
}

.floating-badge.top {
  top: 30px;
  right: -30px;
  animation: float 6s infinite ease-in-out;
}
.floating-badge.bottom {
  bottom: 60px;
  left: -20px;
  animation: float 6s infinite ease-in-out reverse;
}

@keyframes float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-15px);
  }
}

/* Features */
.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
  margin-bottom: 120px;
}

.feature-card {
  padding: 40px;
  transition: transform 0.3s ease;
}
.feature-card:hover {
  transform: translateY(-10px);
}

.f-icon {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: rgba(37, 99, 235, 0.08);
  color: var(--accent-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}

.feature-card h3 {
  font-size: 1.35rem;
  margin-bottom: 16px;
  font-weight: 700;
}
.feature-card p {
  color: var(--text-secondary);
  line-height: 1.6;
}

/* Featured Products */
.featured-products {
  margin-bottom: 120px;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 40px;
}
.section-title {
  font-size: 2.8rem;
  font-weight: 800;
}
.view-all {
  color: var(--accent-primary);
  text-decoration: none;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
}

/* Workflow */
.workflow-section {
  padding: 80px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 60px;
  margin-top: 20px;
}

.step-num {
  font-size: 3rem;
  font-weight: 900;
  opacity: 0.1;
  margin-bottom: -15px;
}

.step h4 {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 12px;
}
.step p {
  color: var(--text-secondary);
  font-size: 0.95rem;
}

@media (max-width: 1200px) {
  .hero-section {
    flex-direction: column;
    text-align: center;
  }
  .hero-subtitle {
    margin: 0 auto 48px;
  }
  .cta-group {
    justify-content: center;
  }
  .hero-stats {
    justify-content: center;
  }
  .products-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .features-grid {
    grid-template-columns: 1fr;
  }
  .steps {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}
</style>
