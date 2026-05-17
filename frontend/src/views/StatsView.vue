<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h6 font-weight-bold">統計分析</h2>
      <p class="text-body-2 text-grey">專案與任務整體數據</p>
    </div>

    <div v-if="loading" class="d-flex justify-center align-center py-16">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="data">
      <!-- Summary Cards -->
      <v-row class="mb-5">
        <v-col v-for="card in summaryCards" :key="card.label" cols="6" sm="4" md="">
          <v-card rounded="xl" height="100%">
            <v-card-text class="pa-4 text-center">
              <div class="text-h5 font-weight-bold" :class="card.color">{{ card.value }}</div>
              <div class="text-caption text-grey mt-1">{{ card.label }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mb-5">
        <!-- Status Distribution -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" height="100%">
            <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3">專案狀態分佈</v-card-title>
            <v-card-text class="pt-0">
              <div v-if="data.status_distribution.length === 0" class="py-8 text-center text-body-2 text-grey">無資料</div>
              <div v-else class="d-flex flex-column gap-3">
                <div v-for="item in data.status_distribution" :key="item.status.name" class="d-flex align-center gap-3">
                  <v-icon size="16" :style="{ color: item.status.color }" :icon="statusIcon(item.status.icon)" />
                  <span class="text-body-2 text-grey-darken-1" style="width:80px">{{ item.status.name }}</span>
                  <v-progress-linear
                    :model-value="item.count / totalProjects * 100"
                    :color="item.status.color"
                    bg-color="grey-lighten-3"
                    rounded
                    height="6"
                    class="flex-grow-1"
                  />
                  <span class="text-caption text-grey" style="width:24px;text-align:right">{{ item.count }}</span>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Task Workload -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" height="100%">
            <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3">成員任務負載（未完成）</v-card-title>
            <v-card-text class="pt-0">
              <div v-if="data.task_workload.length === 0" class="py-8 text-center text-body-2 text-grey">無資料</div>
              <div v-else class="d-flex flex-column gap-3">
                <div v-for="item in data.task_workload" :key="item.name" class="d-flex align-center gap-3">
                  <v-avatar color="primary" size="26" class="mr-2">
                    <span class="text-caption text-white font-weight-bold">{{ item.name.charAt(0) }}</span>
                  </v-avatar>
                  <span class="text-body-2 text-grey-darken-1 text-truncate" style="width:64px">{{ item.name }}</span>
                  <v-progress-linear
                    :model-value="item.task_count / maxWorkload * 100"
                    color="primary"
                    bg-color="grey-lighten-3"
                    rounded
                    height="6"
                    class="flex-grow-1"
                  />
                  <span class="text-caption text-grey" style="width:24px;text-align:right">{{ item.task_count }}</span>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Project Progress Ranking -->
      <v-card rounded="xl" class="mb-6">
        <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3">專案進度排行（前 10）</v-card-title>
        <v-card-text class="pt-0">
          <div v-if="data.project_progress.length === 0" class="py-8 text-center text-body-2 text-grey">無資料</div>
          <div v-else class="d-flex flex-column gap-2">
            <div v-for="(p, idx) in data.project_progress" :key="p.id" class="d-flex align-center gap-3">
              <span class="text-caption text-grey" style="width:20px;text-align:right">{{ idx + 1 }}</span>
              <span class="text-body-2 text-truncate" style="width:180px">{{ p.name }}</span>
              <v-progress-linear
                :model-value="p.progress_percent"
                :color="p.is_completed ? 'success' : 'primary'"
                bg-color="grey-lighten-3"
                rounded
                height="6"
                class="flex-grow-1"
              />
              <span class="text-caption font-weight-medium text-grey-darken-1" style="width:36px;text-align:right">
                {{ p.progress_percent }}%
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Completion Trend -->
      <v-card rounded="xl">
        <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3">任務完成趨勢（近 6 個月）</v-card-title>
        <v-card-text class="pt-0">
          <div v-if="data.completion_trend.length === 0" class="py-8 text-center text-body-2 text-grey">
            近 6 個月無已完成任務
          </div>
          <div v-else class="d-flex align-end gap-3" style="height:128px">
            <div
              v-for="item in data.completion_trend"
              :key="item.month"
              class="d-flex flex-column align-center gap-1 flex-grow-1"
            >
              <span class="text-caption text-grey">{{ item.count }}</span>
              <div
                class="rounded-t w-100"
                :style="{
                  height: (item.count / maxTrend * 96) + 'px',
                  minHeight: '4px',
                  backgroundColor: '#00897B',
                }"
              />
              <span class="text-caption text-grey">{{ item.month.slice(5) }}月</span>
            </div>
          </div>
        </v-card-text>
      </v-card>
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
const maxWorkload   = computed(() => Math.max(...(data.value?.task_workload.map(i => i.task_count) ?? [1]), 1))
const maxTrend      = computed(() => Math.max(...(data.value?.completion_trend.map(i => i.count) ?? [1]), 1))

const summaryCards = computed(() => {
  if (!data.value) return []
  const t = data.value.totals
  return [
    { label: '總專案',   value: t.projects,       color: '' },
    { label: '總任務',   value: t.tasks,           color: '' },
    { label: '已完成任務', value: t.completed_tasks, color: 'text-success' },
    { label: '逾期任務',  value: t.overdue_tasks,   color: t.overdue_tasks > 0 ? 'text-error' : 'text-grey' },
    { label: '活躍成員',  value: t.members,         color: 'text-primary' },
  ]
})

function statusIcon(icon: string | null | undefined) {
  if (!icon) return 'mdi-circle-outline'
  const normalized = icon.replace(/_/g, '-')
  return normalized.startsWith('mdi-') ? normalized : `mdi-${normalized}`
}

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
