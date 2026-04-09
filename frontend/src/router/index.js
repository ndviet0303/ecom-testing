import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import Home from '../views/Home.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: Home },
    { path: '/builder', name: 'builder', component: () => import('../views/PcBuilder.vue') },
    { path: '/products', name: 'products', component: () => import('../views/ProductList.vue') },
    { path: '/products/:id', name: 'product-detail', component: () => import('../views/ProductDetail.vue') },
    { path: '/cart', name: 'cart', component: () => import('../views/Cart.vue') },
    { path: '/login', name: 'login', component: () => import('../views/Login.vue') },
    { path: '/checkout', name: 'checkout', component: () => import('../views/Checkout.vue') },
    { path: '/order-success/:id', name: 'order-success', component: () => import('../views/OrderSuccess.vue') },
    { 
      path: '/profile', 
      name: 'profile', 
      component: () => import('../views/Profile.vue'),
      meta: { requiresAuth: true }
    },
    
    // Admin Routes
    {
      path: '/admin',
      component: () => import('../views/admin/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresStaff: true },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('../views/admin/OrderManager.vue')
        },
        {
          path: 'orders',
          name: 'admin-orders',
          component: () => import('../views/admin/OrderManager.vue')
        },
        {
          path: 'inventory',
          name: 'admin-inventory',
          component: () => import('../views/admin/InventoryMonitor.vue')
        },
        {
          path: 'analytics',
          name: 'admin-analytics',
          component: () => import('../views/admin/SalesAnalytics.vue')
        }
      ]
    }
  ]
})

// Navigation Guard
router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  
  if (auth.token && !auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.requiresStaff && (!auth.isLoggedIn || (auth.user.role !== 'admin' && auth.user.role !== 'staff'))) {
    next({ name: 'home' })
  } else if (to.meta.requiresAuth && !auth.isLoggedIn) {
    next({ name: 'login' })
  } else {
    next()
  }
})

export default router
