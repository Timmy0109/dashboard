<template>
  <div>
    <!-- ── ADMIN: Company picker → Projects ─────────────────────────────── -->
    <template v-if="auth.isAdmin">
      <!-- Step 1: Company list -->
      <template v-if="!selectedCompany">
        <div class="mb-6">
          <h2 class="text-xl font-bold text-gray-900">專案管理</h2>
          <p class="text-sm text-gray-500 mt-0.5">選擇公司以查看其專案</p>
        </div>

        <div v-if="companiesLoading" class="flex items-center justify-center py-20">
          <div class="text-gray-400 text-sm">載入中...</div>
        </div>

        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50">
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">#</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">公司名稱</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">成員數</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">專案數</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr
                v-for="(company, idx) in companies"
                :key="company.id"
                class="hover:bg-gray-50 transition-colors cursor-pointer"
                @click="selectCompany(company)"
              >
                <td class="px-4 py-3 text-xs text-gray-400">{{ idx + 1 }}</td>
                <td class="px-4 py-3">
                  <span class="text-sm font-medium text-gray-800">{{ company.name }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ company.users_count ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                    :class="company.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                  >
                    {{ company.is_active ? '啟用' : '停用' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="inline-flex items-center gap-1 text-xs text-blue-600 font-medium">
                    查看專案 <span class="material-icons text-sm leading-none">chevron_right</span>
                  </span>
                </td>
              </tr>
              <tr v-if="companies.length === 0">
                <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">目前沒有公司</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Step 2: Projects for selected company -->
      <template v-else>
        <div class="mb-6 flex items-center justify-between">
          <div>
            <button
              @click="selectedCompany = null; store.list = []"
              class="text-sm text-gray-400 hover:text-gray-600 mb-2 flex items-center gap-1"
            >
              <span class="material-icons text-base leading-none">arrow_back</span> 返回公司列表
            </button>
            <h2 class="text-xl font-bold text-gray-900">{{ selectedCompany.name }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">專案列表</p>
          </div>
          <button
            @click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            <span class="material-icons text-base leading-none">add</span> 新增專案
          </button>
        </div>

        <ProjectTable
          :projects="store.list"
          :loading="store.listLoading"
          @edit="openEdit"
          @delete="handleDelete"
        />
      </template>
    </template>

    <!-- ── MANAGER / MEMBER: Tabs (Projects | My Tasks) ─────────────────── -->
    <template v-else>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="text-xl font-bold text-gray-900">專案管理</h2>
        </div>
        <button
          v-if="auth.canManageMembers && activeTab === 'projects'"
          @click="openCreate"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
        >
          <span class="material-icons text-base leading-none">add</span> 新增專案
        </button>
        <button
          v-if="activeTab === 'tasks'"
          @click="openAddTask"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
        >
          <span class="material-icons text-base leading-none">add</span> 新增任務
        </button>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 mb-5 border-b border-gray-200">
        <button
          v-for="tab in memberTabs"
          :key="tab.value"
          @click="activeTab = tab.value"
          class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="activeTab === tab.value
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Projects tab -->
      <template v-if="activeTab === 'projects'">
        <ProjectTable
          :projects="store.list"
          :loading="store.listLoading"
          @edit="openEdit"
          @delete="handleDelete"
        />
      </template>

      <!-- My tasks tab -->
      <template v-else>
        <!-- Filter chips -->
        <div class="flex gap-2 mb-4">
          <button
            v-for="chip in taskTabs"
            :key="chip.value"
            @click="taskTab = chip.value"
            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
            :class="taskTab === chip.value
              ? 'bg-blue-600 text-white'
              : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'"
          >
            {{ chip.label }}
            <span class="ml-1 opacity-70">({{ getTaskCount(chip.value) }})</span>
          </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div v-if="todoStore.loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>

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
                @click="openEditTask(task)"
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
                    <span v-if="task.is_overdue" class="material-icons text-sm leading-none align-middle mr-0.5">warning</span>
                    {{ task.end_date }}
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
                      @click="openEditTask(task)"
                      class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                    ><span class="material-icons text-base leading-none">edit</span></button>
                    <button
                      @click="handleDeleteTask(task)"
                      class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                    ><span class="material-icons text-base leading-none">delete</span></button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredTasks.length === 0">
                <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">
                  {{
                    taskTab === 'overdue' ? '沒有逾期任務' :
                    taskTab === 'completed' ? '尚無已完成任務' :
                    taskTab === 'pending' ? '沒有待處理任務' :
                    taskTab === 'in_progress' ? '沒有進行中任務' :
                    '目前沒有任務'
                  }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>

    <!-- Project Modal -->
    <ProjectModal
      v-if="showModal"
      :project="editingProject"
      :company-id="selectedCompany?.id ?? null"
      @close="showModal = false"
      @saved="onProjectSaved"
    />

    <!-- Project picker for task add -->
    <div v-if="showProjectPicker" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showProjectPicker = false" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">選擇所屬專案</h3>
        <select
          v-model="selectedProjectId"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
        >
          <option value="">請選擇專案</option>
          <option v-for="p in store.list" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="flex gap-3">
          <button @click="showProjectPicker = false" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">取消</button>
          <button @click="confirmProjectPick" :disabled="!selectedProjectId" class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">繼續</button>
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
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore, type ProjectListItem, type Task } from '@/stores/project'
import { useTodoStore, type TodoTask } from '@/stores/todo'
import { useToast } from '@/composables/useToast'
import ProjectModal from '@/components/ProjectModal.vue'
import TaskModal from '@/components/TaskModal.vue'
import ProjectTable from '@/components/ProjectTable.vue'
import api from '@/lib/axios'

interface Company { id: number; name: string; is_active: boolean; users_count?: number }

const auth = useAuthStore()
const store = useProjectStore()
const todoStore = useTodoStore()
const toast = useToast()

// Admin company state
const companies = ref<Company[]>([])
const companiesLoading = ref(false)
const selectedCompany = ref<Company | null>(null)

// Shared modal state
const showModal = ref(false)
const editingProject = ref<ProjectListItem | null>(null)

// Member/manager tabs
type TabValue = 'projects' | 'tasks'
const activeTab = ref<TabValue>('projects')
const memberTabs: { label: string; value: TabValue }[] = [
  { label: '專案列表', value: 'projects' },
  { label: '我的任務', value: 'tasks' },
]

// Task filter tabs
const taskTab = ref('all')
const taskTabs = [
  { label: '全部', value: 'all' },
  { label: '待處理', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '逾期', value: 'overdue' },
]

const filteredTasks = computed(() => {
  const tasks = todoStore.tasks
  if (taskTab.value === 'completed') return tasks.filter(t => t.is_completed)
  if (taskTab.value === 'overdue') return tasks.filter(t => t.is_overdue && !t.is_completed)
  if (taskTab.value === 'pending') return tasks.filter(t => t.progress === 0 && !t.is_completed)
  if (taskTab.value === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed)
  return tasks
})

function getTaskCount(tab: string) {
  const tasks = todoStore.tasks
  if (tab === 'completed') return tasks.filter(t => t.is_completed).length
  if (tab === 'overdue') return tasks.filter(t => t.is_overdue && !t.is_completed).length
  if (tab === 'pending') return tasks.filter(t => t.progress === 0 && !t.is_completed).length
  if (tab === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed).length
  return tasks.length
}

// Task modal state
const showTaskModal = ref(false)
const showProjectPicker = ref(false)
const editingTask = ref<Task | null>(null)
const activeProjectId = ref<number | null>(null)
const selectedProjectId = ref<number | ''>('')

async function selectCompany(company: Company) {
  selectedCompany.value = company
  await store.fetchList(company.id)
}

function openCreate() {
  editingProject.value = null
  showModal.value = true
}

function openEdit(project: ProjectListItem) {
  editingProject.value = project
  showModal.value = true
}

async function onProjectSaved() {
  showModal.value = false
  if (auth.isAdmin && selectedCompany.value) {
    await store.fetchList(selectedCompany.value.id)
  } else {
    await store.fetchList()
  }
}

async function handleDelete(project: ProjectListItem) {
  if (!confirm(`確定要刪除「${project.name}」？此操作無法復原。`)) return
  await store.deleteProject(project.id)
}

function openAddTask() {
  if (store.list.length === 0) {
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

function openEditTask(task: TodoTask) {
  editingTask.value = {
    id: task.id,
    project_id: task.project_id,
    name: task.name,
    note: task.note,
    start_date: task.start_date,
    end_date: task.end_date,
    progress: task.progress,
    is_completed: task.is_completed,
    assignee: task.assignee,
    status: task.status ? { id: task.status.id, name: task.status.name, icon: task.status.icon, color: task.status.color } : null,
    priority: task.priority,
  }
  activeProjectId.value = task.project_id
  showTaskModal.value = true
}

async function handleDeleteTask(task: TodoTask) {
  if (!confirm(`確定要刪除任務「${task.name}」？`)) return
  try {
    await todoStore.deleteTask(task.project_id, task.id)
    toast.success('已刪除')
  } catch {
    toast.error('刪除失敗')
  }
}

async function onTaskSaved() {
  showTaskModal.value = false
  await todoStore.fetch()
  toast.success('儲存成功')
}

onMounted(async () => {
  if (auth.isAdmin) {
    companiesLoading.value = true
    try {
      const res = await api.get('/admin/companies')
      companies.value = res.data
    } finally {
      companiesLoading.value = false
    }
  } else {
    await Promise.all([store.fetchList(), todoStore.fetch()])
  }
})
</script>
