import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    loading: false
  }),

  getters: {
    isLoggedIn: (state) => !!state.token
  },

  actions: {
    applyAuthHeader(token = this.token) {
      if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
      } else {
        delete axios.defaults.headers.common['Authorization']
      }
    },

    clearSession() {
      this.user = null
      this.token = null
      localStorage.removeItem('auth_token')
      this.applyAuthHeader(null)
    },

    async login(email, password) {
      this.loading = true
      try {
        const response = await axios.post('/api/auth/login', { email, password })
        this.token = response.data.token
        localStorage.setItem('auth_token', this.token)
        this.applyAuthHeader(this.token)
        await this.fetchUser()
        return true
      } catch (err) {
        console.error('Lỗi đăng nhập:', err)
        return false
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        const response = await axios.post('/api/auth/register', payload)
        const token = response?.data?.token

        if (!token) {
          return { success: false, message: 'Đăng ký thành công nhưng không nhận được token.' }
        }

        this.token = token
        localStorage.setItem('auth_token', this.token)
        this.applyAuthHeader(this.token)

        if (response?.data?.user) {
          this.user = response.data.user
        } else {
          await this.fetchUser()
        }

        return { success: true }
      } catch (err) {
        const message = err?.response?.data?.message || 'Không thể đăng ký tài khoản.'
        return { success: false, message }
      } finally {
        this.loading = false
      }
    },

    async fetchUser() {
      if (!this.token) return
      this.applyAuthHeader(this.token)
      try {
        const response = await axios.get('/api/auth/me')
        this.user = response.data
      } catch (err) {
        this.clearSession()
      }
    },

    async logout() {
      if (this.token) {
        try {
          this.applyAuthHeader(this.token)
          await axios.post('/api/auth/logout')
        } catch (err) {
          console.error('Lỗi đăng xuất:', err)
        }
      }

      this.clearSession()
    }
  }
})
