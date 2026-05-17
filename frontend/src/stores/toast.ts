import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastColor = 'success' | 'error' | 'info' | 'warning'

export interface ToastItem {
  id: number
  message: string
  color: ToastColor
  timeout: number
}

let _seq = 0

export const useToastStore = defineStore('toast', () => {
  const queue   = ref<ToastItem[]>([])
  const current = ref<ToastItem | null>(null)
  const visible = ref(false)

  function enqueue(msg: string, color: ToastColor = 'success', timeout = 3000) {
    queue.value.push({ id: ++_seq, message: msg, color, timeout })
    if (!visible.value) _next()
  }

  function _next() {
    const item = queue.value.shift()
    if (!item) { current.value = null; return }
    current.value = item
    visible.value = true
  }

  function dismiss() {
    visible.value = false
    setTimeout(_next, 300)
  }

  const success = (msg: string) => enqueue(msg, 'success')
  const error   = (msg: string) => enqueue(msg, 'error')
  const info    = (msg: string) => enqueue(msg, 'info')
  const warning = (msg: string) => enqueue(msg, 'warning')

  return { current, visible, dismiss, success, error, info, warning }
})
