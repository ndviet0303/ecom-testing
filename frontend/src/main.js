import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/authStore'
import './index.css'

const app = createApp(App)
const pinia = createPinia()

let isHandlingUnauthorized = false

axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error?.response?.status === 401) {
      const authStore = useAuthStore(pinia)
      const requestUrl = String(error?.config?.url || '')
      const isAuthEndpoint = requestUrl.includes('/api/auth/login')
        || requestUrl.includes('/api/auth/register')
        || requestUrl.includes('/api/auth/logout')

      if (!isAuthEndpoint && authStore.token && !isHandlingUnauthorized) {
        isHandlingUnauthorized = true
        authStore.clearSession()

        if (router.currentRoute.value.name !== 'login') {
          await router.push({ name: 'login' })
        }

        isHandlingUnauthorized = false
      }
    }

    return Promise.reject(error)
  }
)

app.use(pinia)
app.use(router)

app.mount('#app')
