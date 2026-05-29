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

type ChannelListener = (payload: unknown) => void
const listeners: Record<string, ChannelListener> = {}

vi.mock('@/lib/echo', () => ({
  getEcho: () => ({
    private: (_name: string) => ({
      listen: (event: string, cb: ChannelListener) => {
        listeners[event] = cb
      },
    }),
    leave: vi.fn(),
  }),
}))

import api from '@/lib/axios'
import { useNotificationStore } from '@/stores/notification'
import type { Notification } from '@/types/notification'

const mockApi = api as unknown as {
  get: ReturnType<typeof vi.fn>
  post: ReturnType<typeof vi.fn>
}

function makeNotif(over: Partial<Notification> = {}): Notification {
  return {
    id: 1,
    user_id: 1,
    type: 'task_assigned',
    payload: {
      task_id: 10,
      task_name: 'T',
      project_id: 1,
      project_name: 'P',
      actor_name: 'A',
    },
    read_at: null,
    created_at: '2026-01-01T00:00:00Z',
    ...over,
  } as Notification
}

describe('notification store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    for (const k of Object.keys(listeners)) delete listeners[k]
  })

  it('fetch populates notifications + unreadCount', async () => {
    mockApi.get.mockResolvedValueOnce({
      data: { data: [makeNotif({ id: 1 }), makeNotif({ id: 2 })], next_cursor: null, unread_count: 2 },
    })
    const store = useNotificationStore()
    await store.fetch()
    expect(store.notifications).toHaveLength(2)
    expect(store.unreadCount).toBe(2)
    expect(store.loaded).toBe(true)
  })

  it('markAsRead updates read_at locally + clears unread badge', async () => {
    mockApi.get.mockResolvedValueOnce({
      data: { data: [makeNotif({ id: 1 }), makeNotif({ id: 2 })], next_cursor: null, unread_count: 2 },
    })
    mockApi.post.mockResolvedValueOnce({ data: {} })
    const store = useNotificationStore()
    await store.fetch()
    await store.markAsRead(1)
    const item = store.notifications.find(n => n.id === 1)!
    expect(item.read_at).not.toBeNull()
    expect(store.unreadCount).toBe(1)
  })

  it('listenForRealtime: NotificationCreated handler prepends to state', async () => {
    mockApi.get.mockResolvedValueOnce({
      data: { data: [], next_cursor: null, unread_count: 0 },
    })
    const store = useNotificationStore()
    await store.fetch()
    store.listenForRealtime(42)
    expect(listeners['.NotificationCreated']).toBeDefined()
    const incoming = makeNotif({ id: 99 })
    listeners['.NotificationCreated']!(incoming)
    expect(store.notifications[0]!.id).toBe(99)
    expect(store.unreadCount).toBe(1)
  })
})
