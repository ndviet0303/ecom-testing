<template>
  <section class="not-found">
    <h1>Khong tim thay trang</h1>
    <p>Duong dan ban vua truy cap khong ton tai hoac da duoc di chuyen.</p>
    <RouterLink to="/" class="go-home">Quay ve trang chu</RouterLink>
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
