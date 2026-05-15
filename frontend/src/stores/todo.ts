import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

interface TodoTask {
  id: number
  name: string
  project_id: number
  project_name: string
  end_date: string
  is_overdue: boolean
  progress: number
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

  return { tasks, loading, fetch }
})
