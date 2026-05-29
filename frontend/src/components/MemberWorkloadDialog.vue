<script setup lang="ts">
// MemberWorkloadDialog — 成員負載：summary + 專案分佈圖 + 任務列表
// 保留 props.userId 與 emit('close')、emit('edit', userId)
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import KPICard from '@/components/ui/KPICard.vue'
import ChipGroup from '@/components/ui/ChipGroup.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import HBarChart from '@/components/charts/HBarChart.vue'

const props = defineProps<{ userId: number }>()
const emit = defineEmits<{ close: []; edit: [userId: number] }>()

interface MemberSummary {
  total: number
  completed: number
  pending: number
  in_progress: number
  overdue: number
  due_soon: number
  overdue_rate: number
  avg_progress: number
  project_count: number
}
interface ProjectRow {
  project_id: number
  project_name: string
  total: number
  completed: number
  overdue: number
}
interface TaskRow {
  id: number
  name: string
  project_name: string
  end_date: string | null
  progress: number
  is_completed: boolean
  is_overdue: boolean
  status: { id: number; name: string; color: string; icon: string } | null
  priority: { id: number; name: string; color: string } | null
}
interface MemberDetail {
  member: { id: number; name: string; email: string }
  summary: MemberSummary
  by_project: ProjectRow[]
  tasks: TaskRow[]
}

const loading = ref(false)
const data = ref<MemberDetail | null>(null)
const taskFilter = ref<'all' | 'overdue' | 'pending' | 'in_progress' | 'completed'>('all')

const filteredTasks = computed(() => {
  const tasks = data.value?.tasks ?? []
  if (taskFilter.value === 'overdue')     return tasks.filter(t => t.is_overdue)
  if (taskFilter.value === 'pending')     return tasks.filter(t => !t.is_completed && t.progress === 0 && !t.is_overdue)
  if (taskFilter.value === 'in_progress') return tasks.filter(t => !t.is_completed && t.progress > 0)
  if (taskFilter.value === 'completed')   return tasks.filter(t => t.is_completed)
  return tasks
})

const counts = computed(() => {
  const tasks = data.value?.tasks ?? []
  return {
    all:         tasks.length,
    overdue:     tasks.filter(t => t.is_overdue).length,
    pending:     tasks.filter(t => !t.is_completed && t.progress === 0 && !t.is_overdue).length,
    in_progress: tasks.filter(t => !t.is_completed && t.progress > 0).length,
    completed:   tasks.filter(t => t.is_completed).length,
  }
})

const tabItems = computed(() => [
  { value: 'all',         label: '全部',   count: counts.value.all },
  { value: 'overdue',     label: '逾期',   count: counts.value.overdue },
  { value: 'pending',     label: '待處理', count: counts.value.pending },
  { value: 'in_progress', label: '進行中', count: counts.value.in_progress },
  { value: 'completed',   label: '已完成', count: counts.value.completed },
])

const projectChartData = computed(() => {
  const rows = data.value?.by_project ?? []
  return rows
    .slice()
    .sort((a, b) => b.total - a.total)
    .slice(0, 8)
    .map(r => ({
      label: r.project_name,
      value: r.total,
      color: r.overdue > 0 ? 'rgb(var(--v-theme-error))' : 'rgb(var(--v-theme-primary))',
    }))
})

const initial = computed(() => data.value?.member.name.charAt(0).toUpperCase() ?? '?')

function onEditMember() {
  emit('edit', props.userId)
  emit('close')
}

onMounted(async () => {
  loading.value = true
  try {
    const { data: res } = await api.get(`/stats/member/${props.userId}`)
    data.value = res
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <v-dialog :model-value="true" max-width="760" scrollable @update:model-value="emit('close')">
    <v-card rounded="xl">
      <!-- Header -->
      <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <div class="d-flex align-center gap-3">
          <v-avatar color="white" size="44">
            <span class="text-body-1 font-weight-bold text-primary">{{ initial }}</span>
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold text-white">{{ data?.member.name ?? '—' }}</div>
            <div class="text-caption" style="color:rgba(255,255,255,.75)">{{ data?.member.email ?? '' }}</div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="emit('close')" />
      </v-card-title>

      <v-card-text class="pa-0">
        <!-- Loading -->
        <div v-if="loading" class="d-flex justify-center align-center py-16">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <template v-else-if="data">
          <!-- KPI Summary -->
          <div class="pa-5 pb-3">
            <v-row dense>
              <v-col cols="6" sm="3">
                <KPICard
                  label="參與專案"
                  :value="data.summary.project_count"
                  icon="mdi-folder-multiple-outline"
                  icon-color="primary"
                  accent="primary"
                />
              </v-col>
              <v-col cols="6" sm="3">
                <KPICard
                  label="已完成"
                  :value="data.summary.completed"
                  icon="mdi-check-circle-outline"
                  icon-color="success"
                  accent="success"
                />
              </v-col>
              <v-col cols="6" sm="3">
                <KPICard
                  label="未完成"
                  :value="data.summary.total - data.summary.completed"
                  icon="mdi-progress-clock"
                  icon-color="info"
                  accent="info"
                />
              </v-col>
              <v-col cols="6" sm="3">
                <KPICard
                  label="逾期"
                  :value="data.summary.overdue"
                  icon="mdi-alert-circle-outline"
                  :icon-color="data.summary.overdue > 0 ? 'error' : 'primary'"
                  :accent="data.summary.overdue > 0 ? 'error' : 'primary'"
                />
              </v-col>
            </v-row>

            <!-- 副指標 -->
            <div class="d-flex flex-wrap gap-4 mt-3 text-caption text-medium-emphasis">
              <span>
                <v-icon icon="mdi-percent-outline" size="14" class="mr-1" />
                平均進度 {{ data.summary.avg_progress }}%
              </span>
              <span>
                <v-icon icon="mdi-alert-outline" size="14" class="mr-1" />
                逾期率 {{ data.summary.overdue_rate }}%
              </span>
              <span>
                <v-icon icon="mdi-clock-alert-outline" size="14" class="mr-1" />
                即將到期 {{ data.summary.due_soon }}
              </span>
            </div>
          </div>

          <v-divider />

          <!-- 專案分佈 -->
          <div class="pa-5 pb-4">
            <div class="text-caption text-medium-emphasis font-weight-bold text-uppercase mb-3">
              專案任務分佈
            </div>
            <HBarChart :data="projectChartData" />
          </div>

          <v-divider />

          <!-- Task list -->
          <div class="pa-5 pt-4">
            <div class="d-flex align-center justify-space-between mb-3 flex-wrap gap-2">
              <div class="text-caption text-medium-emphasis font-weight-bold text-uppercase">
                任務列表
              </div>
              <ChipGroup v-model="taskFilter" :items="tabItems" />
            </div>

            <EmptyState
              v-if="filteredTasks.length === 0"
              icon="mdi-clipboard-text-outline"
              title="沒有符合條件的任務"
              sub="試試切換其他篩選條件"
            />
            <div v-else class="d-flex flex-column gap-2">
              <div
                v-for="task in filteredTasks"
                :key="task.id"
                class="d-flex align-center gap-3 pa-3 rounded-lg pms-task-row"
                :class="{ 'pms-task-row--overdue': task.is_overdue }"
              >
                <v-icon
                  :icon="task.is_completed ? 'mdi-check-circle' : task.is_overdue ? 'mdi-alert-circle' : 'mdi-circle-outline'"
                  :color="task.is_completed ? 'success' : task.is_overdue ? 'error' : 'grey'"
                  size="18"
                />
                <div class="flex-grow-1 overflow-hidden">
                  <div class="text-body-2 font-weight-medium text-truncate">{{ task.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ task.project_name }}</div>
                </div>
                <span
                  class="text-caption pms-tnum"
                  :class="task.is_overdue ? 'text-error' : 'text-medium-emphasis'"
                  style="min-width:80px; text-align:right"
                >
                  {{ task.end_date ?? '—' }}
                </span>
                <div style="min-width:56px">
                  <div class="text-caption text-right mb-1 pms-tnum">{{ task.progress }}%</div>
                  <v-progress-linear
                    :model-value="task.progress"
                    :color="task.is_completed ? 'success' : task.is_overdue ? 'error' : 'primary'"
                    bg-color="grey-lighten-3"
                    rounded
                    height="4"
                  />
                </div>
              </div>
            </div>
          </div>
        </template>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-btn variant="text" @click="emit('close')">關閉</v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          prepend-icon="mdi-account-edit"
          :disabled="!data"
          @click="onEditMember"
        >編輯成員</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.pms-task-row {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}
.pms-task-row--overdue {
  background-color: rgba(var(--v-theme-error), 0.06);
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
