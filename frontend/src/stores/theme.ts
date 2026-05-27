import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useTheme } from 'vuetify'

const STORAGE_KEY = 'app.theme'
type ThemeName = 'light' | 'dark'

export const useThemeStore = defineStore('theme', () => {
  const current = ref<ThemeName>('light')

  /** Apply stored preference to Vuetify on app boot. */
  function init(vuetifyTheme: ReturnType<typeof useTheme>) {
    const saved = (localStorage.getItem(STORAGE_KEY) as ThemeName | null) ?? 'light'
    current.value = saved === 'dark' ? 'dark' : 'light'
    vuetifyTheme.global.name.value = current.value
  }

  function toggle(vuetifyTheme: ReturnType<typeof useTheme>) {
    current.value = current.value === 'light' ? 'dark' : 'light'
    vuetifyTheme.global.name.value = current.value
    localStorage.setItem(STORAGE_KEY, current.value)
  }

  function set(name: ThemeName, vuetifyTheme: ReturnType<typeof useTheme>) {
    current.value = name
    vuetifyTheme.global.name.value = name
    localStorage.setItem(STORAGE_KEY, name)
  }

  return { current, init, toggle, set }
})
