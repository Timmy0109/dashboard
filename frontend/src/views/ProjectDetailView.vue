<template>
  <div v-if="store.detailLoading" class="d-flex justify-center align-center py-16">
    <v-progress-circular indeterminate color="primary" />
  </div>

  <div v-else-if="project">
    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="mb-5">
      <v-btn
        variant="text" color="grey" prepend-icon="mdi-arrow-left"
        size="small" class="mb-3 px-0"
        @click="router.push('/projects')"
      >返回專案列表</v-btn>

      <v-card rounded="xl" class="pa-5" elevation="1">
        <div class="d-flex align-start justify-space-between gap-4 flex-wrap">
          <div class="flex-grow-1">
            <div class="d-flex align-center gap-3 flex-wrap mb-1">
              <h2 class="text-h6 font-weight-bold">{{ project.name }}</h2>
              <v-chip
                v-if="project.status"
                size="small"
                :style="{ backgroundColor: project.status.color + '22', color: project.status.color }"
                class="font-weight-medium"
              >
                <v-icon :icon="statusIcon(project.status.icon)" size="12" class="mr-1" />
                {{ project.status.name }}
              </v-chip>
            </div>
            <div v-if="project.project_no" class="text-caption text-grey">{{ project.project_no }}</div>
          </div>

          <div class="d-flex align-center gap-2 flex-shrink-0">
            <!-- WebSocket indicator -->
            <v-chip
              size="x-small"
              :color="wsConnected ? 'success' : 'grey'"
              variant="tonal"
              :prepend-icon="wsConnected ? 'mdi-wifi' : 'mdi-wifi-off'"
            >
              {{ wsConnected ? '即時連線' : '連線中...' }}
            </v-chip>
            <v-btn
              v-if="auth.canManageMembers"
              color="primary" prepend-icon="mdi-plus" rounded="lg"
              @click="showTaskModal = true; editingTask = null"
            >新增任務</v-btn>
          </div>
        </div>

        <!-- Progress bar spanning full width -->
        <div class="mt-4">
          <div class="d-flex justify-space-between mb-1">
            <span class="text-caption text-grey">整體進度</span>
            <span class="text-caption font-weight-bold" :class="project.progress_percent >= 100 ? 'text-success' : 'text-primary'">
              {{ project.progress_percent }}%
            </span>
          </div>
          <v-progress-linear
            :model-value="project.progress_percent"
            :color="project.progress_percent >= 100 ? 'success' : 'primary'"
            bg-color="grey-lighten-3"
            rounded
            height="8"
          />
        </div>
      </v-card>
    </div>

    <!-- ── Info Cards ──────────────────────────────────────────────────── -->
    <v-row class="mb-5" dense>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" height="100%">
          <v-card-text class="pa-4">
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
              <span class="text-caption text-grey">任務總數</span>
            </div>
            <div class="text-h5 font-weight-bold">{{ project.tasks.length }}</div>
            <div class="text-caption text-grey mt-1">
              已完成 <span class="text-success font-weight-medium">{{ completedCount }}</span> / {{ project.tasks.length }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" height="100%">
          <v-card-text class="pa-4">
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-alert-circle" size="18" color="error" />
              <span class="text-caption text-grey">逾期任務</span>
            </div>
            <div class="text-h5 font-weight-bold" :class="overdueCount > 0 ? 'text-error' : ''">{{ overdueCount }}</div>
            <div class="text-caption text-grey mt-1">
              {{ overdueCount > 0 ? '需要立即處理' : '目前無逾期' }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" height="100%">
          <v-card-text class="pa-4">
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-calendar-start" size="18" color="grey" />
              <span class="text-caption text-grey">開始日期</span>
            </div>
            <div class="text-body-1 font-weight-semibold">{{ project.start_date?.slice(0, 10) ?? '—' }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" height="100%">
          <v-card-text class="pa-4">
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-calendar-end" size="18" :color="dueDateUrgency" />
              <span class="text-caption text-grey">預計結束</span>
            </div>
            <div class="text-body-1 font-weight-semibold" :class="dueDateClass">
              {{ project.due_date?.slice(0, 10) ?? '未設定' }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Gantt Chart ─────────────────────────────────────────────────── -->
    <v-card rounded="xl" class="mb-5">
      <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3 d-flex align-center gap-2">
        <v-icon icon="mdi-chart-gantt" size="18" color="primary" />
        甘特圖
      </v-card-title>
      <v-divider />
      <v-card-text class="pt-4">
        <div v-if="project.tasks.length === 0" class="py-8 text-center text-body-2 text-grey">
          尚無任務，新增任務後甘特圖將自動顯示
        </div>
        <GanttChart
          v-else
          :tasks="project.tasks"
          @task-click="openEditTask"
          @task-date-change="handleGanttDateChange"
        />
      </v-card-text>
    </v-card>

    <!-- ── Task Table ──────────────────────────────────────────────────── -->
    <v-card rounded="xl">
      <v-data-table
        :headers="taskHeaders"
        :items="filteredTasks"
        :search="taskSearch"
        hover
        item-value="id"
      >
        <template #top>
          <v-toolbar flat rounded="t-xl">
            <div class="d-flex align-center gap-3 pa-3 w-100 flex-wrap">
              <div class="d-flex align-center gap-2">
                <v-icon icon="mdi-format-list-bulleted" size="18" color="grey-darken-1" />
                <span class="text-body-2 font-weight-semibold text-grey-darken-3">任務列表</span>
              </div>
              <v-text-field
                v-model="taskSearch"
                prepend-inner-icon="mdi-magnify"
                placeholder="搜尋任務..."
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                style="max-width:220px"
              />
              <v-chip-group v-model="taskTab" mandatory selected-class="bg-primary text-white">
                <v-chip v-for="tab in taskTabs" :key="tab.value" :value="tab.value" size="small" filter>
                  {{ tab.label }}（{{ getTabCount(tab.value) }}）
                </v-chip>
              </v-chip-group>
            </div>
          </v-toolbar>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-icon
              v-if="item.is_completed"
              icon="mdi-check-circle"
              size="16"
              color="success"
            />
            <v-icon
              v-else-if="isTaskOverdue(item)"
              icon="mdi-alert-circle"
              size="16"
              color="error"
            />
            <span :class="item.is_completed ? 'text-grey text-decoration-line-through' : 'text-body-2 font-weight-medium'">
              {{ item.name }}
            </span>
          </div>
        </template>

        <template #item.assignee="{ item }">
          <div v-if="item.assignee" class="d-flex align-center gap-2">
            <v-avatar color="primary" size="26">
              <span class="text-caption text-white font-weight-bold">{{ item.assignee.name.charAt(0) }}</span>
            </v-avatar>
            <span class="text-body-2">{{ item.assignee.name }}</span>
          </div>
          <span v-else class="text-grey text-body-2">—</span>
        </template>

        <template #item.start_date="{ item }">
          <span class="text-body-2">{{ item.start_date.slice(0, 10) }}</span>
        </template>

        <template #item.end_date="{ item }">
          <span :class="isTaskOverdue(item) ? 'text-error font-weight-medium' : 'text-body-2'">
            {{ item.end_date.slice(0, 10) }}
          </span>
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

        <template #item.progress="{ item }">
          <div class="d-flex align-center gap-2" style="min-width:110px">
            <v-progress-linear
              :model-value="item.progress"
              :color="item.is_completed ? 'success' : isTaskOverdue(item) ? 'error' : 'primary'"
              bg-color="grey-lighten-3"
              rounded
              height="5"
              class="flex-grow-1"
            />
            <span class="text-caption text-grey-darken-1">{{ item.progress }}%</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div v-if="auth.canManageMembers" class="d-flex gap-1" @click.stop>
            <v-btn icon="mdi-pencil" size="small" variant="text" color="grey" @click="openEditTask(item)" />
            <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="handleDeleteTask(item)" />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-8 text-grey">
            {{
              taskTab === 'overdue'     ? '沒有逾期任務' :
              taskTab === 'completed'   ? '尚無已完成任務' :
              taskTab === 'pending'     ? '沒有待處理任務' :
              taskTab === 'in_progress' ? '沒有進行中任務' :
              '目前沒有任務'
            }}
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Task Modal -->
    <TaskModal
      v-if="showTaskModal"
      :task="editingTask"
      :project-id="project.id"
      @close="showTaskModal = false"
      @saved="showTaskModal = false"
    />
  </div>

  <div v-else class="py-20 text-center text-body-2 text-grey">找不到專案</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore, type Task } from '@/stores/project'
import GanttChart from '@/components/GanttChart.vue'
import TaskModal from '@/components/TaskModal.vue'
import getEcho from '@/lib/echo'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const store  = useProjectStore()

const showTaskModal = ref(false)
const editingTask   = ref<Task | null>(null)
const wsConnected   = ref(false)
const taskSearch    = ref('')

const project        = computed(() => store.current)
const completedCount = computed(() => project.value?.tasks.filter(t => t.is_completed).length ?? 0)
const overdueCount   = computed(() => project.value?.tasks.filter(t => isTaskOverdue(t)).length ?? 0)

const dueDateClass = computed(() => {
  if (!project.value?.due_date || project.value.is_completed) return ''
  return new Date(project.value.due_date) < new Date() ? 'text-error font-weight-bold' : ''
})

const dueDateUrgency = computed(() => {
  if (!project.value?.due_date || project.value.is_completed) return 'grey'
  return new Date(project.value.due_date) < new Date() ? 'error' : 'grey'
})

// Task tabs
const taskTab  = ref('all')
const taskTabs = [
  { label: '全部',   value: 'all' },
  { label: '待處理', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '逾期',   value: 'overdue' },
]

const filteredTasks = computed(() => {
  const tasks = project.value?.tasks ?? []
  if (taskTab.value === 'completed')   return tasks.filter(t => t.is_completed)
  if (taskTab.value === 'overdue')     return tasks.filter(t => isTaskOverdue(t))
  if (taskTab.value === 'pending')     return tasks.filter(t => t.progress === 0 && !t.is_completed)
  if (taskTab.value === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed)
  return tasks
})

function getTabCount(tab: string) {
  const tasks = project.value?.tasks ?? []
  if (tab === 'completed')   return tasks.filter(t => t.is_completed).length
  if (tab === 'overdue')     return tasks.filter(t => isTaskOverdue(t)).length
  if (tab === 'pending')     return tasks.filter(t => t.progress === 0 && !t.is_completed).length
  if (tab === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed).length
  return tasks.length
}

const taskHeaders = [
  { title: '任務名稱', key: 'name',       sortable: true },
  { title: '負責人',  key: 'assignee',   sortable: false },
  { title: '開始',   key: 'start_date', sortable: true },
  { title: '結束',   key: 'end_date',   sortable: true },
  { title: '狀態',   key: 'status',     sortable: false },
  { title: '優先級',  key: 'priority',   sortable: false },
  { title: '進度',   key: 'progress',   sortable: true, width: '150px' },
  { title: '',      key: 'actions',    sortable: false, width: '80px' },
]

function isTaskOverdue(task: Task) {
  return !task.is_completed && new Date(task.end_date) < new Date()
}

function statusIcon(icon: string) {
  return icon.startsWith('mdi-') ? icon : `mdi-${icon}`
}

function openEditTask(task: Task) {
  editingTask.value  = task
  showTaskModal.value = true
}

async function handleDeleteTask(task: Task) {
  if (!confirm(`確定要刪除任務「${task.name}」？`)) return
  if (project.value) await store.deleteTask(project.value.id, task.id)
}

async function handleGanttDateChange(taskId: number, start: string, end: string) {
  if (!project.value) return
  await store.updateTask(project.value.id, taskId, { start_date: start, end_date: end })
}

// WebSocket
let channelLeave: (() => void) | null = null

function subscribeToChannel(projectId: number) {
  const echo = getEcho()
  if (!echo) return

  const channel = echo.private(`project.${projectId}`)
  channel.subscribed(() => { wsConnected.value = true })

  channel.listen('.TaskSaved', (data: { task: Task }) => {
    if (!store.current || store.current.id !== projectId) return
    const idx = store.current.tasks.findIndex(t => t.id === data.task.id)
    if (idx !== -1) store.current.tasks[idx] = data.task
    else store.current.tasks.push(data.task)
  })

  channel.listen('.TaskDeleted', (data: { task_id: number }) => {
    if (!store.current || store.current.id !== projectId) return
    store.current.tasks = store.current.tasks.filter(t => t.id !== data.task_id)
  })

  channel.listen('.ProjectProgressUpdated', (data: { project_id: number; progress_percent: number }) => {
    if (!store.current || store.current.id !== data.project_id) return
    store.current.progress_percent = data.progress_percent
  })

  channelLeave = () => echo.leave(`project.${projectId}`)
}

onMounted(async () => {
  const id = Number(route.params.id)
  await store.fetchDetail(id)
  subscribeToChannel(id)
})

onBeforeUnmount(() => {
  channelLeave?.()
  wsConnected.value = false
})
</script>
