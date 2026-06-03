<template>
  <div>
    <!-- ── ADMIN: Company picker → Projects ─────────────────────────────── -->
    <template v-if="auth.isAdmin">
      <!-- Step 1: Company list -->
      <template v-if="!selectedCompany">
        <div class="mb-5">
          <h2 class="text-h6 font-weight-bold">專案管理</h2>
          <p class="text-body-2 text-medium-emphasis">選擇公司以查看其專案列表</p>
        </div>

        <!-- KPI strip -->
        <v-row class="mb-5" dense>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="管理公司"
              :value="activeCompanies.length"
              icon="mdi-domain"
              icon-color="primary"
              accent="primary"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="全平台專案"
              :value="companyStats.totalProjects"
              icon="mdi-folder-multiple-outline"
              icon-color="primary"
              accent="primary"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="進行中"
              :value="companyStats.totalInProgress"
              icon="mdi-progress-clock"
              icon-color="info"
              accent="info"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <KPICard
              label="總成員"
              :value="companyStats.totalMembers"
              icon="mdi-account-group"
              icon-color="success"
              accent="success"
            />
          </v-col>
        </v-row>

        <!-- Search bar -->
        <div class="mb-4">
          <v-text-field
            v-model="companySearch"
            prepend-inner-icon="mdi-magnify"
            placeholder="搜尋公司..."
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            style="max-width: 320px"
          />
        </div>

        <!-- Company grid -->
        <div v-if="companiesLoading" class="pms-company-grid">
          <v-skeleton-loader v-for="i in 6" :key="i" type="card" rounded="xl" />
        </div>

        <EmptyState
          v-else-if="filteredCompanies.length === 0"
          icon="mdi-domain"
          :title="activeCompanies.length === 0 ? '目前沒有公司' : '找不到符合條件的公司'"
          :sub="activeCompanies.length === 0 ? '請至系統管理新增公司' : '試試其他關鍵字'"
        />

        <div v-else class="pms-company-grid">
          <CompanyCard
            v-for="c in filteredCompanies"
            :key="c.id"
            :company="c"
            @select="selectCompany"
          />
        </div>

        <div class="d-flex justify-end mt-3 text-caption text-medium-emphasis">
          共 {{ filteredCompanies.length }} / {{ activeCompanies.length }} 間公司
        </div>
      </template>

      <!-- Step 2: Projects for selected company -->
      <template v-else>
        <div class="mb-6 d-flex align-start justify-space-between gap-4 flex-wrap">
          <div>
            <v-breadcrumbs
              :items="[
                { title: '專案管理', onClick: () => { selectedCompany = null; store.list = [] } },
                { title: selectedCompany.name, disabled: true },
              ]"
              density="compact"
              class="pa-0 mb-1"
            >
              <template #divider>
                <v-icon icon="mdi-chevron-right" size="14" />
              </template>
            </v-breadcrumbs>
            <h2 class="text-h6 font-weight-bold">{{ selectedCompany.name }}</h2>
            <p class="text-body-2 text-medium-emphasis">專案列表</p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <v-btn
              variant="outlined"
              color="primary"
              prepend-icon="mdi-upload"
              rounded="lg"
              @click="showImport = true"
              >匯入</v-btn
            >
            <v-btn
              variant="outlined"
              color="grey"
              prepend-icon="mdi-download"
              rounded="lg"
              :loading="exporting"
              @click="exportAll"
              >匯出全部</v-btn
            >
            <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate"
              >新增專案</v-btn
            >
          </div>
        </div>

        <ProjectsChartStrip :projects="store.list" class="mb-5" />

        <ProjectDataTable
          v-if="viewMode === 'table'"
          :projects="filteredProjects"
          :loading="store.listLoading"
          v-model:search="projectSearch"
          v-model:status="statusFilter"
          v-model:view="viewMode"
          :status-options="statusFilterOptions"
          @edit="openEdit"
          @delete="handleDelete"
        />
        <ProjectCardGrid
          v-else
          :projects="filteredProjects"
          :loading="store.listLoading"
          v-model:search="projectSearch"
          v-model:status="statusFilter"
          v-model:view="viewMode"
          :status-options="statusFilterOptions"
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
          <v-btn
            variant="outlined"
            color="primary"
            prepend-icon="mdi-upload"
            rounded="lg"
            @click="showImport = true"
            >匯入</v-btn
          >
          <v-btn
            variant="outlined"
            color="grey"
            prepend-icon="mdi-download"
            rounded="lg"
            :loading="exporting"
            @click="exportAll"
            >匯出全部</v-btn
          >
          <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate"
            >新增專案</v-btn
          >
        </div>
      </div>

      <ProjectsChartStrip :projects="store.list" class="mb-5" />

      <ProjectDataTable
        v-if="viewMode === 'table'"
        :projects="filteredProjects"
        :loading="store.listLoading"
        v-model:search="projectSearch"
        v-model:status="statusFilter"
        v-model:view="viewMode"
        :status-options="statusFilterOptions"
        @edit="openEdit"
        @delete="handleDelete"
      />
      <ProjectCardGrid
        v-else
        :projects="filteredProjects"
        :loading="store.listLoading"
        v-model:search="projectSearch"
        v-model:status="statusFilter"
        v-model:view="viewMode"
        :status-options="statusFilterOptions"
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
import { onMounted, ref, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useProjectStore, type ProjectListItem } from "@/stores/project";
import { useToast } from "@/composables/useToast";
import ProjectModal from "@/components/ProjectModal.vue";
import ProjectDataTable from "@/components/ProjectDataTable.vue";
import ImportDialog from "@/components/ImportDialog.vue";
import ProjectsChartStrip from "@/components/project/ProjectsChartStrip.vue";
import ProjectCardGrid from "@/components/project/ProjectCardGrid.vue";
import CompanyCard from "@/components/admin/CompanyCard.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import KPICard from "@/components/ui/KPICard.vue";
import api from "@/lib/axios";

export interface Company {
  id: number;
  name: string;
  status: "active" | "suspended";
  managers_count: number;
  members_count: number;
  projects_count: number;
  projects_in_progress_count: number;
  avg_progress_percent: number;
}

const auth = useAuthStore();
const store = useProjectStore();
const toast = useToast();

// Admin company state
const companies = ref<Company[]>([]);
const companiesLoading = ref(false);
const selectedCompany = ref<Company | null>(null);
const companySearch = ref("");

// 停用公司不顯示在這支頁面 — 由系統管理頁面控管
const activeCompanies = computed(() => companies.value.filter((c) => c.status === "active"));

const companyStats = computed(() => {
  let totalProjects = 0;
  let totalInProgress = 0;
  let totalMembers = 0;
  for (const c of activeCompanies.value) {
    totalProjects += c.projects_count ?? 0;
    totalInProgress += c.projects_in_progress_count ?? 0;
    totalMembers += (c.managers_count ?? 0) + (c.members_count ?? 0);
  }
  return { totalProjects, totalInProgress, totalMembers };
});

const filteredCompanies = computed<Company[]>(() => {
  const q = companySearch.value.trim().toLowerCase();
  if (!q) return activeCompanies.value;
  return activeCompanies.value.filter((c) => c.name.toLowerCase().includes(q));
});

// Import / Export
const showImport = ref(false);
const exporting = ref(false);

async function exportAll() {
  exporting.value = true;
  try {
    const res = await api.get("/projects/export", { responseType: "blob" });
    const url = URL.createObjectURL(res.data);
    const a = document.createElement("a");
    a.href = url;
    a.download = `全部專案_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
  } finally {
    exporting.value = false;
  }
}

async function onImportDone() {
  showImport.value = false;
  if (auth.isAdmin && selectedCompany.value) {
    await store.fetchList(selectedCompany.value.id);
  } else {
    await store.fetchList();
  }
  toast.success("匯入完成，專案列表已更新");
}

// Shared modal state
const showModal = ref(false);
const editingProject = ref<ProjectListItem | null>(null);

// Project list filters
const projectSearch = ref("");
const statusFilter = ref<string>("all");
const viewMode = ref<"table" | "card">("table");

const statusFilterOptions = computed(() => {
  const seen = new Map<string, { value: string; label: string; count: number }>();
  seen.set("all", { value: "all", label: "全部", count: store.list.length });
  for (const p of store.list) {
    if (!p.status) continue;
    const key = String(p.status.id);
    if (!seen.has(key)) {
      seen.set(key, { value: key, label: p.status.name, count: 0 });
    }
    seen.get(key)!.count++;
  }
  return Array.from(seen.values());
});

const filteredProjects = computed<ProjectListItem[]>(() => {
  let list = store.list;
  if (statusFilter.value !== "all") {
    list = list.filter((p) => p.status && String(p.status.id) === statusFilter.value);
  }
  const q = projectSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        (p.project_no ?? "").toLowerCase().includes(q) ||
        (p.owner?.name ?? "").toLowerCase().includes(q),
    );
  }
  return list;
});

async function selectCompany(company: Company) {
  selectedCompany.value = company;
  await store.fetchList(company.id);
}

function openCreate() {
  editingProject.value = null;
  showModal.value = true;
}

function openEdit(project: ProjectListItem) {
  editingProject.value = project;
  showModal.value = true;
}

async function onProjectSaved() {
  showModal.value = false;
  if (auth.isAdmin && selectedCompany.value) {
    await store.fetchList(selectedCompany.value.id);
  } else {
    await store.fetchList();
  }
}

async function handleDelete(project: ProjectListItem) {
  if (!confirm(`確定要刪除「${project.name}」？此操作無法復原。`)) return;
  await store.deleteProject(project.id);
}

onMounted(async () => {
  if (auth.isAdmin) {
    companiesLoading.value = true;
    try {
      const res = await api.get("/admin/companies");
      companies.value = res.data;
    } finally {
      companiesLoading.value = false;
    }
  } else {
    await store.fetchList();
  }
});
</script>

<style scoped>
.pms-company-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
@media (max-width: 1280px) {
  .pms-company-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 960px) {
  .pms-company-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .pms-company-grid { grid-template-columns: 1fr; }
}
</style>
