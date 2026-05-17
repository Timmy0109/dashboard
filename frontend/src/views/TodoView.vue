<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">今日待辦</h2>
        <p class="text-sm text-gray-500 mt-0.5">所有未完成任務，依截止日期排序</p>
      </div>
      <button
        @click="openAdd"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
      >
        <span class="material-icons text-base leading-none">add</span>
        新增任務
      </button>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-4">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="activeTab = tab.value"
        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
        :class="activeTab === tab.value
          ? 'bg-blue-600 text-white'
          : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'"
      >
        {{ tab.label }}
        <span class="ml-1 text-xs opacity-70">({{ getTabCount(tab.value) }})</span>
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="store.loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>

      <table v-else class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">任務名稱</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">所屬專案</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">負責人</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">截止日期</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">優先級</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 w-24">進度</th>
            <th class="px-4 py-3 w-20"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="task in filteredTasks"
            :key="task.id"
            class="hover:bg-gray-50 transition-colors cursor-pointer"
            @click="openEdit(task)"
          >
            <td class="px-4 py-3">
              <span class="text-sm text-gray-800 font-medium">{{ task.name }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="text-xs text-gray-500">{{ task.project_name }}</span>
            </td>
            <td class="px-4 py-3">
              <div v-if="task.assignee" class="flex items-center gap-1.5">
                <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium shrink-0">
                  {{ task.assignee.name.charAt(0) }}
                </div>
                <span class="text-xs text-gray-600">{{ task.assignee.name }}</span>
              </div>
              <span v-else class="text-xs text-gray-400">未指派</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="text-xs font-medium"
                :class="task.is_overdue ? 'text-red-600' : 'text-gray-600'"
              >
                <span v-if="task.is_overdue" class="material-icons text-sm leading-none align-middle mr-0.5">warning</span>{{ task.end_date }}
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
              <div class="flex items-center gap-1.5">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                  <div
                    class="h-1.5 rounded-full"
                    :class="task.is_overdue ? 'bg-red-400' : 'bg-blue-500'"
                    :style="{ width: task.progress + '%' }"
                  />
                </div>
                <span class="text-xs text-gray-400 w-7 text-right">{{ task.progress }}%</span>
              </div>
            </td>
            <td class="px-4 py-3" @click.stop>
              <div class="flex gap-1">
                <button
                  @click="openEdit(task)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                  title="編輯"
                >
                  <span class="material-icons text-base leading-none">edit</span>
                </button>
                <button
                  @click="handleDelete(task)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                  title="刪除"
                >
                  <span class="material-icons text-base leading-none">delete</span>
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="filteredTasks.length === 0">
            <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-400">
              {{
                activeTab === 'overdue' ? '沒有逾期任務' :
                activeTab === 'completed' ? '尚無已完成任務' :
                '目前沒有待辦任務'
              }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Legend -->
      <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 flex gap-4 text-xs text-gray-400">
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> 逾期</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span> 高優先級</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span> 進行中</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> 已完成</span>
      </div>
    </div>

    <!-- Project picker dialog (before adding a task) -->
    <div v-if="showProjectPicker" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showProjectPicker = false" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">選擇所屬專案</h3>
        <select
          v-model="selectedProjectId"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
        >
          <option value="">請選擇專案</option>
          <option v-for="p in projectStore.list" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="flex gap-3">
          <button @click="showProjectPicker = false"
            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">取消</button>
          <button @click="confirmProjectPick" :disabled="!selectedProjectId"
            class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">繼續</button>
        </div>
      </div>
    </div>

    <!-- Task Modal -->
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
import { useToast } from '@/composables/useToast'
import TaskModal from '@/components/TaskModal.vue'
import type { Task } from '@/stores/project'

const store = useTodoStore()
const projectStore = useProjectStore()
const toast = useToast()
const activeTab = ref('all')
const showProjectPicker = ref(false)
const showTaskModal = ref(false)
const editingTask = ref<Task | null>(null)
const activeProjectId = ref<number | null>(null)
const selectedProjectId = ref<number | ''>('')

const tabs = [
  { label: '全部', value: 'all' },
  { label: '待處理', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '逾期', value: 'overdue' },
]

const filteredTasks = computed(() => {
  if (activeTab.value === 'completed') return store.tasks.filter(t => t.is_completed)
  if (activeTab.value === 'overdue') return store.tasks.filter(t => t.is_overdue && !t.is_completed)
  if (activeTab.value === 'pending') return store.tasks.filter(t => t.progress === 0 && !t.is_completed)
  if (activeTab.value === 'in_progress') return store.tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed)
  return store.tasks
})

function getTabCount(tab: string) {
  if (tab === 'completed') return store.tasks.filter(t => t.is_completed).length
  if (tab === 'overdue') return store.tasks.filter(t => t.is_overdue && !t.is_completed).length
  if (tab === 'pending') return store.tasks.filter(t => t.progress === 0 && !t.is_completed).length
  if (tab === 'in_progress') return store.tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed).length
  return store.tasks.length
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
    status: t.status ? { id: t.status.id, name: t.status.name, icon: t.status.icon, color: t.status.color } : null,
    priority: t.priority,
  }
}

function openAdd() {
  if (projectStore.list.length === 0) {
    toast.error('請先建立專案')
    return
  }
  selectedProjectId.value = ''
  showProjectPicker.value = true
}

function confirmProjectPick() {
  if (!selectedProjectId.value) return
  activeProjectId.value = selectedProjectId.value as number
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
