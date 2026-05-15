import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

export interface Task {
  id: number
  project_id: number
  name: string
  note: string | null
  start_date: string
  end_date: string
  progress: number
  is_completed: boolean
  assignee: { id: number; name: string } | null
  status: { id: number; name: string; icon: string; color: string } | null
  priority: { id: number; name: string; color: string } | null
}

export interface ProjectDetail {
  id: number
  project_no: string | null
  name: string
  note: string | null
  start_date: string
  due_date: string | null
  completed_date: string | null
  progress_percent: number
  is_completed: boolean
  owner: { id: number; name: string } | null
  category: { id: number; name: string; color: string } | null
  priority: { id: number; name: string; color: string } | null
  status: { id: number; name: string; icon: string; color: string } | null
  tasks: Task[]
  members: { id: number; name: string; pivot: { role: string } }[]
}

export interface ProjectListItem {
  id: number
  project_no: string | null
  name: string
  start_date: string
  due_date: string | null
  progress_percent: number
  is_completed: boolean
  owner: { id: number; name: string } | null
  category: { id: number; name: string; color: string } | null
  priority: { id: number; name: string; color: string } | null
  status: { id: number; name: string; icon: string; color: string } | null
}

export const useProjectStore = defineStore('project', () => {
  const list = ref<ProjectListItem[]>([])
  const current = ref<ProjectDetail | null>(null)
  const listLoading = ref(false)
  const detailLoading = ref(false)

  async function fetchList() {
    listLoading.value = true
    try {
      const res = await api.get('/projects')
      list.value = res.data
    } finally {
      listLoading.value = false
    }
  }

  async function fetchDetail(id: number) {
    detailLoading.value = true
    try {
      const res = await api.get(`/projects/${id}`)
      current.value = res.data
    } finally {
      detailLoading.value = false
    }
  }

  async function createProject(data: Record<string, unknown>) {
    const res = await api.post('/projects', data)
    list.value.unshift(res.data)
    return res.data
  }

  async function updateProject(id: number, data: Record<string, unknown>) {
    const res = await api.put(`/projects/${id}`, data)
    if (current.value?.id === id) {
      Object.assign(current.value, res.data)
    }
    const idx = list.value.findIndex(p => p.id === id)
    if (idx !== -1) list.value[idx] = { ...list.value[idx], ...res.data }
    return res.data
  }

  async function deleteProject(id: number) {
    await api.delete(`/projects/${id}`)
    list.value = list.value.filter(p => p.id !== id)
    if (current.value?.id === id) current.value = null
  }

  async function createTask(projectId: number, data: Record<string, unknown>) {
    const res = await api.post(`/projects/${projectId}/tasks`, data)
    if (current.value?.id === projectId) {
      current.value.tasks.push(res.data)
      current.value.progress_percent = Math.round(
        current.value.tasks.reduce((s, t) => s + t.progress, 0) / current.value.tasks.length
      )
    }
    return res.data
  }

  async function updateTask(projectId: number, taskId: number, data: Record<string, unknown>) {
    const res = await api.put(`/projects/${projectId}/tasks/${taskId}`, data)
    if (current.value?.id === projectId) {
      const idx = current.value.tasks.findIndex(t => t.id === taskId)
      if (idx !== -1) current.value.tasks[idx] = res.data
      current.value.progress_percent = Math.round(
        current.value.tasks.reduce((s, t) => s + t.progress, 0) / current.value.tasks.length
      )
    }
    return res.data
  }

  async function deleteTask(projectId: number, taskId: number) {
    await api.delete(`/projects/${projectId}/tasks/${taskId}`)
    if (current.value?.id === projectId) {
      current.value.tasks = current.value.tasks.filter(t => t.id !== taskId)
      if (current.value.tasks.length > 0) {
        current.value.progress_percent = Math.round(
          current.value.tasks.reduce((s, t) => s + t.progress, 0) / current.value.tasks.length
        )
      } else {
        current.value.progress_percent = 0
      }
    }
  }

  return {
    list, current, listLoading, detailLoading,
    fetchList, fetchDetail,
    createProject, updateProject, deleteProject,
    createTask, updateTask, deleteTask,
  }
})
