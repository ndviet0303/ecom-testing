import { defineStore } from 'pinia'
import axios from 'axios'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    subtotal: 0,
    loading: false,
    cartToken: localStorage.getItem('cart_token') || null,
    pendingItemIds: []
  }),

  getters: {
    itemCount: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    isItemPending: (state) => (productId) => state.pendingItemIds.includes(productId)
  },

  actions: {
    isValidCartToken(token = this.cartToken) {
      return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(token || ''))
    },

    applyCartTokenFromResponse(response) {
      const newToken = response.headers['x-cart-token']
      if (newToken) {
        this.setToken(newToken)
      }
    },

    recalculateSubtotal() {
      this.subtotal = this.items.reduce(
        (sum, item) => sum + ((item.unit_price_cents || 0) * (item.quantity || 0)),
        0
      )
    },

    setItemPending(productId, pending) {
      if (pending) {
        if (!this.pendingItemIds.includes(productId)) {
          this.pendingItemIds.push(productId)
        }
        return
      }

      this.pendingItemIds = this.pendingItemIds.filter((id) => id !== productId)
    },

    async fetchCart() {
      if (this.cartToken && !this.isValidCartToken()) {
        this.clearToken()
      }

      this.loading = true
      try {
        const response = await axios.get('/api/v1/cart', {
          headers: { 'X-Cart-Token': this.cartToken }
        })
        
        this.items = response.data.items || []
        this.subtotal = response.data.subtotal_cents || 0
        this.applyCartTokenFromResponse(response)
      } catch (err) {
        if (err.response?.status === 404 && this.cartToken) {
          this.clearToken()
          this.items = []
          this.subtotal = 0
          return
        }
        console.error('Lỗi khi tải giỏ hàng:', err)
      } finally {
        this.loading = false
      }
    },

    async mergeGuestCart() {
      if (!this.cartToken) return false
      if (!this.isValidCartToken()) {
        this.clearToken()
        return false
      }

      try {
        await axios.post('/api/v1/cart/merge', {
          guest_token: this.cartToken
        })
        this.clearToken()
        await this.fetchCart()
        return true
      } catch (err) {
        if (err.response?.status === 404 || err.response?.status === 422) {
          this.clearToken()
          return false
        }
        console.error('Lỗi khi gộp giỏ hàng guest:', err)
        throw err
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

        this.applyCartTokenFromResponse(response)
        await this.fetchCart()
        return true
      } catch (err) {
        console.error('Lỗi khi thêm vào giỏ hàng:', err)
        throw err
      }
    },

    async addBuildToCart(products) {
      const items = Object.values(products)
        .filter(p => p !== null)
        .map(p => ({ product_id: p.id, quantity: 1 }))

      if (items.length === 0) return false

      try {
        const response = await axios.post('/api/v1/cart/items/bulk', { items }, {
          headers: { 'X-Cart-Token': this.cartToken }
        })

        this.applyCartTokenFromResponse(response)
        await this.fetchCart()
        return true
      } catch (err) {
        console.error('Lỗi khi thêm bộ máy vào giỏ hàng:', err)
        throw err
      }
    },

    async updateQuantity(productId, quantity) {
      const targetItem = this.items.find((item) => item.product_id === productId)
      if (!targetItem || quantity < 1 || this.isItemPending(productId)) return

      const previousQuantity = targetItem.quantity

      targetItem.quantity = quantity
      this.recalculateSubtotal()
      this.setItemPending(productId, true)

      try {
        const response = await axios.put(`/api/v1/cart/items/${productId}`, { quantity }, {
          headers: { 'X-Cart-Token': this.cartToken }
        })

        this.applyCartTokenFromResponse(response)
        this.subtotal = response.data.subtotal_cents ?? this.subtotal
      } catch (err) {
        targetItem.quantity = previousQuantity
        this.recalculateSubtotal()
        console.error('Lỗi khi cập nhật số lượng:', err)
        throw err
      } finally {
        this.setItemPending(productId, false)
      }
    },

    async removeItem(productId) {
      if (this.isItemPending(productId)) return

      const previousItems = [...this.items]
      const nextItems = this.items.filter((item) => item.product_id !== productId)

      this.items = nextItems
      this.recalculateSubtotal()
      this.setItemPending(productId, true)

      try {
        const response = await axios.delete(`/api/v1/cart/items/${productId}`, {
          headers: { 'X-Cart-Token': this.cartToken }
        })

        this.applyCartTokenFromResponse(response)
        this.subtotal = response.data.subtotal_cents ?? this.subtotal
      } catch (err) {
        this.items = previousItems
        this.recalculateSubtotal()
        console.error('Lỗi khi xóa khỏi giỏ hàng:', err)
        throw err
      } finally {
        this.setItemPending(productId, false)
      }
    },

    setToken(token) {
      this.cartToken = token
      localStorage.setItem('cart_token', token)
    },

    clearToken() {
      this.cartToken = null
      localStorage.removeItem('cart_token')
    }
  }
})
