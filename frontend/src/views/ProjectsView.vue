<template>
  <div>
    <!-- ── ADMIN: Company picker → Projects ─────────────────────────────── -->
    <template v-if="auth.isAdmin">

      <!-- Step 1: Company list -->
      <template v-if="!selectedCompany">
        <div class="mb-5">
          <h2 class="text-h6 font-weight-bold">專案管理</h2>
          <p class="text-body-2 text-medium-emphasis">選擇公司以查看其專案</p>
        </div>

        <!-- KPI strip -->
        <v-row class="mb-5" dense>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="公司總數"
              :value="companies.length"
              icon="mdi-domain"
              icon-color="primary"
              accent="primary"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="運作中"
              :value="companyStats.active"
              icon="mdi-check-circle"
              icon-color="success"
              accent="success"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="已停用"
              :value="companyStats.suspended"
              icon="mdi-pause-circle"
              icon-color="error"
              accent="error"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="總成員"
              :value="companyStats.totalMembers"
              icon="mdi-account-group"
              icon-color="info"
              accent="info"
            />
          </v-col>
        </v-row>

        <!-- Toolbar -->
        <v-card rounded="xl" class="mb-3 pa-3 d-flex align-center gap-3 flex-wrap">
          <v-text-field
            v-model="companySearch"
            prepend-inner-icon="mdi-magnify"
            placeholder="搜尋公司..."
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            style="max-width:280px"
          />
          <ChipGroup
            v-model="companyStatusFilter"
            :items="companyStatusOptions"
          />
        </v-card>

        <v-card rounded="xl">
          <v-data-table
            :headers="companyHeaders"
            :items="filteredCompanies"
            :loading="companiesLoading"
            hover
            item-value="id"
            @click:row="(_e: Event, { item }: { item: Company }) => selectCompany(item)"
          >
            <template #item.name="{ item }">
              <div class="d-flex align-center gap-3 py-2">
                <v-avatar size="36" color="primary" variant="tonal">
                  <span class="text-body-2 font-weight-bold">{{ item.name.charAt(0) }}</span>
                </v-avatar>
                <span class="text-body-2 font-weight-medium">{{ item.name }}</span>
              </div>
            </template>

            <template #item.managers_count="{ item }">
              <v-chip size="small" variant="tonal" color="primary">
                {{ item.managers_count ?? 0 }} 人
              </v-chip>
            </template>

            <template #item.members_count="{ item }">
              <v-chip size="small" variant="tonal" color="secondary">
                {{ item.members_count ?? 0 }} 人
              </v-chip>
            </template>

            <template #item.status="{ item }">
              <v-chip
                :color="item.status === 'active' ? 'success' : 'error'"
                size="small"
                variant="tonal"
              >
                <v-icon
                  :icon="item.status === 'active' ? 'mdi-check-circle' : 'mdi-pause-circle'"
                  size="12"
                  class="mr-1"
                />
                {{ item.status === 'active' ? '運作中' : '已停用' }}
              </v-chip>
            </template>

            <template #item.action>
              <v-icon icon="mdi-chevron-right" color="grey" />
            </template>

            <template #no-data>
              <div class="py-6">
                <EmptyState
                  icon="mdi-domain"
                  :title="companies.length === 0 ? '目前沒有公司' : '找不到符合條件的公司'"
                  :sub="companies.length === 0 ? '平台尚未建立任何公司' : '試試其他關鍵字或狀態'"
                />
              </div>
            </template>
          </v-data-table>
        </v-card>

        <div class="d-flex justify-end mt-3 text-caption text-medium-emphasis">
          共 {{ filteredCompanies.length }} / {{ companies.length }} 間公司
        </div>
      </template>

      <!-- Step 2: Projects for selected company -->
      <template v-else>
        <div class="mb-6 d-flex align-start justify-space-between gap-4 flex-wrap">
          <div>
            <v-btn
              variant="text"
              color="grey"
              prepend-icon="mdi-arrow-left"
              size="small"
              class="mb-2 px-0"
              @click="selectedCompany = null; store.list = []"
            >
              返回公司列表
            </v-btn>
            <h2 class="text-h6 font-weight-bold">{{ selectedCompany.name }}</h2>
            <p class="text-body-2 text-medium-emphasis">專案列表</p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <v-btn variant="outlined" color="primary" prepend-icon="mdi-upload" rounded="lg" @click="showImport = true">匯入</v-btn>
            <v-btn variant="outlined" color="grey" prepend-icon="mdi-download" rounded="lg" :loading="exporting" @click="exportAll">匯出全部</v-btn>
            <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">新增專案</v-btn>
          </div>
        </div>

        <ProjectsChartStrip :projects="store.list" class="mb-5" />

        <ProjectsToolbar
          v-model:search="projectSearch"
          v-model:status="statusFilter"
          v-model:view="viewMode"
          :status-options="statusFilterOptions"
        />

        <ProjectDataTable
          v-if="viewMode === 'table'"
          :projects="filteredProjects"
          :loading="store.listLoading"
          @edit="openEdit"
          @delete="handleDelete"
        />
        <ProjectCardGrid
          v-else
          :projects="filteredProjects"
          :loading="store.listLoading"
        />

        <div class="d-flex justify-end mt-3 text-caption text-medium-emphasis">
          共 {{ filteredProjects.length }} / {{ store.list.length }} 個專案
        </div>
      </template>
    </template>

    <!-- ── MANAGER / MEMBER: Tabs (Projects | My Tasks) ─────────────────── -->
    <template v-else>
      <div class="mb-5 d-flex align-center justify-space-between flex-wrap gap-3">
        <div>
          <h2 class="text-h6 font-weight-bold">專案管理</h2>
          <p class="text-body-2 text-medium-emphasis">我的專案</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <template v-if="auth.canManageMembers && activeTab === 'projects'">
            <v-btn variant="outlined" color="primary" prepend-icon="mdi-upload" rounded="lg" @click="showImport = true">匯入</v-btn>
            <v-btn variant="outlined" color="grey" prepend-icon="mdi-download" rounded="lg" :loading="exporting" @click="exportAll">匯出全部</v-btn>
            <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">新增專案</v-btn>
          </template>
          <v-btn
            v-if="activeTab === 'tasks'"
            color="primary"
            prepend-icon="mdi-plus"
            rounded="lg"
            @click="openAddTask"
          >
            新增任務
          </v-btn>
        </div>
      </div>

      <v-tabs v-model="activeTab" color="primary" class="mb-4">
        <v-tab value="projects">專案列表</v-tab>
        <v-tab value="tasks">我的任務</v-tab>
      </v-tabs>

      <v-tabs-window v-model="activeTab">
        <!-- Projects tab -->
        <v-tabs-window-item value="projects">
          <ProjectsChartStrip :projects="store.list" class="mb-5" />

          <ProjectsToolbar
            v-model:search="projectSearch"
            v-model:status="statusFilter"
            v-model:view="viewMode"
            :status-options="statusFilterOptions"
          />

          <ProjectDataTable
            v-if="viewMode === 'table'"
            :projects="filteredProjects"
            :loading="store.listLoading"
            @edit="openEdit"
            @delete="handleDelete"
          />
          <ProjectCardGrid
            v-else
            :projects="filteredProjects"
            :loading="store.listLoading"
          />

          <div class="d-flex justify-end mt-3 text-caption text-medium-emphasis">
            共 {{ filteredProjects.length }} / {{ store.list.length }} 個專案
          </div>
        </v-tabs-window-item>

        <!-- My Tasks tab -->
        <v-tabs-window-item value="tasks">
          <v-card rounded="xl">
            <v-data-table
              :headers="taskHeaders"
              :items="filteredTasks"
              :loading="todoStore.loading"
              :search="taskSearch"
              hover
              item-value="id"
              @click:row="(_e: Event, { item }: { item: TodoTask }) => openEditTask(item)"
            >
              <template #top>
                <v-toolbar flat rounded="t-xl">
                  <div class="d-flex align-center gap-3 pa-3 w-100 flex-wrap">
                    <v-text-field
                      v-model="taskSearch"
                      prepend-inner-icon="mdi-magnify"
                      placeholder="搜尋任務..."
                      variant="outlined"
                      density="compact"
                      hide-details
                      rounded="lg"
                      style="max-width:260px"
                    />
                    <v-chip-group v-model="taskTab" mandatory selected-class="bg-primary text-white" class="flex-shrink-0">
                      <v-chip v-for="t in taskTabs" :key="t.value" :value="t.value" size="small" filter>
                        {{ t.label }}（{{ getTaskCount(t.value) }}）
                      </v-chip>
                    </v-chip-group>
                  </div>
                </v-toolbar>
              </template>

              <template #item.assignee="{ item }">
                <div v-if="item.assignee" class="d-flex align-center gap-2">
                  <v-avatar color="primary" size="26" class="mr-2">
                    <span class="text-caption text-white font-weight-bold">{{ item.assignee.name.charAt(0) }}</span>
                  </v-avatar>
                  <span class="text-body-2">{{ item.assignee.name }}</span>
                </div>
                <span v-else class="text-grey text-body-2">未指派</span>
              </template>

              <template #item.end_date="{ item }">
                <span :class="item.is_overdue ? 'text-error font-weight-medium' : ''">
                  <v-icon v-if="item.is_overdue" icon="mdi-alert-circle" size="14" color="error" class="mr-1" />
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
                >
                  <v-icon :icon="statusIcon(item.status.icon)" size="12" class="mr-1" />
                  {{ item.status.name }}
                </v-chip>
              </template>

              <template #item.progress="{ item }">
                <div class="d-flex align-center gap-2" style="min-width:100px">
                  <v-progress-linear
                    :model-value="item.progress"
                    :color="item.is_overdue ? 'error' : 'primary'"
                    bg-color="grey-lighten-3"
                    rounded
                    height="5"
                    class="flex-grow-1"
                  />
                  <span class="text-caption text-grey-darken-1">{{ item.progress }}%</span>
                </div>
              </template>

              <template #item.actions="{ item }">
                <div class="d-flex gap-1" @click.stop>
                  <v-btn icon="mdi-pencil" size="small" variant="text" color="grey" @click="openEditTask(item)" />
                  <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="handleDeleteTask(item)" />
                </div>
              </template>

              <template #no-data>
                <EmptyState
                  icon="mdi-format-list-checks"
                  :title="taskTab === 'overdue' ? '沒有逾期任務' : taskTab === 'completed' ? '尚無已完成任務' : '目前沒有任務'"
                  sub="切換上方分頁或建立新任務"
                />
              </template>
            </v-data-table>
          </v-card>
        </v-tabs-window-item>
      </v-tabs-window>
    </template>

    <!-- Import Dialog -->
    <ImportDialog v-if="showImport" @close="showImport = false" @done="onImportDone" />

    <!-- Project Modal -->
    <ProjectModal
      v-if="showModal"
      :project="editingProject"
      :company-id="selectedCompany?.id ?? null"
      @close="showModal = false"
      @saved="onProjectSaved"
    />

    <!-- Project picker for task add -->
    <v-dialog v-model="showProjectPicker" max-width="400" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3">選擇所屬專案</v-card-title>
        <v-card-text>
          <v-select
            v-model="selectedProjectId"
            :items="store.list"
            item-title="name"
            item-value="id"
            label="專案"
            placeholder="請選擇專案"
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 gap-2">
          <v-btn variant="outlined" color="grey" flex-grow-1 @click="showProjectPicker = false">取消</v-btn>
          <v-btn color="primary" flex-grow-1 :disabled="!selectedProjectId" @click="confirmProjectPick">繼續</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
import ProjectDataTable from '@/components/ProjectDataTable.vue'
import ImportDialog from '@/components/ImportDialog.vue'
import ProjectsChartStrip from '@/components/project/ProjectsChartStrip.vue'
import ProjectsToolbar from '@/components/project/ProjectsToolbar.vue'
import ProjectCardGrid from '@/components/project/ProjectCardGrid.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import KPICard from '@/components/ui/KPICard.vue'
import ChipGroup from '@/components/ui/ChipGroup.vue'
import api from '@/lib/axios'

interface Company {
  id: number
  name: string
  status: 'active' | 'suspended'
  managers_count: number
  members_count: number
}

const auth = useAuthStore()
const store = useProjectStore()
const todoStore = useTodoStore()
const toast = useToast()

// Admin company state
const companies = ref<Company[]>([])
const companiesLoading = ref(false)
const selectedCompany = ref<Company | null>(null)
const companySearch = ref('')
const companyStatusFilter = ref<string>('all')

const companyHeaders = [
  { title: '公司名稱', key: 'name',           sortable: true },
  { title: 'Manager', key: 'managers_count', sortable: true },
  { title: '成員數',  key: 'members_count',  sortable: true },
  { title: '狀態',   key: 'status',         sortable: false },
  { title: '',      key: 'action',          sortable: false, width: '48px' },
]

const companyStats = computed(() => {
  let active = 0
  let suspended = 0
  let totalMembers = 0
  for (const c of companies.value) {
    if (c.status === 'active') active++
    else suspended++
    totalMembers += (c.managers_count ?? 0) + (c.members_count ?? 0)
  }
  return { active, suspended, totalMembers }
})

const companyStatusOptions = computed(() => [
  { value: 'all',       label: '全部',   count: companies.value.length },
  { value: 'active',    label: '運作中', count: companyStats.value.active },
  { value: 'suspended', label: '已停用', count: companyStats.value.suspended },
])

const filteredCompanies = computed<Company[]>(() => {
  let list = companies.value
  if (companyStatusFilter.value !== 'all') {
    list = list.filter(c => c.status === companyStatusFilter.value)
  }
  const q = companySearch.value.trim().toLowerCase()
  if (q) list = list.filter(c => c.name.toLowerCase().includes(q))
  return list
})

// Import / Export
const showImport = ref(false)
const exporting = ref(false)

async function exportAll() {
  exporting.value = true
  try {
    const res = await api.get('/projects/export', { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `全部專案_${new Date().toISOString().slice(0, 10)}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } finally {
    exporting.value = false
  }
}

async function onImportDone() {
  showImport.value = false
  if (auth.isAdmin && selectedCompany.value) {
    await store.fetchList(selectedCompany.value.id)
  } else {
    await store.fetchList()
  }
  toast.success('匯入完成，專案列表已更新')
}

// Shared modal state
const showModal = ref(false)
const editingProject = ref<ProjectListItem | null>(null)

// Member/manager tabs
const activeTab = ref<'projects' | 'tasks'>('projects')

// Project list filters
const projectSearch = ref('')
const statusFilter = ref<string>('all')
const viewMode = ref<'table' | 'card'>('table')

const statusFilterOptions = computed(() => {
  const seen = new Map<string, { value: string; label: string; count: number }>()
  seen.set('all', { value: 'all', label: '全部', count: store.list.length })
  for (const p of store.list) {
    if (!p.status) continue
    const key = String(p.status.id)
    if (!seen.has(key)) {
      seen.set(key, { value: key, label: p.status.name, count: 0 })
    }
    seen.get(key)!.count++
  }
  return Array.from(seen.values())
})

const filteredProjects = computed<ProjectListItem[]>(() => {
  let list = store.list
  if (statusFilter.value !== 'all') {
    list = list.filter(p => p.status && String(p.status.id) === statusFilter.value)
  }
  const q = projectSearch.value.trim().toLowerCase()
  if (q) {
    list = list.filter(p =>
      p.name.toLowerCase().includes(q)
      || (p.project_no ?? '').toLowerCase().includes(q)
      || (p.owner?.name ?? '').toLowerCase().includes(q),
    )
  }
  return list
})

// Task filter tabs
const taskTab = ref('all')
const taskSearch = ref('')
const taskTabs = [
  { label: '全部',   value: 'all' },
  { label: '待處理', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '逾期',   value: 'overdue' },
]

const taskHeaders = [
  { title: '任務名稱', key: 'name' },
  { title: '所屬專案', key: 'project_name', sortable: true },
  { title: '負責人',  key: 'assignee',     sortable: false },
  { title: '截止日期', key: 'end_date',     sortable: true },
  { title: '優先級',  key: 'priority',     sortable: false },
  { title: '狀態',   key: 'status',       sortable: false },
  { title: '進度',   key: 'progress',     sortable: true },
  { title: '',      key: 'actions',      sortable: false, width: '80px' },
]

const filteredTasks = computed(() => {
  const tasks = todoStore.tasks
  if (taskTab.value === 'completed')  return tasks.filter(t => t.is_completed)
  if (taskTab.value === 'overdue')    return tasks.filter(t => t.is_overdue && !t.is_completed)
  if (taskTab.value === 'pending')    return tasks.filter(t => t.progress === 0 && !t.is_completed)
  if (taskTab.value === 'in_progress')return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed)
  return tasks
})

function statusIcon(icon: string | null | undefined) {
  if (!icon) return 'mdi-circle-outline'
  const normalized = icon.replace(/_/g, '-')
  return normalized.startsWith('mdi-') ? normalized : `mdi-${normalized}`
}

function getTaskCount(tab: string) {
  const tasks = todoStore.tasks
  if (tab === 'completed')   return tasks.filter(t => t.is_completed).length
  if (tab === 'overdue')     return tasks.filter(t => t.is_overdue && !t.is_completed).length
  if (tab === 'pending')     return tasks.filter(t => t.progress === 0 && !t.is_completed).length
  if (tab === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed).length
  return tasks.length
}

// Task modal state
const showTaskModal = ref(false)
const showProjectPicker = ref(false)
const editingTask = ref<Task | null>(null)
const activeProjectId = ref<number | null>(null)
const selectedProjectId = ref<number | null>(null)

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
