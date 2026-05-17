import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

export interface Category { id: number; name: string; color: string }
export interface Priority { id: number; name: string; color: string; sort_order: number }
export interface StatusRule { id: number; name: string; icon: string; color: string; sort_order: number }
export interface UserOption { id: number; name: string; role: string }

export const useLookupStore = defineStore('lookup', () => {
  const categories = ref<Category[]>([])
  const priorities = ref<Priority[]>([])
  const statuses = ref<StatusRule[]>([])
  const users = ref<UserOption[]>([])
  const loaded = ref(false)

  async function fetch() {
    // Users are company-scoped — always fetch fresh.
    // Categories/priorities/statuses are global and safe to cache.
    const fetchUsers = api.get('/lookups/users').then(r => { users.value = r.data })

    if (loaded.value) {
      await fetchUsers
      return
    }

    const [cat, pri, sta] = await Promise.all([
      api.get('/lookups/categories'),
      api.get('/lookups/priorities'),
      api.get('/lookups/statuses'),
    ])
    categories.value = cat.data
    priorities.value = pri.data
    statuses.value = sta.data
    loaded.value = true
    await fetchUsers
  }

  return { categories, priorities, statuses, users, loaded, fetch }
})
