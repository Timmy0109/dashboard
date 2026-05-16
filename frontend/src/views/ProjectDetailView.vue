<template>
  <div v-if="store.detailLoading" class="flex items-center justify-center py-20">
    <div class="text-gray-400 text-sm">載入中...</div>
  </div>

  <div v-else-if="project">
    <!-- Back + Header -->
    <div class="mb-5">
      <button @click="router.push('/projects')" class="text-sm text-gray-400 hover:text-gray-600 mb-3 flex items-center gap-1">
        <span class="material-icons text-base leading-none">arrow_back</span> 返回專案列表
      </button>
      <div class="flex items-start justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-gray-900">{{ project.name }}</h2>
            <span
              v-if="project.status"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
              :style="{ backgroundColor: project.status.color + '20', color: project.status.color }"
            >
              <span class="material-icons text-xs leading-none">{{ project.status.icon }}</span>
              {{ project.status.name }}
            </span>
          </div>
          <p v-if="project.project_no" class="text-xs text-gray-400 mt-0.5">{{ project.project_no }}</p>
        </div>
        <div class="flex items-center gap-3">
          <!-- WebSocket status indicator -->
          <div class="flex items-center gap-1.5 text-xs" :class="wsConnected ? 'text-green-600' : 'text-gray-400'">
            <span class="w-1.5 h-1.5 rounded-full inline-block" :class="wsConnected ? 'bg-green-500 animate-pulse' : 'bg-gray-300'"></span>
            {{ wsConnected ? '即時連線' : '連線中...' }}
          </div>
          <button
            v-if="auth.isManager"
            @click="showTaskModal = true; editingTask = null"
            class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            <span class="material-icons text-base leading-none">add</span> 新增任務
          </button>
        </div>
      </div>
    </div>

    <!-- Info Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400 mb-1">整體進度</p>
        <div class="flex items-end gap-2">
          <span class="text-2xl font-bold text-gray-900">{{ project.progress_percent }}%</span>
        </div>
        <div class="mt-2 bg-gray-100 rounded-full h-1.5">
          <div
            class="h-1.5 rounded-full transition-all"
            :class="project.progress_percent >= 100 ? 'bg-green-500' : 'bg-blue-500'"
            :style="{ width: project.progress_percent + '%' }"
          />
        </div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400 mb-1">任務總數</p>
        <p class="text-2xl font-bold text-gray-900">{{ project.tasks.length }}</p>
        <p class="text-xs text-gray-400 mt-1">已完成 {{ completedCount }} / {{ project.tasks.length }}</p>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400 mb-1">開始日期</p>
        <p class="text-sm font-semibold text-gray-800">{{ project.start_date?.slice(0, 10) }}</p>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400 mb-1">預計結束</p>
        <p
          class="text-sm font-semibold"
          :class="dueDateClass"
        >
          {{ project.due_date?.slice(0, 10) ?? '未設定' }}
        </p>
      </div>
    </div>

    <!-- Gantt Chart -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
      <h3 class="text-sm font-semibold text-gray-700 mb-4">甘特圖</h3>
      <div v-if="project.tasks.length === 0" class="py-8 text-center text-sm text-gray-400">
        尚無任務，新增任務後甘特圖將自動顯示
      </div>
      <GanttChart
        v-else
        :tasks="project.tasks"
        @task-click="openEditTask"
        @task-date-change="handleGanttDateChange"
      />
    </div>

    <!-- Task Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700">任務列表</h3>
      </div>
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">任務名稱</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">負責人</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">開始</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">結束</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">優先級</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 w-32">進度</th>
            <th class="px-4 py-3 w-16"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="task in project.tasks"
            :key="task.id"
            class="hover:bg-gray-50 transition-colors"
          >
            <td class="px-4 py-3">
              <span class="text-sm font-medium text-gray-800">{{ task.name }}</span>
            </td>
            <td class="px-4 py-3">
              <div v-if="task.assignee" class="flex items-center gap-1.5">
                <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium shrink-0">
                  {{ task.assignee.name.charAt(0) }}
                </div>
                <span class="text-xs text-gray-600">{{ task.assignee.name }}</span>
              </div>
              <span v-else class="text-xs text-gray-400">—</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-600">{{ task.start_date.slice(0, 10) }}</td>
            <td class="px-4 py-3">
              <span
                class="text-xs"
                :class="isTaskOverdue(task) ? 'text-red-600 font-medium' : 'text-gray-600'"
              >
                {{ task.end_date.slice(0, 10) }}
                <span v-if="isTaskOverdue(task)" class="material-icons text-sm leading-none align-middle ml-0.5">warning</span>
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                v-if="task.status"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                :style="{ backgroundColor: task.status.color + '20', color: task.status.color }"
              >
                <span class="material-icons text-xs leading-none">{{ task.status.icon }}</span>
                {{ task.status.name }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                v-if="task.priority"
                class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                :style="{ backgroundColor: task.priority.color + '20', color: task.priority.color }"
              >
                {{ task.priority.name }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                  <div
                    class="h-1.5 rounded-full"
                    :class="task.is_completed ? 'bg-green-500' : 'bg-blue-500'"
                    :style="{ width: task.progress + '%' }"
                  />
                </div>
                <span class="text-xs text-gray-400 w-7 text-right">{{ task.progress }}%</span>
              </div>
            </td>
            <td class="px-4 py-3" v-if="auth.isManager">
              <div class="flex gap-1">
                <button
                  @click="openEditTask(task)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                ><span class="material-icons text-base leading-none">edit</span></button>
                <button
                  @click="handleDeleteTask(task)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                ><span class="material-icons text-base leading-none">delete</span></button>
              </div>
            </td>
            <td class="px-4 py-3" v-else></td>
          </tr>
          <tr v-if="project.tasks.length === 0">
            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-400">
              目前沒有任務
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Task Modal -->
    <TaskModal
      v-if="showTaskModal"
      :task="editingTask"
      :project-id="project.id"
      @close="showTaskModal = false"
      @saved="showTaskModal = false"
    />
  </div>

  <div v-else class="py-20 text-center text-sm text-gray-400">
    找不到專案
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore, type Task } from '@/stores/project'
import GanttChart from '@/components/GanttChart.vue'
import TaskModal from '@/components/TaskModal.vue'
import getEcho from '@/lib/echo'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useProjectStore()

const showTaskModal = ref(false)
const editingTask = ref<Task | null>(null)
const wsConnected = ref(false)

const project = computed(() => store.current)
const completedCount = computed(() => project.value?.tasks.filter(t => t.is_completed).length ?? 0)

const dueDateClass = computed(() => {
  if (!project.value?.due_date || project.value.is_completed) return 'text-gray-800'
  return new Date(project.value.due_date) < new Date() ? 'text-red-600' : 'text-gray-800'
})

function isTaskOverdue(task: Task) {
  return !task.is_completed && new Date(task.end_date) < new Date()
}

function openEditTask(task: Task) {
  editingTask.value = task
  showTaskModal.value = true
}

async function handleDeleteTask(task: Task) {
  if (!confirm(`確定要刪除任務「${task.name}」？`)) return
  if (project.value) {
    await store.deleteTask(project.value.id, task.id)
  }
}

async function handleGanttDateChange(taskId: number, start: string, end: string) {
  if (!project.value) return
  await store.updateTask(project.value.id, taskId, { start_date: start, end_date: end })
}

// ── WebSocket ────────────────────────────────────────────────────────────────
let channelLeave: (() => void) | null = null

function subscribeToChannel(projectId: number) {
  const echo = getEcho()
  if (!echo) return  // Reverb not configured — skip WebSocket

  const channel = echo.private(`project.${projectId}`)

  channel.subscribed(() => { wsConnected.value = true })

  channel.listen('.TaskSaved', (data: { task: Task }) => {
    if (!store.current || store.current.id !== projectId) return
    const idx = store.current.tasks.findIndex(t => t.id === data.task.id)
    if (idx !== -1) {
      store.current.tasks[idx] = data.task
    } else {
      store.current.tasks.push(data.task)
    }
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
