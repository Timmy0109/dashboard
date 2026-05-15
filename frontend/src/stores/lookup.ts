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
    if (loaded.value) return
    const [cat, pri, sta, usr] = await Promise.all([
      api.get('/lookups/categories'),
      api.get('/lookups/priorities'),
      api.get('/lookups/statuses'),
      api.get('/lookups/users'),
    ])
    categories.value = cat.data
    priorities.value = pri.data
    statuses.value = sta.data
    users.value = usr.data
    loaded.value = true
  }

  return { categories, priorities, statuses, users, loaded, fetch }
})
