import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/lib/axios'
import axios from 'axios'

export type UserRole = 'admin' | 'boss' | 'manager' | 'accountant' | 'member'

interface User {
  id: number
  name: string
  email: string
  role: UserRole
  job_title: string | null
  avatar_url: string | null
  // 後端計算的能力旗標（核發權含「無會計→老闆兼任」規則，不可用 role 推算）
  can_review_fee?: boolean
  can_disburse_fee?: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isBoss = computed(() => user.value?.role === 'boss')
  // 專案經理：管自己的專案 + 做任務 + 提費用；不參與費用審核
  const isManager = computed(() => user.value?.role === 'manager')
  const isAccountant = computed(() => user.value?.role === 'accountant')
  const isMember = computed(() => user.value?.role === 'member')

  // 費用流程不含 admin：一階審核 = 老闆；二階核發 = 會計（無會計的公司由老闆兼任，後端計算）
  // 旗標以後端為準，role 推算僅為舊回應的 fallback
  const canReviewFee   = computed(() => user.value?.can_review_fee ?? user.value?.role === 'boss')
  const canDisburseFee = computed(() => user.value?.can_disburse_fee ?? user.value?.role === 'accountant')
  // 參與費用流程（一階或二階）→ 費用審核頁入口
  const canAccessFees  = computed(() => canReviewFee.value || canDisburseFee.value)
  // 管理權（公司 / 專案 / 成員）仍含 admin
  const canManage      = computed(() => ['admin', 'boss'].includes(user.value?.role ?? ''))
  // 可建立專案：admin / 老闆（全公司）、專案經理（自己的）
  const canCreateProjects = computed(() => canManage.value || isManager.value)

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

  // 職稱由管理者指派，本人不可自改（profile 僅能改名稱）
  async function updateProfile(name: string) {
    const { data } = await api.put('/profile', { name })
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
    isAdmin, isBoss, isManager, isAccountant, isMember,
    canReviewFee, canDisburseFee, canAccessFees, canManage, canManageMembers, canCreateProjects,
    fetchUser, login, logout, updateProfile, updatePassword, updateAvatar,
  }
})
