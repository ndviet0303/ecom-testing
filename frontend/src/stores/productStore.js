import { defineStore } from 'pinia'
import axios from 'axios'

export const useProductStore = defineStore('product', {
  state: () => ({
    products: [],
    loading: false,
    loadingMore: false,
    error: null,
    currentCategory: null,
    searchQuery: '',
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0
    }
  }),

  actions: {
    async fetchProducts(category = null, search = '') {
      if (category !== undefined) this.currentCategory = category
      if (search !== undefined) this.searchQuery = search
      
      this.loading = true
      this.error = null
      this.products = []
      this.pagination.currentPage = 1
      
      try {
        const response = await axios.get('/api/v1/products', {
          params: {
            category: this.currentCategory,
            q: this.searchQuery,
            page: 1
          }
        })
        
        const data = response.data
        this.products = data.data
        this.pagination.lastPage = data.last_page
        this.pagination.total = data.total
        
        // UX delay for skeletons
        if (this.loading) await new Promise(resolve => setTimeout(resolve, 600))
      } catch (err) {
        this.error = 'Không thể tải danh sách sản phẩm.'
        console.error(err)
      } finally {
        this.loading = false
      }
    },

    async loadMore() {
      if (this.pagination.currentPage >= this.pagination.lastPage || this.loadingMore) return
      
      this.loadingMore = true
      const nextPage = this.pagination.currentPage + 1
      
      try {
        const response = await axios.get('/api/v1/products', {
          params: {
            category: this.currentCategory,
            q: this.searchQuery,
            page: nextPage
          }
        })
        
        const data = response.data
        this.products = [...this.products, ...data.data]
        this.pagination.currentPage = data.current_page
        this.pagination.lastPage = data.last_page
      } catch (err) {
        console.error('Lỗi tải thêm:', err)
      } finally {
        this.loadingMore = false
      }
    },

    setCategory(category) {
      this.fetchProducts(category, this.searchQuery)
    },

    setSearch(query) {
      this.fetchProducts(this.currentCategory, query)
    }
  }
})
