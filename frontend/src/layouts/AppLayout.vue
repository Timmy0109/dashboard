<template>
  <ToastContainer />
  <div class="flex h-screen bg-gray-100 overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-52 flex-shrink-0 bg-gray-900 text-white flex flex-col">
      <!-- Logo -->
      <div class="px-4 py-5 border-b border-gray-700">
        <h1 class="text-sm font-semibold text-white">專案管理系統</h1>
        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ auth.user?.name }}</p>
      </div>

      <!-- Nav -->
      <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors"
          :class="isActive(item.to)
            ? 'bg-blue-600 text-white'
            : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <span class="material-icons text-lg leading-none">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <!-- Bottom -->
      <div class="px-2 py-3 border-t border-gray-700">
        <button
          @click="handleLogout"
          class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
        >
          <span class="material-icons text-lg leading-none">logout</span>
          <span>登出</span>
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Top bar -->
      <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between flex-shrink-0">
        <h2 class="text-base font-semibold text-gray-800">{{ currentPageTitle }}</h2>
        <div class="flex items-center gap-2 text-sm text-gray-500">
          <span class="inline-flex items-center gap-1.5">
            <span class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-medium">
              {{ auth.user?.name?.charAt(0) }}
            </span>
            {{ auth.user?.name }}
          </span>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ToastContainer from '@/components/ToastContainer.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const navItems = computed(() => {
  const items = [
    { to: '/', icon: 'dashboard', label: '首頁總覽' },
    { to: '/projects', icon: 'folder', label: '專案管理' },
    { to: '/todo', icon: 'check_circle', label: 'Todo List' },
    { to: '/stats', icon: 'bar_chart', label: '統計分析' },
  ]
  if (auth.isAdmin) {
    items.push({ to: '/settings', icon: 'settings', label: '設定管理' })
    items.push({ to: '/system', icon: 'shield', label: '系統管理' })
  }
  return items
})

const pageTitles: Record<string, string> = {
  dashboard: '首頁總覽',
  projects: '專案管理',
  'project-detail': '專案詳情',
  todo: 'Todo List',
  stats: '統計分析',
  settings: '設定管理',
  system: '系統管理',
}

const currentPageTitle = computed(() => pageTitles[route.name as string] ?? '')

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
