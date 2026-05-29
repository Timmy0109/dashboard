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

    <!-- ── MANAGER / MEMBER: Projects only ──────────────────────────────── -->
    <template v-else>
      <div class="mb-5 d-flex align-center justify-space-between flex-wrap gap-3">
        <div>
          <h2 class="text-h6 font-weight-bold">專案管理</h2>
          <p class="text-body-2 text-medium-emphasis">我的專案</p>
        </div>
        <div v-if="auth.canManageMembers" class="d-flex gap-2 flex-wrap">
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
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore, type ProjectListItem } from '@/stores/project'
import { useToast } from '@/composables/useToast'
import ProjectModal from '@/components/ProjectModal.vue'
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
    await store.fetchList()
  }
})
</script>
