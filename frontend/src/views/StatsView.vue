<template>
  <div>
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-900">統計分析</h2>
      <p class="text-sm text-gray-500 mt-0.5">專案與任務整體數據</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="text-gray-400 text-sm">載入中...</div>
    </div>

    <template v-else-if="data">
      <!-- Summary Cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div v-for="card in summaryCards" :key="card.label"
          class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
          <div class="text-2xl font-bold" :class="card.color">{{ card.value }}</div>
          <div class="text-xs text-gray-400 mt-1">{{ card.label }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Status Distribution -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">專案狀態分佈</h3>
          <div v-if="data.status_distribution.length === 0" class="py-8 text-center text-sm text-gray-400">無資料</div>
          <div v-else class="space-y-3">
            <div v-for="item in data.status_distribution" :key="item.status.name"
              class="flex items-center gap-3">
              <span class="text-sm w-4">{{ item.status.icon }}</span>
              <span class="text-sm text-gray-600 w-20">{{ item.status.name }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-2">
                <div
                  class="h-2 rounded-full transition-all"
                  :style="{ width: (item.count / totalProjects * 100) + '%', backgroundColor: item.status.color }"
                />
              </div>
              <span class="text-xs text-gray-500 w-8 text-right">{{ item.count }}</span>
            </div>
          </div>
        </div>

        <!-- Task Workload per Member -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">成員任務負載（未完成）</h3>
          <div v-if="data.task_workload.length === 0" class="py-8 text-center text-sm text-gray-400">無資料</div>
          <div v-else class="space-y-3">
            <div v-for="item in data.task_workload" :key="item.name"
              class="flex items-center gap-3">
              <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium shrink-0">
                {{ item.name.charAt(0) }}
              </div>
              <span class="text-sm text-gray-600 w-16 truncate">{{ item.name }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-2">
                <div
                  class="h-2 rounded-full bg-blue-500 transition-all"
                  :style="{ width: (item.task_count / maxWorkload * 100) + '%' }"
                />
              </div>
              <span class="text-xs text-gray-500 w-6 text-right">{{ item.task_count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Project Progress Ranking -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">專案進度排行（前 10）</h3>
        <div v-if="data.project_progress.length === 0" class="py-8 text-center text-sm text-gray-400">無資料</div>
        <div v-else class="space-y-2.5">
          <div v-for="(p, idx) in data.project_progress" :key="p.id"
            class="flex items-center gap-3">
            <span class="text-xs text-gray-400 w-5 text-right">{{ idx + 1 }}</span>
            <span class="text-sm text-gray-700 w-48 truncate">{{ p.name }}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="p.is_completed ? 'bg-green-500' : 'bg-blue-500'"
                :style="{ width: p.progress_percent + '%' }"
              />
            </div>
            <span class="text-xs font-medium text-gray-600 w-8 text-right">{{ p.progress_percent }}%</span>
          </div>
        </div>
      </div>

      <!-- Completion Trend -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">任務完成趨勢（近 6 個月）</h3>
        <div v-if="data.completion_trend.length === 0" class="py-8 text-center text-sm text-gray-400">
          近 6 個月無已完成任務
        </div>
        <div v-else class="flex items-end gap-3 h-32">
          <div
            v-for="item in data.completion_trend"
            :key="item.month"
            class="flex-1 flex flex-col items-center gap-1"
          >
            <span class="text-xs text-gray-500">{{ item.count }}</span>
            <div
              class="w-full rounded-t bg-blue-500 transition-all"
              :style="{ height: (item.count / maxTrend * 96) + 'px', minHeight: '4px' }"
            />
            <span class="text-xs text-gray-400">{{ item.month.slice(5) }}月</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'

interface StatsData {
  status_distribution: { status: { name: string; color: string; icon: string }; count: number }[]
  project_progress: { id: number; name: string; progress_percent: number; is_completed: boolean }[]
  task_workload: { name: string; task_count: number }[]
  completion_trend: { month: string; count: number }[]
  totals: { projects: number; tasks: number; completed_tasks: number; overdue_tasks: number; members: number }
}

const data = ref<StatsData | null>(null)
const loading = ref(false)

const totalProjects = computed(() => data.value?.status_distribution.reduce((s, i) => s + i.count, 0) || 1)
const maxWorkload = computed(() => Math.max(...(data.value?.task_workload.map(i => i.task_count) ?? [1]), 1))
const maxTrend = computed(() => Math.max(...(data.value?.completion_trend.map(i => i.count) ?? [1]), 1))

const summaryCards = computed(() => {
  if (!data.value) return []
  const t = data.value.totals
  return [
    { label: '總專案', value: t.projects, color: 'text-gray-900' },
    { label: '總任務', value: t.tasks, color: 'text-gray-900' },
    { label: '已完成任務', value: t.completed_tasks, color: 'text-green-600' },
    { label: '逾期任務', value: t.overdue_tasks, color: t.overdue_tasks > 0 ? 'text-red-600' : 'text-gray-400' },
    { label: '活躍成員', value: t.members, color: 'text-blue-600' },
  ]
})

onMounted(async () => {
  loading.value = true
  try {
    const res = await api.get('/stats')
    data.value = res.data
  } finally {
    loading.value = false
  }
})
</script>
