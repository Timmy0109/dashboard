import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'
import { getEcho } from '@/lib/echo'
import type {
  Notification,
  PaginatedNotifications,
} from '@/types/notification'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const loaded = ref(false)
  const nextCursor = ref<string | null>(null)
  const loading = ref(false)

  let currentUserId: number | null = null

  async function fetch(opts?: { unreadOnly?: boolean }) {
    loading.value = true
    try {
      const params: Record<string, string> = {}
      if (opts?.unreadOnly) params.filter = 'unread'
      const res = await api.get<PaginatedNotifications>('/notifications', { params })
      notifications.value = res.data.data
      unreadCount.value = res.data.unread_count
      nextCursor.value = res.data.next_cursor
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function fetchMore() {
    if (!nextCursor.value) return
    loading.value = true
    try {
      const res = await api.get<PaginatedNotifications>('/notifications', {
        params: { cursor: nextCursor.value },
      })
      notifications.value.push(...res.data.data)
      nextCursor.value = res.data.next_cursor
      // unread_count is authoritative from server
      unreadCount.value = res.data.unread_count
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(id: number) {
    await api.post(`/notifications/${id}/read`)
    const item = notifications.value.find(n => n.id === id)
    if (item && !item.read_at) {
      item.read_at = new Date().toISOString()
      if (unreadCount.value > 0) unreadCount.value--
    }
  }

  async function markAllRead() {
    await api.post('/notifications/mark-all-read')
    const now = new Date().toISOString()
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = now
    })
    unreadCount.value = 0
  }

  function listenForRealtime(userId: number) {
    const echo = getEcho()
    if (!echo) return
    if (currentUserId === userId) return
    if (currentUserId != null) stopListening()
    currentUserId = userId
    const ch = echo.private(`notifications.${userId}`)
    ch.listen('.NotificationCreated', (payload: Notification) => {
      notifications.value.unshift(payload)
      if (!payload.read_at) unreadCount.value++
    })
    ch.listen('.NotificationRead', (payload: { id: number; read_at: string }) => {
      const item = notifications.value.find(n => n.id === payload.id)
      if (item && !item.read_at) {
        item.read_at = payload.read_at
        if (unreadCount.value > 0) unreadCount.value--
      }
    })
  }

  function stopListening() {
    if (currentUserId == null) return
    const echo = getEcho()
    if (echo) {
      try {
        echo.leave(`notifications.${currentUserId}`)
      } catch (e) {
        console.warn('[notification] leave failed', e)
      }
    }
    currentUserId = null
  }

  function reset() {
    stopListening()
    notifications.value = []
    unreadCount.value = 0
    loaded.value = false
    nextCursor.value = null
    loading.value = false
  }

  return {
    notifications,
    unreadCount,
    loaded,
    nextCursor,
    loading,
    fetch,
    fetchMore,
    markAsRead,
    markAllRead,
    listenForRealtime,
    stopListening,
    reset,
  }
})
