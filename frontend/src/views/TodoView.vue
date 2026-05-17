<template>
  <div>
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h6 font-weight-bold">今日待辦</h2>
        <p class="text-body-2 text-grey">所有未完成任務，依截止日期排序</p>
      </div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openAdd">新增任務</v-btn>
    </div>

    <v-card rounded="xl">
      <v-data-table
        :headers="headers"
        :items="filteredTasks"
        :loading="store.loading"
        hover
        item-value="id"
        @click:row="(_e: Event, { item }: any) => openEdit(item)"
      >
        <template #top>
          <v-toolbar flat rounded="t-xl">
            <div class="d-flex align-center gap-3 pa-3 w-100 flex-wrap">
              <v-chip-group v-model="activeTab" mandatory selected-class="bg-primary text-white">
                <v-chip v-for="tab in tabs" :key="tab.value" :value="tab.value" size="small" filter>
                  {{ tab.label }}（{{ getTabCount(tab.value) }}）
                </v-chip>
              </v-chip-group>
            </div>
          </v-toolbar>
        </template>

        <template #item.assignee="{ item }">
          <div v-if="item.assignee" class="d-flex align-center gap-2">
            <v-avatar color="primary" size="26">
              <span class="text-caption text-white font-weight-bold">{{ item.assignee.name.charAt(0) }}</span>
            </v-avatar>
            <span class="text-body-2">{{ item.assignee.name }}</span>
          </div>
          <span v-else class="text-grey text-body-2">未指派</span>
        </template>

        <template #item.end_date="{ item }">
          <span :class="item.is_overdue ? 'text-error font-weight-medium' : 'text-body-2'">
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
            class="font-weight-medium"
          >
            <v-icon :icon="statusIcon(item.status.icon)" size="12" class="mr-1" />
            {{ item.status.name }}
          </v-chip>
        </template>

        <template #item.progress="{ item }">
          <div class="d-flex align-center gap-2" style="min-width:100px">
            <v-progress-linear
              :model-value="item.progress"
              :color="item.is_overdue ? 'error' : item.is_completed ? 'success' : 'primary'"
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
            <v-btn icon="mdi-pencil" size="small" variant="text" color="grey" @click="openEdit(item)" />
            <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="handleDelete(item)" />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-8 text-grey">
            {{
              activeTab === 'overdue'     ? '沒有逾期任務' :
              activeTab === 'completed'   ? '尚無已完成任務' :
              activeTab === 'pending'     ? '沒有待處理任務' :
              activeTab === 'in_progress' ? '沒有進行中任務' :
              '目前沒有待辦任務'
            }}
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
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 gap-2">
          <v-btn variant="outlined" color="grey" class="flex-grow-1" @click="showProjectPicker = false">取消</v-btn>
          <v-btn color="primary" class="flex-grow-1" :disabled="!selectedProjectId" @click="confirmProjectPick">繼續</v-btn>
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
const selectedProjectId = ref<number | null>(null)

const tabs = [
  { label: '全部',   value: 'all' },
  { label: '待處理', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '逾期',   value: 'overdue' },
]

const headers = [
  { title: '任務名稱', key: 'name',        sortable: true },
  { title: '所屬專案', key: 'project_name', sortable: true },
  { title: '負責人',  key: 'assignee',     sortable: false },
  { title: '截止日期', key: 'end_date',     sortable: true },
  { title: '優先級',  key: 'priority',     sortable: false },
  { title: '狀態',   key: 'status',       sortable: false },
  { title: '進度',   key: 'progress',     sortable: true, width: '140px' },
  { title: '',      key: 'actions',      sortable: false, width: '80px' },
]

const filteredTasks = computed(() => {
  const tasks = store.tasks
  if (activeTab.value === 'completed')   return tasks.filter(t => t.is_completed)
  if (activeTab.value === 'overdue')     return tasks.filter(t => t.is_overdue && !t.is_completed)
  if (activeTab.value === 'pending')     return tasks.filter(t => t.progress === 0 && !t.is_completed)
  if (activeTab.value === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed)
  return tasks
})

function getTabCount(tab: string) {
  const tasks = store.tasks
  if (tab === 'completed')   return tasks.filter(t => t.is_completed).length
  if (tab === 'overdue')     return tasks.filter(t => t.is_overdue && !t.is_completed).length
  if (tab === 'pending')     return tasks.filter(t => t.progress === 0 && !t.is_completed).length
  if (tab === 'in_progress') return tasks.filter(t => t.progress > 0 && t.progress < 100 && !t.is_completed).length
  return tasks.length
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
    status: t.status ? { id: t.status.id, name: t.status.name, icon: t.status.icon, color: t.status.color } : null,
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
