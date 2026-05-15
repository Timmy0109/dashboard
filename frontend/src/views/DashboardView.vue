<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-900">首頁總覽</h2>
      <p class="text-sm text-gray-500 mt-0.5">歡迎回來，{{ auth.user?.name }}，以下是今日專案概況</p>
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="flex items-center justify-center py-20">
      <div class="text-gray-400 text-sm">載入中...</div>
    </div>

    <template v-else-if="store.stats">
      <!-- Stats Cards -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div
          v-for="card in statCards"
          :key="card.label"
          class="bg-white rounded-xl p-4 shadow-sm border border-gray-100"
        >
          <div class="flex items-center gap-2 mb-2">
            <span class="text-lg">{{ card.icon }}</span>
            <span class="text-xs text-gray-500">{{ card.label }}</span>
          </div>
          <div class="text-2xl font-bold" :class="card.color">{{ card.value }}</div>
        </div>
      </div>

      <!-- Project Progress + Project List -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Overall progress card -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">專案整體進度</h3>
          <div class="flex items-center justify-center">
            <div class="relative w-32 h-32">
              <svg class="w-32 h-32 -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="2.5" />
                <circle
                  cx="18" cy="18" r="15.9"
                  fill="none"
                  stroke="#3b82f6"
                  stroke-width="2.5"
                  :stroke-dasharray="`${overallProgress} ${100 - overallProgress}`"
                  stroke-linecap="round"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-bold text-gray-900">{{ overallProgress }}%</span>
                <span class="text-xs text-gray-400">整體進度</span>
              </div>
            </div>
          </div>
          <div class="mt-4 space-y-2">
            <div class="flex justify-between text-xs text-gray-500">
              <span>已完成專案</span>
              <span class="font-medium text-green-600">{{ store.stats.completed_projects }}</span>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
              <span>進行中專案</span>
              <span class="font-medium text-blue-600">{{ store.stats.active_projects }}</span>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
              <span>逾期任務</span>
              <span class="font-medium" :class="store.stats.overdue_tasks > 0 ? 'text-red-600' : 'text-gray-400'">
                {{ store.stats.overdue_tasks }}
              </span>
            </div>
          </div>
        </div>

        <!-- Project List -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">專案列表</h3>
            <RouterLink to="/projects" class="text-xs text-blue-600 hover:text-blue-700">查看全部</RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <div
              v-for="project in store.projects.slice(0, 8)"
              :key="project.id"
              class="px-5 py-3 hover:bg-gray-50 transition-colors cursor-pointer"
              @click="router.push(`/projects/${project.id}`)"
            >
              <div class="flex items-center gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-sm font-medium text-gray-800 truncate">{{ project.name }}</span>
                    <span
                      v-if="project.status"
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium shrink-0"
                      :style="{ backgroundColor: project.status.color + '20', color: project.status.color }"
                    >
                      {{ project.status.icon }} {{ project.status.name }}
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                      <div
                        class="h-1.5 rounded-full transition-all"
                        :class="project.progress_percent >= 100 ? 'bg-green-500' : 'bg-blue-500'"
                        :style="{ width: project.progress_percent + '%' }"
                      />
                    </div>
                    <span class="text-xs text-gray-400 shrink-0">{{ project.progress_percent }}%</span>
                  </div>
                </div>
                <div class="shrink-0 text-right">
                  <span
                    v-if="project.priority"
                    class="text-xs px-1.5 py-0.5 rounded font-medium"
                    :style="{ backgroundColor: project.priority.color + '20', color: project.priority.color }"
                  >
                    {{ project.priority.name }}
                  </span>
                </div>
              </div>
            </div>
            <div v-if="store.projects.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
              目前沒有專案
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'

const auth = useAuthStore()
const store = useDashboardStore()
const router = useRouter()

const statCards = computed(() => {
  if (!store.stats) return []
  return [
    { label: '全部專案', icon: '📁', value: store.stats.total_projects, color: 'text-gray-900' },
    { label: '進行中', icon: '🔵', value: store.stats.active_projects, color: 'text-blue-600' },
    { label: '已完成', icon: '🟢', value: store.stats.completed_projects, color: 'text-green-600' },
    { label: '全部任務', icon: '📋', value: store.stats.total_tasks, color: 'text-gray-900' },
    { label: '已完成任務', icon: '✅', value: store.stats.completed_tasks, color: 'text-green-600' },
    { label: '逾期任務', icon: '🔴', value: store.stats.overdue_tasks, color: store.stats.overdue_tasks > 0 ? 'text-red-600' : 'text-gray-400' },
  ]
})

const overallProgress = computed(() => {
  if (!store.projects.length) return 0
  const avg = store.projects.reduce((sum, p) => sum + p.progress_percent, 0) / store.projects.length
  return Math.round(avg)
})

onMounted(() => store.fetch())
</script>
