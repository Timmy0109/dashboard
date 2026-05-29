<script setup lang="ts">
// StatsView — 統計分析
// 5 summary cards / 狀態分佈 / 成員負載 / 專案進度排行 / 任務完成趨勢
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/axios'

import KPICard from '@/components/ui/KPICard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import TrendChart from '@/components/charts/TrendChart.vue'
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

function openMemberDetail(userId: number | null) {
  if (userId) selectedMemberId.value = userId
}

const totalProjects = computed(() =>
  data.value?.status_distribution.reduce((s, i) => s + i.count, 0) || 0,
)
const totalForPct = computed(() => Math.max(totalProjects.value, 1))
const maxWorkload = computed(() =>
  Math.max(...(data.value?.task_workload.map(i => i.task_count) ?? [1]), 1),
)

interface SummaryCard {
  label: string
  value: number
  icon: string
  iconColor: string
  accent: 'primary' | 'success' | 'warning' | 'error' | 'info'
  trend?: string
}

const summaryCards = computed<SummaryCard[]>(() => {
  if (!data.value) return []
  const t = data.value.totals
  const completeRate = t.tasks > 0 ? Math.round((t.completed_tasks / t.tasks) * 100) : 0
  return [
    {
      label: '總專案數', value: t.projects,
      icon: 'mdi-folder-multiple', iconColor: 'primary', accent: 'primary',
    },
    {
      label: '總任務數', value: t.tasks,
      icon: 'mdi-clipboard-list', iconColor: 'info', accent: 'info',
    },
    {
      label: '已完成任務', value: t.completed_tasks,
      icon: 'mdi-clipboard-check', iconColor: 'success', accent: 'success',
      trend: t.tasks > 0 ? `${completeRate}%` : undefined,
    },
    {
      label: '逾期任務', value: t.overdue_tasks,
      icon: 'mdi-alert-circle',
      iconColor: t.overdue_tasks > 0 ? 'error' : 'grey',
      accent: t.overdue_tasks > 0 ? 'error' : 'warning',
      trend: t.overdue_tasks > 0 ? '需注意' : undefined,
    },
    {
      label: '活躍成員', value: t.members,
      icon: 'mdi-account-group', iconColor: 'info', accent: 'info',
    },
  ]
})

// 狀態分佈：堆疊式 progress bar 顯示比例
interface StackSegment {
  name: string
  color: string
  pct: number
}
const stackedSegments = computed<StackSegment[]>(() => {
  if (!data.value) return []
  return data.value.status_distribution
    .filter(i => i.count > 0)
    .map(i => ({
      name: i.status.name,
      color: i.status.color,
      pct: (i.count / totalForPct.value) * 100,
    }))
})

function statusIcon(icon: string | null | undefined) {
  if (!icon) return 'mdi-circle-outline'
  const normalized = icon.replace(/_/g, '-')
  return normalized.startsWith('mdi-') ? normalized : `mdi-${normalized}`
}

function rankIcon(idx: number) {
  if (idx === 0) return { icon: 'mdi-trophy', color: 'amber-darken-2' }
  if (idx === 1) return { icon: 'mdi-medal',  color: 'grey-darken-1' }
  if (idx === 2) return { icon: 'mdi-medal',  color: 'brown-lighten-1' }
  return null
}

function progressColor(pct: number, isCompleted: boolean) {
  if (isCompleted) return 'success'
  if (pct >= 70) return 'primary'
  if (pct >= 30) return 'warning'
  return 'error'
}

function workloadColor(idx: number) {
  if (idx === 0) return 'error'
  if (idx === 1) return 'warning'
  return 'primary'
}

const trendData = computed(() =>
  (data.value?.completion_trend ?? []).map(i => ({
    month: i.month.slice(5) + '月',
    count: i.count,
  })),
)
const trendTotal = computed(() =>
  (data.value?.completion_trend ?? []).reduce((s, i) => s + i.count, 0),
)

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

<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h5 font-weight-bold">統計分析</h2>
      <p class="text-body-2 text-medium-emphasis mt-1 mb-0">專案與任務整體數據</p>
    </div>

    <div v-if="loading" class="d-flex justify-center align-center py-16">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="data">
      <!-- Summary 5 cards -->
      <v-row class="mb-5" dense>
        <v-col
          v-for="card in summaryCards"
          :key="card.label"
          cols="6"
          sm="4"
          md=""
        >
          <KPICard
            :label="card.label"
            :value="card.value"
            :icon="card.icon"
            :icon-color="card.iconColor"
            :accent="card.accent"
            :trend="card.trend"
          />
        </v-col>
      </v-row>

      <!-- 狀態分佈 + 成員負載 -->
      <v-row class="mb-5" align="stretch">
        <!-- 狀態分佈 -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" class="h-100">
            <v-card-title class="d-flex align-center gap-2 pa-5 pb-3">
              <v-icon icon="mdi-chart-donut" color="primary" size="20" />
              <span class="text-body-1 font-weight-medium">專案狀態分佈</span>
              <v-spacer />
              <v-chip size="x-small" color="primary" variant="tonal">
                {{ totalProjects }} 個專案
              </v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-5">
              <EmptyState
                v-if="stackedSegments.length === 0"
                icon="mdi-chart-donut"
                title="尚無資料"
                sub="目前沒有專案狀態資料"
              />
              <template v-else>
                <!-- 堆疊式 progress bar -->
                <div
                  class="d-flex w-100 overflow-hidden rounded mb-5"
                  style="height: 14px; background-color: rgba(var(--v-theme-on-surface), 0.06)"
                  role="img"
                  aria-label="專案狀態分佈"
                >
                  <div
                    v-for="seg in stackedSegments"
                    :key="seg.name"
                    :style="{ width: seg.pct + '%', backgroundColor: seg.color, transition: 'width .3s ease' }"
                    :title="`${seg.name} ${Math.round(seg.pct)}%`"
                  />
                </div>

                <!-- Legend with progress bars -->
                <div class="d-flex flex-column gap-3">
                  <div
                    v-for="item in data.status_distribution"
                    :key="item.status.name"
                    class="d-flex align-center gap-3"
                  >
                    <div class="d-flex align-center gap-2" style="width: 110px; flex-shrink: 0">
                      <v-icon
                        :icon="statusIcon(item.status.icon)"
                        size="14"
                        :style="{ color: item.status.color }"
                      />
                      <span class="text-body-2 text-truncate">{{ item.status.name }}</span>
                    </div>
                    <v-progress-linear
                      :model-value="(item.count / totalForPct) * 100"
                      :color="item.status.color"
                      bg-color="grey-lighten-3"
                      rounded
                      height="8"
                      class="flex-grow-1"
                    />
                    <div
                      class="d-flex align-center gap-1"
                      style="width: 60px; flex-shrink: 0; justify-content: flex-end"
                    >
                      <span class="text-body-2 font-weight-medium">{{ item.count }}</span>
                      <span class="text-caption text-medium-emphasis">
                        ({{ Math.round((item.count / totalForPct) * 100) }}%)
                      </span>
                    </div>
                  </div>
                </div>
              </template>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- 成員任務負載 -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" class="h-100">
            <v-card-title class="d-flex align-center gap-2 pa-5 pb-3">
              <v-icon icon="mdi-account-group" color="primary" size="20" />
              <span class="text-body-1 font-weight-medium">成員任務負載</span>
              <v-spacer />
              <v-chip size="x-small" color="grey" variant="tonal">未完成</v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-5">
              <EmptyState
                v-if="data.task_workload.length === 0"
                icon="mdi-account-group-outline"
                title="尚無資料"
                sub="目前沒有成員負載資料"
              />
              <div v-else class="d-flex flex-column gap-2">
                <div
                  v-for="(item, idx) in data.task_workload"
                  :key="item.name + idx"
                  class="pms-workload-row d-flex align-center gap-3 px-2 py-2 rounded-lg"
                  :class="{ 'pms-workload-row--clickable': !!item.user_id }"
                  :tabindex="item.user_id ? 0 : -1"
                  :role="item.user_id ? 'button' : undefined"
                  @click="openMemberDetail(item.user_id)"
                  @keydown.enter="openMemberDetail(item.user_id)"
                >
                  <!-- Rank -->
                  <div class="d-flex align-center justify-center" style="width: 22px; flex-shrink: 0">
                    <v-icon
                      v-if="rankIcon(idx)"
                      :icon="rankIcon(idx)!.icon"
                      :color="rankIcon(idx)!.color"
                      size="16"
                    />
                    <span v-else class="text-caption text-medium-emphasis">{{ idx + 1 }}</span>
                  </div>
                  <v-avatar color="primary" size="26" style="flex-shrink: 0">
                    <span class="text-caption text-white font-weight-bold">
                      {{ item.name.charAt(0) }}
                    </span>
                  </v-avatar>
                  <span
                    class="text-body-2 text-truncate"
                    style="width: 80px; flex-shrink: 0"
                  >
                    {{ item.name }}
                  </span>
                  <v-progress-linear
                    :model-value="(item.task_count / maxWorkload) * 100"
                    :color="workloadColor(idx)"
                    bg-color="grey-lighten-3"
                    rounded
                    height="7"
                    class="flex-grow-1"
                  />
                  <span
                    class="text-body-2 font-weight-medium"
                    style="width: 24px; text-align: right; flex-shrink: 0"
                  >
                    {{ item.task_count }}
                  </span>
                  <v-icon
                    v-if="item.user_id"
                    icon="mdi-chevron-right"
                    size="16"
                    color="grey"
                    style="flex-shrink: 0"
                  />
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- 專案進度排行 -->
      <v-card rounded="xl" class="mb-5">
        <v-card-title class="d-flex align-center gap-2 pa-5 pb-3">
          <v-icon icon="mdi-podium" color="primary" size="20" />
          <span class="text-body-1 font-weight-medium">專案進度排行</span>
          <v-spacer />
          <v-chip size="x-small" color="grey" variant="tonal">
            前 {{ data.project_progress.length }} 名
          </v-chip>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <EmptyState
            v-if="data.project_progress.length === 0"
            icon="mdi-podium"
            title="尚無資料"
            sub="目前沒有專案進度資料"
          />
          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="(p, idx) in data.project_progress"
              :key="p.id"
              class="d-flex align-center gap-3"
            >
              <div class="d-flex align-center justify-center" style="width: 28px; flex-shrink: 0">
                <v-icon
                  v-if="rankIcon(idx)"
                  :icon="rankIcon(idx)!.icon"
                  :color="rankIcon(idx)!.color"
                  size="18"
                />
                <span v-else class="text-caption font-weight-medium text-medium-emphasis">
                  {{ idx + 1 }}
                </span>
              </div>
              <div class="d-flex align-center gap-2" style="width: 220px; flex-shrink: 0">
                <span class="text-body-2 text-truncate">{{ p.name }}</span>
                <v-chip
                  v-if="p.is_completed"
                  size="x-small"
                  color="success"
                  variant="tonal"
                  class="flex-shrink-0"
                >
                  完成
                </v-chip>
              </div>
              <v-progress-linear
                :model-value="p.progress_percent"
                :color="progressColor(p.progress_percent, p.is_completed)"
                bg-color="grey-lighten-3"
                rounded
                height="8"
                class="flex-grow-1"
              />
              <span
                class="text-body-2 font-weight-medium"
                :class="p.is_completed ? 'text-success' : 'text-medium-emphasis'"
                style="width: 44px; text-align: right; flex-shrink: 0"
              >
                {{ p.progress_percent }}%
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- 任務完成趨勢 -->
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 pa-5 pb-3">
          <v-icon icon="mdi-chart-bar" color="primary" size="20" />
          <span class="text-body-1 font-weight-medium">任務完成趨勢</span>
          <v-spacer />
          <v-chip size="x-small" color="grey" variant="tonal">近 6 個月</v-chip>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <TrendChart :data="trendData" />
          <div v-if="trendData.length > 0" class="d-flex justify-end mt-3">
            <span class="text-caption text-medium-emphasis">
              近 6 個月合計
              <span class="font-weight-bold text-primary ml-1">{{ trendTotal }}</span>
              個任務完成
            </span>
          </div>
        </v-card-text>
      </v-card>
    </template>

    <MemberWorkloadDialog
      v-if="selectedMemberId"
      :user-id="selectedMemberId"
      @close="selectedMemberId = null"
    />
  </div>
</template>

<style scoped>
.pms-workload-row {
  transition: background-color 0.15s ease;
}
.pms-workload-row--clickable {
  cursor: pointer;
}
.pms-workload-row--clickable:hover,
.pms-workload-row--clickable:focus-visible {
  background-color: rgba(var(--v-theme-on-surface), 0.05);
  outline: none;
}
</style>
