<template>
  <v-dialog :model-value="true" max-width="720" scrollable @update:model-value="emit('close')">
    <v-card rounded="xl">
      <!-- Header -->
      <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <div class="d-flex align-center gap-3">
          <v-avatar color="white" size="40" class="mr-2">
            <span class="text-body-1 font-weight-bold text-primary">{{ data?.member.name.charAt(0).toUpperCase() }}</span>
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-semibold text-white">{{ data?.member.name }}</div>
            <div class="text-caption" style="color:rgba(255,255,255,.75)">{{ data?.member.email }}</div>
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
          <!-- Summary stat cards -->
          <div class="pa-5 pb-4">
            <v-row dense>
              <v-col v-for="card in summaryCards" :key="card.label" cols="6" sm="4">
                <v-card rounded="xl" variant="tonal" :color="card.color" class="text-center pa-3">
                  <div class="text-h5 font-weight-bold">{{ card.value }}</div>
                  <div class="text-caption mt-1">{{ card.label }}</div>
                </v-card>
              </v-col>
            </v-row>
          </div>

          <!-- Health indicators -->
          <div class="px-5 pb-4">
            <div class="text-caption text-grey font-weight-bold text-uppercase mb-3">工作健康指標</div>
            <v-row dense>
              <v-col cols="12" sm="4">
                <div class="d-flex flex-column align-center pa-3 rounded-xl bg-grey-lighten-5">
                  <div class="position-relative mb-2" style="width:72px;height:72px">
                    <svg width="72" height="72" viewBox="0 0 36 36" style="transform:rotate(-90deg)">
                      <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e0e0e0" stroke-width="3" />
                      <circle
                        cx="18" cy="18" r="15.9"
                        fill="none"
                        :stroke="data.summary.overdue_rate > 40 ? '#ef5350' : data.summary.overdue_rate > 20 ? '#FFA726' : '#00897B'"
                        stroke-width="3"
                        :stroke-dasharray="`${data.summary.overdue_rate} ${100 - data.summary.overdue_rate}`"
                        stroke-linecap="round"
                      />
                    </svg>
                    <div class="position-absolute d-flex flex-column align-center justify-center" style="top:0;left:0;right:0;bottom:0">
                      <span class="text-caption font-weight-bold">{{ data.summary.overdue_rate }}%</span>
                    </div>
                  </div>
                  <div class="text-caption text-grey text-center">逾期率</div>
                  <v-chip
                    size="x-small"
                    :color="data.summary.overdue_rate > 40 ? 'error' : data.summary.overdue_rate > 20 ? 'warning' : 'success'"
                    variant="tonal"
                    class="mt-1"
                  >
                    {{ data.summary.overdue_rate > 40 ? '需關注' : data.summary.overdue_rate > 20 ? '略高' : '健康' }}
                  </v-chip>
                </div>
              </v-col>
              <v-col cols="12" sm="4">
                <div class="d-flex flex-column align-center pa-3 rounded-xl bg-grey-lighten-5">
                  <div class="text-h4 font-weight-bold text-primary mb-1">{{ data.summary.avg_progress }}%</div>
                  <v-progress-linear
                    :model-value="data.summary.avg_progress"
                    color="primary"
                    bg-color="grey-lighten-3"
                    rounded
                    height="6"
                    class="w-100 mb-2"
                  />
                  <div class="text-caption text-grey text-center">未完成任務平均進度</div>
                </div>
              </v-col>
              <v-col cols="12" sm="4">
                <div class="d-flex flex-column align-center pa-3 rounded-xl bg-grey-lighten-5 h-100">
                  <div class="d-flex align-center gap-2 mb-1">
                    <v-icon icon="mdi-folder-multiple" color="primary" size="28" />
                    <span class="text-h4 font-weight-bold">{{ data.summary.project_count }}</span>
                  </div>
                  <div class="text-caption text-grey text-center mb-2">參與專案數</div>
                  <div class="d-flex align-center gap-1">
                    <v-icon icon="mdi-clock-alert" color="warning" size="14" />
                    <span class="text-caption text-warning">{{ data.summary.due_soon }} 個任務本週到期</span>
                  </div>
                </div>
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Per-project breakdown -->
          <div class="pa-5 pb-3" v-if="data.by_project.length">
            <div class="text-caption text-grey font-weight-bold text-uppercase mb-3">各專案任務分佈</div>
            <div class="d-flex flex-column gap-2">
              <div v-for="proj in data.by_project" :key="proj.project_id" class="d-flex align-center gap-3">
                <span class="text-body-2 text-truncate" style="min-width:120px;max-width:160px">{{ proj.project_name }}</span>
                <v-progress-linear
                  :model-value="proj.total > 0 ? proj.completed / proj.total * 100 : 0"
                  color="success"
                  bg-color="grey-lighten-3"
                  rounded
                  height="6"
                  class="flex-grow-1"
                />
                <div class="d-flex gap-2 text-caption" style="min-width:90px">
                  <span class="text-success">{{ proj.completed }}/{{ proj.total }}</span>
                  <span v-if="proj.overdue > 0" class="text-error">逾期{{ proj.overdue }}</span>
                </div>
              </div>
            </div>
          </div>

          <v-divider />

          <!-- Task list -->
          <div class="pa-5 pt-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="text-caption text-grey font-weight-bold text-uppercase">任務列表</div>
              <v-chip-group v-model="taskFilter" mandatory selected-class="bg-primary text-white">
                <v-chip v-for="tab in taskTabs" :key="tab.value" :value="tab.value" size="x-small" filter>
                  {{ tab.label }}
                </v-chip>
              </v-chip-group>
            </div>
            <div class="d-flex flex-column gap-2">
              <template v-if="filteredTasks.length === 0">
                <div class="text-center py-6 text-grey text-body-2">沒有符合條件的任務</div>
              </template>
              <div
                v-for="task in filteredTasks"
                :key="task.id"
                class="d-flex align-center gap-3 pa-3 rounded-lg"
                :class="task.is_overdue ? 'bg-red-lighten-5' : 'bg-grey-lighten-5'"
              >
                <v-icon
                  :icon="task.is_completed ? 'mdi-check-circle' : task.is_overdue ? 'mdi-alert-circle' : 'mdi-circle-outline'"
                  :color="task.is_completed ? 'success' : task.is_overdue ? 'error' : 'grey'"
                  size="18"
                />
                <div class="flex-grow-1 overflow-hidden">
                  <div class="text-body-2 font-weight-medium text-truncate">{{ task.name }}</div>
                  <div class="text-caption text-grey">{{ task.project_name }}</div>
                </div>
                <div class="d-flex flex-column align-end gap-1" style="min-width:80px">
                  <v-chip
                    v-if="task.status"
                    size="x-small"
                    :style="{ backgroundColor: task.status.color + '22', color: task.status.color }"
                    class="font-weight-medium"
                  >
                    {{ task.status.name }}
                  </v-chip>
                  <span class="text-caption" :class="task.is_overdue ? 'text-error' : 'text-grey'">
                    {{ task.end_date ?? '—' }}
                  </span>
                </div>
                <div style="min-width:50px">
                  <div class="text-caption text-right mb-1">{{ task.progress }}%</div>
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
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'

const props = defineProps<{ userId: number }>()
const emit = defineEmits<{ close: [] }>()

interface MemberSummary {
  total: number; completed: number; pending: number; in_progress: number
  overdue: number; due_soon: number; overdue_rate: number; avg_progress: number; project_count: number
}
interface ProjectRow { project_id: number; project_name: string; total: number; completed: number; overdue: number }
interface TaskRow {
  id: number; name: string; project_name: string; end_date: string | null
  progress: number; is_completed: boolean; is_overdue: boolean
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
const taskFilter = ref('all')

const taskTabs = [
  { label: '全部',   value: 'all' },
  { label: '逾期',   value: 'overdue' },
  { label: '待處理', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
]

const summaryCards = computed(() => {
  if (!data.value) return []
  const s = data.value.summary
  return [
    { label: '全部任務',  value: s.total,       color: 'primary' },
    { label: '待處理',    value: s.pending,      color: 'default' },
    { label: '進行中',    value: s.in_progress,  color: 'info' },
    { label: '已完成',    value: s.completed,    color: 'success' },
    { label: '逾期',      value: s.overdue,      color: s.overdue > 0 ? 'error' : 'default' },
    { label: '本週到期',  value: s.due_soon,     color: s.due_soon > 0 ? 'warning' : 'default' },
  ]
})

const filteredTasks = computed(() => {
  const tasks = data.value?.tasks ?? []
  if (taskFilter.value === 'overdue')     return tasks.filter(t => t.is_overdue)
  if (taskFilter.value === 'pending')     return tasks.filter(t => !t.is_completed && t.progress === 0 && !t.is_overdue)
  if (taskFilter.value === 'in_progress') return tasks.filter(t => !t.is_completed && t.progress > 0)
  if (taskFilter.value === 'completed')   return tasks.filter(t => t.is_completed)
  return tasks
})

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
