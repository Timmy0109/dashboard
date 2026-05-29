import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'
import { getEcho } from '@/lib/echo'
import type {
  Attachment,
  ProjectAttachment,
  PaginatedAttachments,
} from '@/types/attachment'

export const useAttachmentStore = defineStore('attachment', () => {
  const byTask = ref<Record<number, Attachment[]>>({})
  const byProject = ref<Record<number, ProjectAttachment[]>>({})
  const nextCursorByProject = ref<Record<number, string | null>>({})
  const loading = ref(false)

  const activeChannels = new Set<number>()

  async function fetchForTask(projectId: number, taskId: number) {
    loading.value = true
    try {
      const res = await api.get<Attachment[]>(
        `/projects/${projectId}/tasks/${taskId}/attachments`,
      )
      byTask.value[taskId] = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchForProject(
    projectId: number,
    opts?: { cursor?: string },
  ) {
    loading.value = true
    try {
      const params: Record<string, string> = {}
      if (opts?.cursor) params.cursor = opts.cursor
      const res = await api.get<PaginatedAttachments>(
        `/projects/${projectId}/attachments`,
        { params },
      )
      if (opts?.cursor && byProject.value[projectId]) {
        byProject.value[projectId].push(...res.data.data)
      } else {
        byProject.value[projectId] = res.data.data
      }
      nextCursorByProject.value[projectId] = res.data.next_cursor
    } finally {
      loading.value = false
    }
  }

  async function upload(
    projectId: number,
    taskId: number,
    file: File,
    onProgress?: (pct: number) => void,
  ): Promise<Attachment> {
    const form = new FormData()
    form.append('file', file)
    const res = await api.post<Attachment>(
      `/projects/${projectId}/tasks/${taskId}/attachments`,
      form,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (evt) => {
          if (onProgress && evt.total) {
            onProgress(Math.round((evt.loaded / evt.total) * 100))
          }
        },
      },
    )
    if (!byTask.value[taskId]) byTask.value[taskId] = []
    if (!byTask.value[taskId].some(a => a.id === res.data.id)) {
      byTask.value[taskId].push(res.data)
    }
    return res.data
  }

  async function deleteAttachment(
    projectId: number,
    taskId: number,
    attachmentId: number,
  ) {
    await api.delete(
      `/projects/${projectId}/tasks/${taskId}/attachments/${attachmentId}`,
    )
    if (byTask.value[taskId]) {
      byTask.value[taskId] = byTask.value[taskId].filter(a => a.id !== attachmentId)
    }
    if (byProject.value[projectId]) {
      byProject.value[projectId] = byProject.value[projectId].filter(a => a.id !== attachmentId)
    }
  }

  function listenForRealtime(taskId: number) {
    const echo = getEcho()
    if (!echo) return
    if (activeChannels.has(taskId)) return
    activeChannels.add(taskId)
    const ch = echo.private(`task.${taskId}`)
    ch.listen('.AttachmentUploaded', (payload: Attachment) => {
      if (!byTask.value[taskId]) byTask.value[taskId] = []
      if (byTask.value[taskId].some(a => a.id === payload.id)) return
      byTask.value[taskId].push(payload)
    })
  }

  function stopListening(taskId: number) {
    if (!activeChannels.has(taskId)) return
    const echo = getEcho()
    if (echo) {
      try {
        echo.leave(`task.${taskId}`)
      } catch (e) {
        console.warn('[attachment] leave failed', e)
      }
    }
    activeChannels.delete(taskId)
  }

  function reset() {
    for (const id of Array.from(activeChannels)) stopListening(id)
    byTask.value = {}
    byProject.value = {}
    nextCursorByProject.value = {}
    loading.value = false
  }

  return {
    byTask,
    byProject,
    nextCursorByProject,
    loading,
    fetchForTask,
    fetchForProject,
    upload,
    delete: deleteAttachment,
    listenForRealtime,
    stopListening,
    reset,
  }
})
