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
            <v-card-text class="pa-4">
              <div class="d-flex align-center justify-space-between mb-3">
                <div
                  class="d-flex align-center justify-center rounded-lg"
                  :style="{ width: '36px', height: '36px', backgroundColor: card.bgColor }"
                >
                  <v-icon :icon="card.icon" :color="card.iconColor" size="18" />
                </div>
                <v-chip v-if="card.badge" size="x-small" :color="card.badgeColor" variant="tonal">
                  {{ card.badge }}
                </v-chip>
              </div>
              <div class="text-h5 font-weight-bold" :class="card.color">{{ card.value }}</div>
              <div class="text-caption text-grey mt-1">{{ card.label }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mb-5" align="stretch">
        <!-- Status Distribution -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" height="100%">
            <v-card-title class="d-flex align-center gap-2 pa-5 pb-4">
              <v-icon icon="mdi-chart-donut" color="primary" size="20" />
              <span class="text-body-1 font-weight-semibold">專案狀態分佈</span>
              <v-spacer />
              <v-chip size="x-small" color="primary" variant="tonal">{{ totalProjects }} 個專案</v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-5">
              <div v-if="data.status_distribution.length === 0" class="py-8 text-center text-body-2 text-grey">無資料</div>
              <div v-else class="d-flex flex-column gap-4">
                <div v-for="item in data.status_distribution" :key="item.status.name" class="d-flex align-center gap-3">
                  <div class="d-flex align-center gap-2" style="width:110px;flex-shrink:0">
                    <v-icon size="14" :style="{ color: item.status.color }" :icon="statusIcon(item.status.icon)" />
                    <span class="text-body-2 text-grey-darken-1 text-truncate">{{ item.status.name }}</span>
                  </div>
                  <v-progress-linear
                    :model-value="item.count / totalProjects * 100"
                    :color="item.status.color"
                    bg-color="grey-lighten-3"
                    rounded
                    height="8"
                    class="flex-grow-1"
                  />
                  <div class="d-flex align-center gap-1" style="width:52px;flex-shrink:0;justify-content:flex-end">
                    <span class="text-body-2 font-weight-medium">{{ item.count }}</span>
                    <span class="text-caption text-grey">({{ Math.round(item.count / totalProjects * 100) }}%)</span>
                  </div>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Task Workload -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" height="100%">
            <v-card-title class="d-flex align-center gap-2 pa-5 pb-4">
              <v-icon icon="mdi-account-group" color="primary" size="20" />
              <span class="text-body-1 font-weight-semibold">成員任務負載</span>
              <v-spacer />
              <v-chip size="x-small" color="grey" variant="tonal">未完成</v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-5">
              <div v-if="data.task_workload.length === 0" class="py-8 text-center text-body-2 text-grey">無資料</div>
              <div v-else class="d-flex flex-column gap-3">
                <div
                  v-for="(item, idx) in data.task_workload"
                  :key="item.name"
                  class="d-flex align-center gap-3 px-2 py-1 rounded-lg"
                  :style="item.user_id ? 'cursor:pointer;transition:background .15s' : ''"
                  @click="item.user_id && openMemberDetail(item.user_id)"
                  @mouseenter="item.user_id && ($event.currentTarget as HTMLElement).classList.add('bg-grey-lighten-4')"
                  @mouseleave="item.user_id && ($event.currentTarget as HTMLElement).classList.remove('bg-grey-lighten-4')"
                >
                  <!-- Rank badge -->
                  <div class="d-flex align-center justify-center rounded-lg" style="width:22px;flex-shrink:0">
                    <v-icon v-if="idx === 0" icon="mdi-trophy" color="amber-darken-2" size="16" />
                    <v-icon v-else-if="idx === 1" icon="mdi-medal" color="grey-darken-1" size="16" />
                    <v-icon v-else-if="idx === 2" icon="mdi-medal" color="brown-lighten-1" size="16" />
                    <span v-else class="text-caption text-grey">{{ idx + 1 }}</span>
                  </div>
                  <v-avatar color="primary" size="26" class="mr-1" style="flex-shrink:0">
                    <span class="text-caption text-white font-weight-bold">{{ item.name.charAt(0) }}</span>
                  </v-avatar>
                  <span class="text-body-2 text-grey-darken-1 text-truncate" style="width:60px;flex-shrink:0">{{ item.name }}</span>
                  <v-progress-linear
                    :model-value="item.task_count / maxWorkload * 100"
                    :color="idx === 0 ? 'error' : idx === 1 ? 'warning' : 'primary'"
                    bg-color="grey-lighten-3"
                    rounded
                    height="7"
                    class="flex-grow-1"
                  />
                  <span class="text-body-2 font-weight-medium" style="width:20px;text-align:right;flex-shrink:0">{{ item.task_count }}</span>
                  <v-icon v-if="item.user_id" icon="mdi-chevron-right" size="16" color="grey" style="flex-shrink:0" />
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Project Progress Ranking -->
      <v-card rounded="xl" class="mb-5">
        <v-card-title class="d-flex align-center gap-2 pa-5 pb-4">
          <v-icon icon="mdi-podium" color="primary" size="20" />
          <span class="text-body-1 font-weight-semibold">專案進度排行</span>
          <v-spacer />
          <v-chip size="x-small" color="grey" variant="tonal">前 {{ data.project_progress.length }} 名</v-chip>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <div v-if="data.project_progress.length === 0" class="py-8 text-center text-body-2 text-grey">無資料</div>
          <div v-else class="d-flex flex-column gap-3">
            <div v-for="(p, idx) in data.project_progress" :key="p.id" class="d-flex align-center gap-3">
              <!-- Rank -->
              <div class="d-flex align-center justify-center" style="width:28px;flex-shrink:0">
                <v-icon v-if="idx === 0" icon="mdi-trophy" color="amber-darken-2" size="18" />
                <v-icon v-else-if="idx === 1" icon="mdi-medal" color="grey-darken-1" size="18" />
                <v-icon v-else-if="idx === 2" icon="mdi-medal" color="brown-lighten-1" size="18" />
                <span v-else class="text-caption text-grey font-weight-medium">{{ idx + 1 }}</span>
              </div>
              <!-- Name -->
              <div class="d-flex align-center gap-2" style="width:200px;flex-shrink:0">
                <span class="text-body-2 text-truncate">{{ p.name }}</span>
                <v-chip v-if="p.is_completed" size="x-small" color="success" variant="tonal" class="flex-shrink-0">完成</v-chip>
              </div>
              <!-- Progress bar -->
              <v-progress-linear
                :model-value="p.progress_percent"
                :color="p.is_completed ? 'success' : p.progress_percent >= 70 ? 'primary' : p.progress_percent >= 30 ? 'warning' : 'error'"
                bg-color="grey-lighten-3"
                rounded
                height="8"
                class="flex-grow-1"
              />
              <!-- Percent -->
              <span
                class="text-body-2 font-weight-semibold"
                :class="p.is_completed ? 'text-success' : 'text-grey-darken-1'"
                style="width:40px;text-align:right;flex-shrink:0"
              >
                {{ p.progress_percent }}%
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Completion Trend -->
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 pa-5 pb-4">
          <v-icon icon="mdi-chart-bar" color="primary" size="20" />
          <span class="text-body-1 font-weight-semibold">任務完成趨勢</span>
          <v-spacer />
          <v-chip size="x-small" color="grey" variant="tonal">近 6 個月</v-chip>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <div v-if="data.completion_trend.length === 0" class="py-8 text-center text-body-2 text-grey">
            近 6 個月無已完成任務
          </div>
          <template v-else>
            <!-- Y-axis guide lines -->
            <div class="position-relative" style="height:160px">
              <!-- Grid lines -->
              <div
                v-for="line in gridLines"
                :key="line.value"
                class="position-absolute w-100 d-flex align-center"
                :style="{ bottom: (line.value / maxTrend * 128) + 'px', left: 0 }"
              >
                <span class="text-caption text-grey" style="width:24px;flex-shrink:0;font-size:10px">{{ line.label }}</span>
                <div style="flex:1;height:1px;background:rgba(0,0,0,.06)" />
              </div>
              <!-- Bars -->
              <div class="d-flex align-end gap-2 h-100 pl-8">
                <div
                  v-for="item in data.completion_trend"
                  :key="item.month"
                  class="d-flex flex-column align-center gap-1"
                  style="flex:1"
                >
                  <span class="text-caption font-weight-medium" style="color:#00897B">{{ item.count }}</span>
                  <div
                    class="w-100 rounded-t"
                    :style="{
                      height: Math.max(item.count / maxTrend * 128, 4) + 'px',
                      background: `linear-gradient(to top, #00897B, #4DB6AC)`,
                      transition: 'height .3s ease',
                      borderRadius: '6px 6px 0 0',
                    }"
                  />
                  <span class="text-caption text-grey">{{ item.month.slice(5) }}月</span>
                </div>
              </div>
            </div>
            <!-- Total -->
            <div class="d-flex justify-end mt-3">
              <span class="text-caption text-grey">
                近 6 個月合計
                <span class="font-weight-bold text-primary ml-1">{{ data.completion_trend.reduce((s, i) => s + i.count, 0) }}</span>
                個任務完成
              </span>
            </div>
          </template>
        </v-card-text>
      </v-card>
    </template>
  </div>

  <MemberWorkloadDialog
    v-if="selectedMemberId"
    :user-id="selectedMemberId"
    @close="selectedMemberId = null"
  />
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import MemberWorkloadDialog from '@/components/MemberWorkloadDialog.vue'

interface StatsData {
  status_distribution: { status: { name: string; color: string; icon: string }; count: number }[]
  project_progress: { id: number; name: string; progress_percent: number; is_completed: boolean }[]
  task_workload: { user_id: number | null; name: string; task_count: number }[]
  completion_trend: { month: string; count: number }[]
  totals: { projects: number; tasks: number; completed_tasks: number; overdue_tasks: number; members: number }
}

const data = ref<StatsData | null>(null)
const loading = ref(false)
const selectedMemberId = ref<number | null>(null)

function openMemberDetail(userId: number) {
  selectedMemberId.value = userId
}

const totalProjects = computed(() => data.value?.status_distribution.reduce((s, i) => s + i.count, 0) || 1)
const maxWorkload   = computed(() => Math.max(...(data.value?.task_workload.map(i => i.task_count) ?? [1]), 1))
const maxTrend      = computed(() => Math.max(...(data.value?.completion_trend.map(i => i.count) ?? [1]), 1))

const gridLines = computed(() => {
  const max = maxTrend.value
  const step = max <= 5 ? 1 : max <= 20 ? 5 : max <= 50 ? 10 : 20
  const lines = []
  for (let v = step; v <= max; v += step) lines.push({ value: v, label: String(v) })
  return lines
})

const summaryCards = computed(() => {
  if (!data.value) return []
  const t = data.value.totals
  const completeRate = t.tasks > 0 ? Math.round(t.completed_tasks / t.tasks * 100) : 0
  return [
    {
      label: '總專案數', value: t.projects,
      icon: 'mdi-folder-multiple', iconColor: 'primary', bgColor: '#e8f5e9',
      color: '', badge: null, badgeColor: '',
    },
    {
      label: '總任務數', value: t.tasks,
      icon: 'mdi-clipboard-list', iconColor: 'blue-darken-1', bgColor: '#e3f2fd',
      color: '', badge: null, badgeColor: '',
    },
    {
      label: '已完成任務', value: t.completed_tasks,
      icon: 'mdi-clipboard-check', iconColor: 'success', bgColor: '#e8f5e9',
      color: 'text-success', badge: completeRate + '%', badgeColor: 'success',
    },
    {
      label: '逾期任務', value: t.overdue_tasks,
      icon: 'mdi-alert-circle', iconColor: t.overdue_tasks > 0 ? 'error' : 'grey', bgColor: t.overdue_tasks > 0 ? '#fdecea' : '#f5f5f5',
      color: t.overdue_tasks > 0 ? 'text-error' : 'text-grey',
      badge: t.overdue_tasks > 0 ? '需注意' : null, badgeColor: 'error',
    },
    {
      label: '活躍成員', value: t.members,
      icon: 'mdi-account-group', iconColor: 'purple', bgColor: '#f3e5f5',
      color: 'text-purple', badge: null, badgeColor: '',
    },
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
