import { defineStore } from 'pinia'
import axios from 'axios'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    subtotal: 0,
    loading: false,
    cartToken: localStorage.getItem('cart_token') || null
  }),

  getters: {
    itemCount: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0)
  },

  actions: {
    async fetchCart() {
      this.loading = true
      try {
        const response = await axios.get('/api/v1/cart', {
          headers: { 'X-Cart-Token': this.cartToken }
        })
        
        this.items = response.data.items || []
        this.subtotal = response.data.subtotal_cents || 0
        
        // Cập nhật token nếu backend trả về token mới (cho guest đầu tiên)
        const newToken = response.headers['x-cart-token']
        if (newToken) {
          this.setToken(newToken)
        }
      } catch (err) {
        console.error('Lỗi khi tải giỏ hàng:', err)
      } finally {
        this.loading = false
      }
    },

    async addToCart(productId, quantity = 1) {
      try {
        const response = await axios.post('/api/v1/cart/items', {
          product_id: productId,
          quantity: quantity
        }, {
          headers: { 'X-Cart-Token': this.cartToken }
        })

        const newToken = response.headers['x-cart-token']
        if (newToken) this.setToken(newToken)
        
        await this.fetchCart()
      } catch (err) {
        console.error('Lỗi khi thêm vào giỏ hàng:', err)
      }
    },

    async addBuildToCart(products) {
      const items = Object.values(products)
        .filter(p => p !== null)
        .map(p => ({ product_id: p.id, quantity: 1 }))

      if (items.length === 0) return

      try {
        const response = await axios.post('/api/v1/cart/items/bulk', { items }, {
          headers: { 'X-Cart-Token': this.cartToken }
        })

        const newToken = response.headers['x-cart-token']
        if (newToken) this.setToken(newToken)
        
        await this.fetchCart()
      } catch (err) {
        console.error('Lỗi khi thêm bộ máy vào giỏ hàng:', err)
      }
    },

    async updateQuantity(productId, quantity) {
        try {
          await axios.put(`/api/v1/cart/items/${productId}`, { quantity }, {
            headers: { 'X-Cart-Token': this.cartToken }
          })
          await this.fetchCart()
        } catch (err) {
          console.error('Lỗi khi cập nhật số lượng:', err)
        }
    },

    async removeItem(productId) {
      try {
        await axios.delete(`/api/v1/cart/items/${productId}`, {
          headers: { 'X-Cart-Token': this.cartToken }
        })
        await this.fetchCart()
      } catch (err) {
        console.error('Lỗi khi xóa khỏi giỏ hàng:', err)
      }
    },

    setToken(token) {
      this.cartToken = token
      localStorage.setItem('cart_token', token)
    }
  }
})
