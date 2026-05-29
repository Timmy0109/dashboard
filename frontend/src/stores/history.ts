import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'
import { getEcho } from '@/lib/echo'
import type { HistoryEvent } from '@/types/history'

export const useHistoryStore = defineStore('history', () => {
  const byTask = ref<Record<number, HistoryEvent[]>>({})
  const loading = ref(false)

  const activeChannels = new Set<number>()

  async function fetchForTask(projectId: number, taskId: number) {
    loading.value = true
    try {
      // handoff terminology says "history"; actual route is /activities
      const res = await api.get<HistoryEvent[]>(
        `/projects/${projectId}/tasks/${taskId}/activities`,
      )
      byTask.value[taskId] = res.data
    } finally {
      loading.value = false
    }
  }

  function listenForRealtime(taskId: number) {
    const echo = getEcho()
    if (!echo) return
    if (activeChannels.has(taskId)) return
    activeChannels.add(taskId)
    const ch = echo.private(`task.${taskId}`)
    ch.listen('.HistoryEventRecorded', (payload: HistoryEvent) => {
      if (!byTask.value[taskId]) byTask.value[taskId] = []
      if (byTask.value[taskId].some(e => e.id === payload.id)) return
      byTask.value[taskId].unshift(payload)
    })
  }

  function stopListening(taskId: number) {
    if (!activeChannels.has(taskId)) return
    const echo = getEcho()
    if (echo) {
      try {
        echo.leave(`task.${taskId}`)
      } catch (e) {
        console.warn('[history] leave failed', e)
      }
    }
    activeChannels.delete(taskId)
  }

  function reset() {
    for (const id of Array.from(activeChannels)) stopListening(id)
    byTask.value = {}
    loading.value = false
  }

  return {
    byTask,
    loading,
    fetchForTask,
    listenForRealtime,
    stopListening,
    reset,
  }
})
