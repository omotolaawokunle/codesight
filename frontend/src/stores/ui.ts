import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: number
  message: string
  type: ToastType
}

let _nextId = 1

export const useUiStore = defineStore('ui', () => {
  const toasts = ref<Toast[]>([])

  function addToast(message: string, type: ToastType = 'success', duration = 4000) {
    const id = _nextId++
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return { toasts, addToast, removeToast }
})
