<template>
  <div>
    <!-- Header -->
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h5 font-weight-bold">每日任務</h2>
        <p class="text-body-2 text-medium-emphasis mt-1">
          所有與我相關的任務，依截止日期排序
        </p>
      </div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openAdd">
        新增任務
      </v-btn>
    </div>

    <!-- Charts strip：狀態 Donut / 優先級 HBar / 截止 VBar -->
    <v-row class="mb-4" dense>
      <v-col cols="12" md="4">
        <v-card rounded="xl" class="pa-4 h-100">
          <div class="text-subtitle-2 font-weight-bold mb-3">任務狀態</div>
          <div class="d-flex align-center" style="gap: 16px">
            <DonutChart
              :data="statusDonutData"
              :size="160"
              :stroke="20"
              :center-label="String(store.tasks.length)"
              center-sub="總任務"
            />
            <div class="d-flex flex-column gap-2 flex-grow-1">
              <div
                v-for="d in statusDonutData"
                :key="d.label"
                class="d-flex align-center text-caption"
              >
                <span
                  class="rounded-circle mr-2 d-inline-block"
                  :style="{ width: '10px', height: '10px', backgroundColor: d.color }"
                />
                <span class="flex-grow-1">{{ d.label }}</span>
                <span class="pms-tnum text-medium-emphasis">{{ d.value }}</span>
              </div>
            </div>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card rounded="xl" class="pa-4 h-100">
          <div class="text-subtitle-2 font-weight-bold mb-3">優先級分佈</div>
          <HBarChart :data="priorityBarData" />
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card rounded="xl" class="pa-4 h-100">
          <div class="text-subtitle-2 font-weight-bold mb-3">截止時間</div>
          <VBarChart :data="dueBarData" :height="180" color="primary" />
        </v-card>
      </v-col>
    </v-row>

    <!-- Main task list -->
    <v-card rounded="xl">
      <div class="pa-4 d-flex align-center justify-space-between flex-wrap gap-3 border-b">
        <ChipGroup
          v-model="activeTab"
          :items="tabItems"
        />
        <v-text-field
          v-model="search"
          placeholder="搜尋任務名稱或專案…"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          hide-details
          rounded="lg"
          style="max-width: 280px"
        />
      </div>

      <template v-if="store.loading">
        <div class="pa-8 d-flex justify-center">
          <v-progress-circular indeterminate color="primary" />
        </div>
      </template>

      <template v-else-if="filteredTasks.length === 0">
        <EmptyState
          :icon="emptyIcon"
          :title="emptyTitle"
          :sub="emptySub"
        >
          <template #action>
            <v-btn
              v-if="activeTab === 'all' && !search"
              color="primary"
              variant="flat"
              class="mt-4"
              prepend-icon="mdi-plus"
              @click="openAdd"
            >
              新增第一筆任務
            </v-btn>
          </template>
        </EmptyState>
      </template>

      <v-data-table
        v-else
        :headers="headers"
        :items="filteredTasks"
        item-value="id"
        hover
        density="comfortable"
        :items-per-page="20"
        @click:row="(_e: Event, { item }: any) => { if (canEditTask(item)) openEdit(item) }"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2">
            <v-icon
              v-if="item.is_completed"
              icon="mdi-check-circle"
              size="16"
              color="success"
            />
            <v-icon
              v-else-if="item.is_overdue"
              icon="mdi-alert-circle"
              size="16"
              color="error"
            />
            <v-icon v-else icon="mdi-circle-outline" size="16" class="text-medium-emphasis" />
            <span
              class="font-weight-medium"
              :class="{ 'text-decoration-line-through text-medium-emphasis': item.is_completed }"
            >
              {{ item.name }}
            </span>
            <TaskMetaBadges
              :attachments-count="(item as any).attachments_count"
              :comments-count="(item as any).comments_count"
            />
          </div>
        </template>

        <template #item.project_name="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ item.project_name }}</span>
        </template>

        <template #item.assignee="{ item }">
          <div v-if="item.assignee" class="d-flex align-center gap-2">
            <v-avatar color="primary" size="26">
              <span class="text-caption text-white font-weight-bold">
                {{ item.assignee.name.charAt(0) }}
              </span>
            </v-avatar>
            <span class="text-body-2">{{ item.assignee.name }}</span>
          </div>
          <span v-else class="text-medium-emphasis text-body-2">未指派</span>
        </template>

        <template #item.end_date="{ item }">
          <span
            class="pms-tnum"
            :class="item.is_overdue && !item.is_completed ? 'text-error font-weight-medium' : 'text-body-2'"
          >
            {{ item.end_date }}
          </span>
        </template>

        <template #item.priority="{ item }">
          <v-chip
            v-if="item.priority"
            size="small"
            :style="{ backgroundColor: item.priority.color + '22', color: item.priority.color }"
            class="font-weight-medium"
          >
            {{ item.priority.name }}
          </v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip
            v-if="item.status"
            size="small"
            :style="{ backgroundColor: item.status.color + '22', color: item.status.color }"
            class="font-weight-medium"
          >
            <v-icon :icon="statusIcon(item.status.icon)" size="12" class="mr-1" />
            {{ item.status.name }}
          </v-chip>
        </template>

        <template #item.progress="{ item }">
          <div class="d-flex align-center gap-2" style="min-width: 110px">
            <v-progress-linear
              :model-value="item.progress"
              :color="item.is_overdue && !item.is_completed ? 'error' : item.is_completed ? 'success' : 'primary'"
              bg-color="grey-lighten-3"
              rounded
              height="6"
              class="flex-grow-1"
            />
            <span class="text-caption text-medium-emphasis pms-tnum">{{ item.progress }}%</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div v-if="canEditTask(item)" class="d-flex gap-1" @click.stop>
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              color="grey"
              @click="openEdit(item)"
            />
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="handleDelete(item)"
            />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Project picker dialog -->
    <v-dialog v-model="showProjectPicker" max-width="360">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3">選擇所屬專案</v-card-title>
        <v-card-text class="pa-5 pt-0">
          <v-select
            v-model="selectedProjectId"
            label="專案"
            :items="projectStore.list.map(p => ({ title: p.name, value: p.id }))"
            variant="outlined"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 gap-2">
          <v-btn
            variant="outlined"
            color="grey"
            class="flex-grow-1"
            @click="showProjectPicker = false"
          >
            取消
          </v-btn>
          <v-btn
            color="primary"
            class="flex-grow-1"
            :disabled="!selectedProjectId"
            @click="confirmProjectPick"
          >
            繼續
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <TaskModal
      v-if="showTaskModal && activeProjectId !== null"
      :task="editingTask"
      :project-id="activeProjectId"
      @close="showTaskModal = false"
      @saved="onTaskSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTodoStore, type TodoTask } from '@/stores/todo'
import { useProjectStore } from '@/stores/project'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import TaskModal from '@/components/TaskModal.vue'
import ChipGroup from '@/components/ui/ChipGroup.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import TaskMetaBadges from '@/components/ui/TaskMetaBadges.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import HBarChart from '@/components/charts/HBarChart.vue'
import VBarChart from '@/components/charts/VBarChart.vue'
import type { Task } from '@/stores/project'

const store = useTodoStore()
const projectStore = useProjectStore()
const auth = useAuthStore()
const toast = useToast()

type TabKey = 'all' | 'pending' | 'in_progress' | 'completed' | 'overdue'

const activeTab = ref<TabKey>('all')
const search = ref('')
const showProjectPicker = ref(false)
const showTaskModal = ref(false)
const editingTask = ref<Task | null>(null)
const activeProjectId = ref<number | null>(null)
const selectedProjectId = ref<number | null>(null)

const headers = [
  { title: '任務名稱', key: 'name', sortable: true },
  { title: '所屬專案', key: 'project_name', sortable: true },
  { title: '負責人', key: 'assignee', sortable: false },
  { title: '截止日期', key: 'end_date', sortable: true },
  { title: '優先級', key: 'priority', sortable: false },
  { title: '狀態', key: 'status', sortable: false },
  { title: '進度', key: 'progress', sortable: true, width: '160px' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
] as const

function countByTab(tab: TabKey) {
  const tasks = store.tasks
  if (tab === 'completed') return tasks.filter(t => t.is_completed).length
  if (tab === 'overdue') return tasks.filter(t => t.is_overdue && !t.is_completed).length
  if (tab === 'pending') return tasks.filter(t => t.progress === 0 && !t.is_completed).length
  if (tab === 'in_progress')
    return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed).length
  return tasks.length
}

const tabItems = computed(() => [
  { label: '全部', value: 'all' as TabKey, count: countByTab('all') },
  { label: '待處理', value: 'pending' as TabKey, count: countByTab('pending') },
  { label: '進行中', value: 'in_progress' as TabKey, count: countByTab('in_progress') },
  { label: '已完成', value: 'completed' as TabKey, count: countByTab('completed') },
  { label: '逾期', value: 'overdue' as TabKey, count: countByTab('overdue') },
])

const filteredTasks = computed<TodoTask[]>(() => {
  let tasks = store.tasks
  if (activeTab.value === 'completed') tasks = tasks.filter(t => t.is_completed)
  else if (activeTab.value === 'overdue') tasks = tasks.filter(t => t.is_overdue && !t.is_completed)
  else if (activeTab.value === 'pending')
    tasks = tasks.filter(t => t.progress === 0 && !t.is_completed)
  else if (activeTab.value === 'in_progress')
    tasks = tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed)

  const q = search.value.trim().toLowerCase()
  if (q) {
    tasks = tasks.filter(
      t =>
        t.name.toLowerCase().includes(q) ||
        (t.project_name || '').toLowerCase().includes(q),
    )
  }
  return tasks
})

// Charts data
const statusDonutData = computed(() => {
  const tasks = store.tasks
  const completed = tasks.filter(t => t.is_completed).length
  const overdue = tasks.filter(t => t.is_overdue && !t.is_completed).length
  const inProgress = tasks.filter(
    t => t.progress > 0 && t.progress < 100 && !t.is_completed,
  ).length
  const pending = tasks.filter(t => t.progress === 0 && !t.is_completed).length
  return [
    { label: '已完成', value: completed, color: '#10b981' },
    { label: '進行中', value: inProgress, color: '#3b82f6' },
    { label: '待處理', value: pending, color: '#9ca3af' },
    { label: '逾期', value: overdue, color: '#ef4444' },
  ].filter(d => d.value > 0)
})

const priorityBarData = computed(() => {
  const buckets = new Map<string, { value: number; color?: string }>()
  for (const t of store.tasks) {
    const name = t.priority?.name ?? '未設定'
    const color = t.priority?.color
    const entry = buckets.get(name) ?? { value: 0, color }
    entry.value += 1
    if (color) entry.color = color
    buckets.set(name, entry)
  }
  return Array.from(buckets.entries()).map(([label, v]) => ({
    label,
    value: v.value,
    color: v.color,
  }))
})

const dueBarData = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const buckets = { overdue: 0, today: 0, d3: 0, d7: 0, later: 0 }
  for (const t of store.tasks) {
    if (t.is_completed) continue
    if (t.is_overdue) {
      buckets.overdue += 1
      continue
    }
    const due = new Date(t.end_date)
    due.setHours(0, 0, 0, 0)
    const diff = Math.round((due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
    if (diff <= 0) buckets.today += 1
    else if (diff <= 3) buckets.d3 += 1
    else if (diff <= 7) buckets.d7 += 1
    else buckets.later += 1
  }
  return [
    { label: '已逾期', value: buckets.overdue },
    { label: '今天', value: buckets.today },
    { label: '3 天內', value: buckets.d3 },
    { label: '7 天內', value: buckets.d7 },
    { label: '之後', value: buckets.later },
  ]
})

// Empty state
const emptyIcon = computed(() => {
  if (search.value) return 'mdi-magnify'
  if (activeTab.value === 'overdue') return 'mdi-check-circle-outline'
  if (activeTab.value === 'completed') return 'mdi-check-circle-outline'
  return 'mdi-clipboard-text-outline'
})
const emptyTitle = computed(() => {
  if (search.value) return '找不到符合的任務'
  if (activeTab.value === 'overdue') return '沒有逾期任務'
  if (activeTab.value === 'completed') return '尚無已完成任務'
  if (activeTab.value === 'pending') return '沒有待處理任務'
  if (activeTab.value === 'in_progress') return '沒有進行中任務'
  return '目前沒有待辦任務'
})
const emptySub = computed(() => {
  if (search.value) return '試試換個關鍵字或清除篩選'
  if (activeTab.value === 'all') return '建立第一筆任務，開始追蹤工作進度'
  return '切換上方分頁看看其他狀態'
})

function canEditTask(task: TodoTask) {
  if (auth.isAdmin || auth.isManager) return true
  return task.assignee?.id === auth.user?.id
}

function statusIcon(icon: string) {
  return icon.startsWith('mdi-') ? icon : `mdi-${icon}`
}

function todoTaskToTask(t: TodoTask): Task {
  return {
    id: t.id,
    project_id: t.project_id,
    name: t.name,
    note: t.note,
    start_date: t.start_date,
    end_date: t.end_date,
    progress: t.progress,
    is_completed: t.is_completed,
    assignee: t.assignee,
    status: t.status
      ? { id: t.status.id, name: t.status.name, icon: t.status.icon, color: t.status.color }
      : null,
    priority: t.priority,
  }
}

function openAdd() {
  if (projectStore.list.length === 0) {
    toast.error('請先建立專案')
    return
  }
  selectedProjectId.value = null
  showProjectPicker.value = true
}

function confirmProjectPick() {
  if (!selectedProjectId.value) return
  activeProjectId.value = selectedProjectId.value
  editingTask.value = null
  showProjectPicker.value = false
  showTaskModal.value = true
}

function openEdit(task: TodoTask) {
  editingTask.value = todoTaskToTask(task)
  activeProjectId.value = task.project_id
  showTaskModal.value = true
}

async function handleDelete(task: TodoTask) {
  if (!confirm(`確定要刪除任務「${task.name}」？`)) return
  try {
    await store.deleteTask(task.project_id, task.id)
    toast.success('已刪除')
  } catch {
    toast.error('刪除失敗')
  }
}

async function onTaskSaved() {
  showTaskModal.value = false
  await store.fetch()
  toast.success('儲存成功')
}

onMounted(async () => {
  await Promise.all([store.fetch(), projectStore.fetchList()])
})
</script>

<style scoped>
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.border-b {
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.gap-2 {
  gap: 8px;
}
.gap-3 {
  gap: 12px;
}
</style>
