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
          <!-- Summary stat cards：參與專案 / 已完成 / 未完成 / 逾期 -->
          <div class="pa-5 pb-4">
            <v-row dense>
              <v-col v-for="card in summaryCards" :key="card.label" cols="6" sm="3">
                <v-card rounded="xl" variant="tonal" :color="card.color" class="text-center pa-3">
                  <div class="text-h5 font-weight-bold">{{ card.value }}</div>
                  <div class="text-caption mt-1">{{ card.label }}</div>
                </v-card>
              </v-col>
            </v-row>
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
                <div class="grow overflow-hidden">
                  <div class="text-body-2 font-weight-medium text-truncate">{{ task.name }}</div>
                  <div class="text-caption text-grey">{{ task.project_name }}</div>
                </div>
                <span class="text-caption" :class="task.is_overdue ? 'text-error' : 'text-grey'" style="min-width:80px; text-align:right">
                  {{ task.end_date ?? '—' }}
                </span>
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

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'

const props = defineProps<{ userId: number }>()
const emit = defineEmits<{ close: []; edit: [userId: number] }>()

function onEditMember() {
  emit('edit', props.userId)
  emit('close')
}

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
    { label: '參與專案',  value: s.project_count,         color: 'primary' },
    { label: '已完成',    value: s.completed,             color: 'success' },
    { label: '未完成',    value: s.total - s.completed,   color: 'info' },
    { label: '逾期',      value: s.overdue,               color: s.overdue > 0 ? 'error' : 'default' },
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
