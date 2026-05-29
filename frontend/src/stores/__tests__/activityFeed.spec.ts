import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/lib/axios', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/lib/echo', () => ({
  getEcho: () => null,
}))

import api from '@/lib/axios'
import { useActivityFeedStore } from '@/stores/activityFeed'

const mockApi = api as unknown as { get: ReturnType<typeof vi.fn> }

describe('activityFeed store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetch with scope=company populates items + passes correct scope param', async () => {
    mockApi.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 1,
            event: 'created',
            task_id: 1,
            task_name: 'T',
            project_id: 1,
            project_name: 'P',
            actor: null,
            payload: null,
            created_at: '2026-01-01T00:00:00Z',
          },
        ],
        next_cursor: null,
      },
    })
    const store = useActivityFeedStore()
    await store.fetch('company')
    expect(store.items).toHaveLength(1)
    expect(store.loaded).toBe(true)
    expect(mockApi.get).toHaveBeenCalledWith('/activity', {
      params: { scope: 'company' },
    })
  })

  it('fetch with scope=project passes correct query param', async () => {
    mockApi.get.mockResolvedValueOnce({
      data: { data: [], next_cursor: null },
    })
    const store = useActivityFeedStore()
    await store.fetch({ project: 42 })
    expect(mockApi.get).toHaveBeenCalledWith('/activity', {
      params: { scope: 'project:42' },
    })
  })
})
