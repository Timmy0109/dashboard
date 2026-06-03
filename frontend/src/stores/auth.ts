import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/lib/axios'
import axios from 'axios'

export type UserRole = 'admin' | 'boss' | 'accountant' | 'member'

interface User {
  id: number
  name: string
  email: string
  role: UserRole
  job_title: string | null
  avatar_url: string | null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isBoss = computed(() => user.value?.role === 'boss')
  const isAccountant = computed(() => user.value?.role === 'accountant')
  const isMember = computed(() => user.value?.role === 'member')

  // 費用審核流程不含 admin：一階審核 = 會計 + 老闆；二階核發 = 老闆
  // 管理權（公司 / 專案 / 成員）仍含 admin
  const canReviewFee   = computed(() => ['boss', 'accountant'].includes(user.value?.role ?? ''))
  const canDisburseFee = computed(() => ['boss'].includes(user.value?.role ?? ''))
  const canManage      = computed(() => ['admin', 'boss'].includes(user.value?.role ?? ''))

  // 沿用舊名字當 alias，避免散布的 isManager check 全爆
  // canManageMembers 行為等同 canManage
  const canManageMembers = canManage

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

  async function updateProfile(name: string, jobTitle: string | null = null) {
    const { data } = await api.put('/profile', { name, job_title: jobTitle })
    if (user.value) {
      user.value.name = data.name
      user.value.job_title = data.job_title ?? null
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

  return {
    user, loading, isLoggedIn,
    isAdmin, isBoss, isAccountant, isMember,
    canReviewFee, canDisburseFee, canManage, canManageMembers,
    fetchUser, login, logout, updateProfile, updatePassword, updateAvatar,
  }
})
