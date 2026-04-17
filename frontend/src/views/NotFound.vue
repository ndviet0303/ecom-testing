<template>
  <section class="not-found">
    <h1>Không tìm thấy trang</h1>
    <p>Đường dẫn bạn vừa truy cập không tồn tại hoặc đã được di chuyển.</p>
    <RouterLink to="/" class="go-home">Quay về trang chủ</RouterLink>
  </section>
</template>

<script setup>
import { RouterLink } from "vue-router";
</script>

<style scoped>
.not-found {
  min-height: 60vh;
  display: grid;
  place-content: center;
  text-align: center;
  gap: 12px;
  padding: 24px;
}

h1 {
  font-size: 2rem;
  margin: 0;
}

p {
  margin: 0;
  color: var(--text-secondary);
}

.go-home {
  margin-inline: auto;
  background: var(--accent-primary);
  color: #fff;
  text-decoration: none;
  padding: 10px 14px;
  border-radius: 10px;
  font-weight: 600;
}
</style>
