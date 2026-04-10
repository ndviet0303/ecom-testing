import { defineStore } from 'pinia'
import axios from 'axios'

export const useBuilderStore = defineStore('builder', {
  state: () => ({
    slots: {
      CPU: null,
      Motherboard: null,
      RAM: null,
      Storage: null,
      GPU: null,
      PSU: null,
      Case: null
    },
    validation: {
      isValid: true,
      errors: [],
      estimatedWattage: 0,
      loading: false
    }
  }),

  getters: {
    selectedIds: (state) => {
      return Object.values(state.slots)
        .filter(p => p !== null)
        .map(p => p.id)
    },
    totalPrice: (state) => {
      return Object.values(state.slots)
        .filter(p => p !== null)
        .reduce((sum, p) => sum + (p.base_price_cents || 0), 0)
    }
  },

  actions: {
    resolveSlotKey(category) {
      if (!category) return null

      if (Object.prototype.hasOwnProperty.call(this.slots, category)) {
        return category
      }

      return this.findSlotKey(category)
    },

    selectPart(category, product) {
      const slotKey = this.resolveSlotKey(category || product?.category)
      if (slotKey) {
        this.slots[slotKey] = product
        this.validateBuild()
      }
    },

    removePart(slotKey) {
      this.slots[slotKey] = null
      this.validateBuild()
    },

    findSlotKey(category) {
      if (category.toLowerCase().includes('motherboard') || category.toLowerCase().includes('mainboard')) return 'Motherboard'
      if (category.toLowerCase().includes('cpu')) return 'CPU'
      if (category.toLowerCase().includes('gpu') || category.toLowerCase().includes('vga')) return 'GPU'
      if (category.toLowerCase().includes('ram')) return 'RAM'
      if (category.toLowerCase().includes('storage') || category.toLowerCase().includes('ssd') || category.toLowerCase().includes('hdd')) return 'Storage'
      if (category.toLowerCase().includes('psu') || category.toLowerCase().includes('power supply')) return 'PSU'
      if (category.toLowerCase().includes('case') || category.toLowerCase().includes('chassis')) return 'Case'
      return null
    },

    async validateBuild() {
      const ids = this.selectedIds
      if (ids.length === 0) {
        this.validation = { isValid: true, errors: [], estimatedWattage: 0, loading: false }
        return
      }

      this.validation.loading = true
      try {
        const response = await axios.post('/api/v1/pc-builder/validate', {
          product_ids: ids
        })
        
        this.validation.isValid = response.data.valid
        this.validation.errors = response.data.errors
        this.validation.estimatedWattage = response.data.estimated_wattage
      } catch (err) {
        console.error('Validation error:', err)
      } finally {
        this.validation.loading = false
      }
    }
  }
})
