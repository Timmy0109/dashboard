import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/axios'

export const useFeatureStore = defineStore('feature', () => {
  const features = ref<string[]>([])
  const loaded = ref(false)

  async function fetch() {
    const { data } = await api.get('/lookups/features')
    features.value = data
    loaded.value = true
  }

  function has(key: string): boolean {
    return features.value.includes(key)
  }

  function reset() {
    features.value = []
    loaded.value = false
  }

  return { features, loaded, fetch, has, reset }
})
