import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

interface Toast {
  id: number
  message: string
  type: ToastType
}

const toasts = ref<Toast[]>([])
let nextId = 0

export function useToast() {
  function add(message: string, type: ToastType = 'success', duration = 3000) {
    const id = ++nextId
    toasts.value.push({ id, message, type })
    setTimeout(() => remove(id), duration)
  }

  function remove(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  const success = (msg: string) => add(msg, 'success')
  const error = (msg: string) => add(msg, 'error', 4000)
  const info = (msg: string) => add(msg, 'info')
  const warning = (msg: string) => add(msg, 'warning')

  return { toasts, success, error, info, warning, remove }
}
