import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastColor = 'success' | 'error' | 'info' | 'warning'

export const useToastStore = defineStore('toast', () => {
  const visible = ref(false)
  const message = ref('')
  const color = ref<ToastColor>('success')

  function show(msg: string, c: ToastColor = 'success') {
    message.value = msg
    color.value = c
    visible.value = true
  }

  const success = (msg: string) => show(msg, 'success')
  const error   = (msg: string) => show(msg, 'error')
  const info    = (msg: string) => show(msg, 'info')
  const warning = (msg: string) => show(msg, 'warning')

  return { visible, message, color, success, error, info, warning }
})
