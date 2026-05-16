import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

export interface TodoTask {
  id: number
  name: string
  note: string | null
  project_id: number
  project_name: string
  start_date: string
  end_date: string
  is_overdue: boolean
  progress: number
  is_completed: boolean
  assignee: { id: number; name: string } | null
  status: { id: number; name: string; color: string; icon: string } | null
  priority: { id: number; name: string; color: string } | null
}

export const useTodoStore = defineStore('todo', () => {
  const tasks = ref<TodoTask[]>([])
  const loading = ref(false)

  async function fetch() {
    loading.value = true
    try {
      const res = await api.get('/todo')
      tasks.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function deleteTask(projectId: number, taskId: number) {
    await api.delete(`/projects/${projectId}/tasks/${taskId}`)
    tasks.value = tasks.value.filter(t => t.id !== taskId)
  }

  return { tasks, loading, fetch, deleteTask }
})
