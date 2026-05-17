import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

export interface Company {
  id: number
  name: string
  status: 'active' | 'suspended'
  invite_code: string | null
  managers_count: number
  members_count: number
  created_at: string
  deleted_at: string | null
}

export interface CompanyFeature {
  key: string
  name: string
  description: string
  category: 'member' | 'project' | 'report' | 'system'
  is_default: boolean
  enabled: boolean
  enabled_at: string | null
}

export const useCompanyStore = defineStore('company', () => {
  const list = ref<Company[]>([])
  const loading = ref(false)

  async function fetchList(withTrashed = false) {
    loading.value = true
    try {
      const { data } = await api.get('/admin/companies', { params: withTrashed ? { with_trashed: 1 } : {} })
      list.value = data
    } finally {
      loading.value = false
    }
  }

  async function create(name: string): Promise<Company> {
    const { data } = await api.post('/admin/companies', { name })
    list.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<Pick<Company, 'name' | 'status'>>) {
    const { data } = await api.put(`/admin/companies/${id}`, payload)
    const idx = list.value.findIndex(c => c.id === id)
    if (idx !== -1) list.value[idx] = { ...list.value[idx]!, ...data }
  }

  async function deleteCompany(id: number) {
    await api.delete(`/admin/companies/${id}`)
    const idx = list.value.findIndex(c => c.id === id)
    if (idx !== -1) list.value[idx]!.deleted_at = new Date().toISOString().slice(0, 10)
  }

  async function restoreCompany(id: number) {
    await api.post(`/admin/companies/${id}/restore`)
    const idx = list.value.findIndex(c => c.id === id)
    if (idx !== -1) list.value[idx]!.deleted_at = null
  }

  async function fetchFeatures(id: number): Promise<CompanyFeature[]> {
    const { data } = await api.get(`/admin/companies/${id}/features`)
    return data
  }

  async function toggleFeature(companyId: number, key: string, enabled: boolean) {
    await api.put(`/admin/companies/${companyId}/features/${key}`, { enabled })
  }

  async function regenerateInviteCode(id: number): Promise<string> {
    const { data } = await api.post(`/admin/companies/${id}/invite-code`)
    const company = list.value.find(c => c.id === id)
    if (company) company.invite_code = data.invite_code
    return data.invite_code
  }

  return { list, loading, fetchList, create, update, deleteCompany, restoreCompany, fetchFeatures, toggleFeature, regenerateInviteCode }
})
