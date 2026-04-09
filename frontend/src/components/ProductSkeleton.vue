<template>
  <div class="product-skeleton glass-panel">
    <div class="skeleton-image pulse"></div>
    <div class="skeleton-info">
      <div class="skeleton-tag pulse"></div>
      <div class="skeleton-title pulse"></div>
      <div class="skeleton-title short pulse"></div>
      <div class="skeleton-footer">
        <div class="skeleton-price pulse"></div>
        <div class="skeleton-btn pulse"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.product-skeleton {
  height: 380px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.skeleton-image {
  height: 200px;
  background: rgba(0, 0, 0, 0.05);
}

.skeleton-info {
  padding: 20px;
  flex-grow: 1;
}

.skeleton-tag {
  width: 60px;
  height: 12px;
  background: rgba(0, 0, 0, 0.05);
  margin-bottom: 12px;
  border-radius: 4px;
}

.skeleton-title {
  width: 100%;
  height: 20px;
  background: rgba(0, 0, 0, 0.05);
  margin-bottom: 8px;
  border-radius: 4px;
}

.skeleton-title.short {
  width: 60%;
}

.skeleton-footer {
  margin-top: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skeleton-price {
  width: 100px;
  height: 24px;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 4px;
}

.skeleton-btn {
  width: 40px;
  height: 40px;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}

.pulse {
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0% { opacity: 0.5; }
  50% { opacity: 0.8; }
  100% { opacity: 0.5; }
}
</style>
