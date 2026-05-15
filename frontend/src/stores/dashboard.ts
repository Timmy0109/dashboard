import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

interface Stats {
  total_projects: number
  active_projects: number
  completed_projects: number
  total_tasks: number
  completed_tasks: number
  overdue_tasks: number
}

interface Project {
  id: number
  project_no: string | null
  name: string
  note: string | null
  start_date: string
  due_date: string | null
  progress_percent: number
  is_completed: boolean
  category: { id: number; name: string; color: string } | null
  priority: { id: number; name: string; color: string } | null
  status: { id: number; name: string; icon: string; color: string } | null
  owner: { id: number; name: string } | null
}

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref<Stats | null>(null)
  const projects = ref<Project[]>([])
  const loading = ref(false)

  async function fetch() {
    loading.value = true
    try {
      const res = await api.get('/dashboard')
      stats.value = res.data.stats
      projects.value = res.data.projects
    } finally {
      loading.value = false
    }
  }

  return { stats, projects, loading, fetch }
})
