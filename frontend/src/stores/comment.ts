import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'
import { getEcho } from '@/lib/echo'
import { useToastStore } from '@/stores/toast'
import type { Comment } from '@/types/comment'

let _optimisticSeq = -1
function nextOptimisticId(): number {
  return _optimisticSeq--
}

export const useCommentStore = defineStore('comment', () => {
  const byTask = ref<Record<number, Comment[]>>({})
  const loading = ref(false)

  const activeChannels = new Set<number>()

  async function fetchForTask(projectId: number, taskId: number) {
    loading.value = true
    try {
      const res = await api.get<Comment[]>(
        `/projects/${projectId}/tasks/${taskId}/comments`,
      )
      byTask.value[taskId] = res.data
    } finally {
      loading.value = false
    }
  }

  async function create(projectId: number, taskId: number, body: string): Promise<Comment> {
    if (!byTask.value[taskId]) byTask.value[taskId] = []
    const tempId = nextOptimisticId()
    const optimistic: Comment = {
      id: tempId,
      task_id: taskId,
      parent_id: null,
      user_id: 0,
      body,
      user: { id: 0, name: '...' },
      created_at: new Date().toISOString(),
      _optimistic: true,
    }
    byTask.value[taskId].push(optimistic)

    try {
      const res = await api.post<Comment>(
        `/projects/${projectId}/tasks/${taskId}/comments`,
        { body },
      )
      const list = byTask.value[taskId]
      const idx = list.findIndex(c => c.id === tempId)
      if (idx !== -1) list[idx] = res.data
      return res.data
    } catch (e) {
      // rollback
      byTask.value[taskId] = byTask.value[taskId].filter(c => c.id !== tempId)
      useToastStore().error('留言送出失敗')
      throw e
    }
  }

  async function reply(
    projectId: number,
    taskId: number,
    parentCommentId: number,
    body: string,
  ): Promise<Comment> {
    if (!byTask.value[taskId]) byTask.value[taskId] = []
    const tempId = nextOptimisticId()
    const optimistic: Comment = {
      id: tempId,
      task_id: taskId,
      parent_id: parentCommentId,
      user_id: 0,
      body,
      user: { id: 0, name: '...' },
      created_at: new Date().toISOString(),
      _optimistic: true,
    }

    const parent = byTask.value[taskId].find(c => c.id === parentCommentId)
    if (parent) {
      if (!parent.replies) parent.replies = []
      parent.replies.push(optimistic)
    }

    try {
      const res = await api.post<Comment>(
        `/projects/${projectId}/tasks/${taskId}/comments/${parentCommentId}/reply`,
        { body },
      )
      if (parent && parent.replies) {
        const idx = parent.replies.findIndex(c => c.id === tempId)
        if (idx !== -1) parent.replies[idx] = res.data
      }
      return res.data
    } catch (e) {
      if (parent && parent.replies) {
        parent.replies = parent.replies.filter(c => c.id !== tempId)
      }
      useToastStore().error('回覆送出失敗')
      throw e
    }
  }

  async function deleteComment(
    projectId: number,
    taskId: number,
    commentId: number,
  ) {
    await api.delete(
      `/projects/${projectId}/tasks/${taskId}/comments/${commentId}`,
    )
    const list = byTask.value[taskId]
    if (!list) return
    // try top-level
    const idx = list.findIndex(c => c.id === commentId)
    if (idx !== -1) {
      list.splice(idx, 1)
      return
    }
    // try nested
    for (const c of list) {
      if (!c.replies) continue
      const ridx = c.replies.findIndex(r => r.id === commentId)
      if (ridx !== -1) {
        c.replies.splice(ridx, 1)
        return
      }
    }
  }

  function listenForRealtime(taskId: number) {
    const echo = getEcho()
    if (!echo) return
    if (activeChannels.has(taskId)) return
    activeChannels.add(taskId)
    const ch = echo.private(`task.${taskId}`)
    ch.listen('.CommentCreated', (payload: Comment) => {
      if (!byTask.value[taskId]) byTask.value[taskId] = []
      // dedupe by id
      if (byTask.value[taskId].some(c => c.id === payload.id)) return
      byTask.value[taskId].push(payload)
    })
    ch.listen('.CommentReplied', (payload: Comment) => {
      const list = byTask.value[taskId]
      if (!list || payload.parent_id == null) return
      const parent = list.find(c => c.id === payload.parent_id)
      if (!parent) return
      if (!parent.replies) parent.replies = []
      if (parent.replies.some(r => r.id === payload.id)) return
      parent.replies.push(payload)
    })
  }

  function stopListening(taskId: number) {
    if (!activeChannels.has(taskId)) return
    const echo = getEcho()
    if (echo) {
      try {
        echo.leave(`task.${taskId}`)
      } catch (e) {
        console.warn('[comment] leave failed', e)
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
    create,
    reply,
    delete: deleteComment,
    listenForRealtime,
    stopListening,
    reset,
  }
})
