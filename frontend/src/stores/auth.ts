import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/lib/axios'
import axios from 'axios'

interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'manager' | 'member'
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isManager = computed(() => user.value?.role === 'admin' || user.value?.role === 'manager')

  async function fetchUser() {
    try {
      const res = await api.get('/me')
      user.value = res.data
    } catch {
      user.value = null
    }
  }

  async function login(email: string, password: string) {
    loading.value = true
    try {
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
      const res = await api.post('/login', { email, password })
      user.value = res.data.user
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await api.post('/logout')
    user.value = null
  }

  return { user, loading, isLoggedIn, isAdmin, isManager, fetchUser, login, logout }
})
