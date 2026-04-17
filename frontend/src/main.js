import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/authStore'
import { useToastStore } from './stores/toastStore'
import './index.css'

const app = createApp(App)
const pinia = createPinia()

let isHandlingUnauthorized = false

function extractErrorMessage(error) {
  const responseData = error?.response?.data

  if (typeof responseData?.message === 'string' && responseData.message.trim() !== '') {
    return responseData.message
  }

  if (responseData?.errors && typeof responseData.errors === 'object') {
    const firstError = Object.values(responseData.errors)?.[0]
    if (Array.isArray(firstError) && firstError.length > 0) {
      return String(firstError[0])
    }
  }

  if (typeof error?.message === 'string' && error.message.trim() !== '') {
    return error.message
  }

  return 'Có lỗi xảy ra. Vui lòng thử lại.'
}

axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    const toastStore = useToastStore(pinia)

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
    } else {
      toastStore.error(extractErrorMessage(error))
    }

    return Promise.reject(error)
  }
)

app.config.errorHandler = (error, instance, info) => {
  const toastStore = useToastStore(pinia)
  toastStore.error('Ứng dụng gặp lỗi. Vui lòng tải lại trang.')
  console.error('Vue error:', error, info, instance)
}

window.addEventListener('unhandledrejection', (event) => {
  const toastStore = useToastStore(pinia)
  toastStore.error('Tác vụ thất bại. Vui lòng thử lại.')
  console.error('Unhandled promise rejection:', event.reason)
})

app.use(pinia)
app.use(router)

app.mount('#app')
