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
    async login(email, password) {
      this.loading = true
      try {
        const response = await axios.post('/api/auth/login', { email, password })
        this.token = response.data.token
        localStorage.setItem('auth_token', this.token)
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
        await this.fetchUser()
        return true
      } catch (err) {
        console.error('Lỗi đăng nhập:', err)
        return false
      } finally {
        this.loading = false
      }
    },

    async fetchUser() {
      if (!this.token) return
      try {
        const response = await axios.get('/api/auth/me', {
          headers: { 'Authorization': `Bearer ${this.token}` }
        })
        this.user = response.data
      } catch (err) {
        this.logout()
      }
    },

    logout() {
      this.user = null
      this.token = null
      localStorage.removeItem('auth_token')
      delete axios.defaults.headers.common['Authorization']
    }
  }
})
