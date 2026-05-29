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
import { useCommentStore } from '@/stores/comment'
import type { Comment } from '@/types/comment'

const mockApi = api as unknown as {
  get: ReturnType<typeof vi.fn>
  post: ReturnType<typeof vi.fn>
}

function parentComment(): Comment {
  return {
    id: 1,
    task_id: 10,
    parent_id: null,
    user_id: 1,
    body: 'parent',
    user: { id: 1, name: 'U' },
    created_at: '2026-01-01T00:00:00Z',
    replies: [],
  }
}

describe('comment store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('reply: optimistic add then server confirms', async () => {
    mockApi.get.mockResolvedValueOnce({ data: [parentComment()] })
    const store = useCommentStore()
    await store.fetchForTask(5, 10)

    const serverReply: Comment = {
      id: 100,
      task_id: 10,
      parent_id: 1,
      user_id: 2,
      body: 'reply!',
      user: { id: 2, name: 'B' },
      created_at: '2026-01-02T00:00:00Z',
    }
    let resolveReq: (v: { data: Comment }) => void = () => {}
    mockApi.post.mockReturnValueOnce(new Promise(r => { resolveReq = r }))

    const p = store.reply(5, 10, 1, 'reply!')
    // optimistic should appear immediately
    const parent = store.byTask[10]![0]!
    expect(parent.replies).toHaveLength(1)
    expect(parent.replies![0]!._optimistic).toBe(true)

    resolveReq({ data: serverReply })
    await p

    expect(parent.replies).toHaveLength(1)
    expect(parent.replies![0]!.id).toBe(100)
    expect(parent.replies![0]!._optimistic).toBeUndefined()
  })

  it('reply: rolls back on server reject', async () => {
    mockApi.get.mockResolvedValueOnce({ data: [parentComment()] })
    const store = useCommentStore()
    await store.fetchForTask(5, 10)

    mockApi.post.mockRejectedValueOnce(new Error('500'))

    await expect(store.reply(5, 10, 1, 'oops')).rejects.toThrow('500')

    const parent = store.byTask[10]![0]!
    expect(parent.replies).toHaveLength(0)
  })
})
