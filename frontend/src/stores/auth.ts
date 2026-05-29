import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/lib/axios'
import axios from 'axios'

interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'manager' | 'member'
  avatar_url: string | null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isManager = computed(() => user.value?.role === 'manager')
  const canManageMembers = computed(() => user.value?.role === 'admin' || user.value?.role === 'manager')

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

  async function updateProfile(name: string) {
    const { data } = await api.put('/profile', { name })
    if (user.value) {
      user.value.name = data.name
      user.value.avatar_url = data.avatar_url
    }
  }

  async function updatePassword(current_password: string, password: string, password_confirmation: string) {
    await api.put('/profile/password', { current_password, password, password_confirmation })
  }

  async function updateAvatar(file: File) {
    const form = new FormData()
    form.append('avatar', file)
    const { data } = await api.post('/profile/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (user.value) user.value.avatar_url = data.avatar_url
  }

  return { user, loading, isLoggedIn, isAdmin, isManager, canManageMembers, fetchUser, login, logout, updateProfile, updatePassword, updateAvatar }
})
