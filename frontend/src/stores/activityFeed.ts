import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'
import { getEcho } from '@/lib/echo'
import type {
  ActivityFeedItem,
  PaginatedActivity,
} from '@/types/notification'

export type ActivityScope = 'company' | { project: number }

function scopeToQuery(scope: ActivityScope): string {
  if (scope === 'company') return 'company'
  return `project:${scope.project}`
}

export const useActivityFeedStore = defineStore('activityFeed', () => {
  const items = ref<ActivityFeedItem[]>([])
  const loaded = ref(false)
  const nextCursor = ref<string | null>(null)
  const loading = ref(false)
  const lastScope = ref<ActivityScope | null>(null)

  let currentCompanyId: number | null = null

  async function fetch(scope: ActivityScope) {
    loading.value = true
    try {
      const res = await api.get<PaginatedActivity>('/activity', {
        params: { scope: scopeToQuery(scope) },
      })
      items.value = res.data.data
      nextCursor.value = res.data.next_cursor
      loaded.value = true
      lastScope.value = scope
    } finally {
      loading.value = false
    }
  }

  async function fetchMore() {
    if (!nextCursor.value || !lastScope.value) return
    loading.value = true
    try {
      const res = await api.get<PaginatedActivity>('/activity', {
        params: {
          scope: scopeToQuery(lastScope.value),
          cursor: nextCursor.value,
        },
      })
      items.value.push(...res.data.data)
      nextCursor.value = res.data.next_cursor
    } finally {
      loading.value = false
    }
  }

  function listenForRealtime(companyId: number) {
    const echo = getEcho()
    if (!echo) return
    if (currentCompanyId === companyId) return
    if (currentCompanyId != null) stopListening()
    currentCompanyId = companyId
    const ch = echo.private(`company.${companyId}.activity`)
    ch.listen('.ActivityRecorded', (payload: ActivityFeedItem) => {
      items.value.unshift(payload)
    })
  }

  function stopListening() {
    if (currentCompanyId == null) return
    const echo = getEcho()
    if (echo) {
      try {
        echo.leave(`company.${currentCompanyId}.activity`)
      } catch (e) {
        console.warn('[activityFeed] leave failed', e)
      }
    }
    currentCompanyId = null
  }

  function reset() {
    stopListening()
    items.value = []
    loaded.value = false
    nextCursor.value = null
    loading.value = false
    lastScope.value = null
  }

  return {
    items,
    loaded,
    nextCursor,
    loading,
    lastScope,
    fetch,
    fetchMore,
    listenForRealtime,
    stopListening,
    reset,
  }
})
