<template>
  <div v-if="store.detailLoading" class="d-flex justify-center align-center py-16">
    <v-progress-circular indeterminate color="primary" />
  </div>

  <div v-else-if="project">
    <!-- ── Back ──────────────────────────────────────────────────────── -->
    <v-btn
      variant="text"
      color="grey"
      prepend-icon="mdi-arrow-left"
      size="small"
      class="mb-3 px-0"
      @click="router.back()"
      >返回</v-btn
    >

    <!-- ── Header Card ────────────────────────────────────────────────── -->
    <v-card rounded="xl" class="pa-5 mb-5" elevation="1">
      <div class="d-flex align-start justify-space-between gap-4 flex-wrap">
        <div class="flex-grow-1" style="min-width: 0">
          <div class="d-flex align-center gap-2 flex-wrap mb-1">
            <h2 class="text-h6 font-weight-bold">{{ project.name }}</h2>
            <v-chip
              v-if="project.status"
              size="small"
              :prepend-icon="statusIcon(project.status.icon)"
              :style="{ backgroundColor: project.status.color + '22', color: project.status.color }"
              class="font-weight-medium"
            >
              {{ project.status.name }}
            </v-chip>
            <v-chip
              v-if="project.priority"
              size="small"
              :style="{
                backgroundColor: project.priority.color + '22',
                color: project.priority.color,
              }"
              class="font-weight-medium"
            >
              {{ project.priority.name }}
            </v-chip>
          </div>
          <div
            class="text-caption text-medium-emphasis d-flex align-center flex-wrap gap-x-4 gap-y-1"
          >
            <span v-if="project.project_no">
              <v-icon icon="mdi-pound" size="12" class="mr-1" />{{ project.project_no }}
            </span>
            <span v-if="project.owner">
              <v-icon icon="mdi-account-tie" size="12" class="mr-1" />負責人
              {{ project.owner.name }}
            </span>
            <span>
              <v-icon icon="mdi-calendar-start" size="12" class="mr-1" />
              {{ formatDate(project.start_date) }}
            </span>
            <span>
              <v-icon icon="mdi-calendar-end" size="12" class="mr-1" />
              {{ formatDate(project.due_date) }}
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-2 flex-shrink-0 flex-wrap">
          <!-- WebSocket indicator -->
          <v-chip
            size="x-small"
            :color="wsConnected ? 'success' : 'grey'"
            variant="tonal"
            class="pms-ws-chip"
          >
            <span
              class="pms-ws-dot mr-1"
              :class="wsConnected ? 'pms-ws-dot--live' : ''"
              :style="{
                backgroundColor: wsConnected
                  ? 'rgb(var(--v-theme-success))'
                  : 'rgb(var(--v-theme-on-surface))',
              }"
            />
            {{ wsConnected ? "即時連線" : "連線中..." }}
          </v-chip>
          <v-btn
            variant="outlined"
            color="grey"
            prepend-icon="mdi-download"
            rounded="lg"
            size="small"
            :loading="exporting"
            @click="exportProject"
            >下載報告</v-btn
          >
          <v-btn
            v-if="canEdit"
            color="primary"
            prepend-icon="mdi-plus"
            rounded="lg"
            @click="openCreateTask"
            >新增任務</v-btn
          >
        </div>
      </div>

      <!-- Read-only banner -->
      <v-alert
        v-if="auth.canManageMembers && !canEdit"
        type="info"
        variant="tonal"
        density="compact"
        class="mt-3"
        icon="mdi-eye-outline"
      >
        你是此專案的觀察者，僅能查看，無法新增或修改任務
      </v-alert>

      <!-- 整體進度 ProgressBar -->
      <div class="mt-4">
        <div class="d-flex justify-space-between mb-1">
          <span class="text-caption text-medium-emphasis">整體進度</span>
          <span
            class="text-caption font-weight-bold pms-tnum"
            :class="project.progress_percent >= 100 ? 'text-success' : 'text-primary'"
          >
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

      <!-- 團隊成員 -->
      <div v-if="memberNames.length > 0" class="mt-4 d-flex align-center gap-3 flex-wrap">
        <span class="text-caption text-medium-emphasis">團隊成員</span>
        <AvatarStack :names="memberNames" :max="5" :size="28" />
        <v-btn variant="text" size="x-small" color="primary" @click="showMembersDialog = true">
          共 {{ memberNames.length }} 人
        </v-btn>
      </div>
    </v-card>

    <!-- ── Info Cards (4 張) ────────────────────────────────────────── -->
    <v-row class="mb-5" dense>
      <v-col cols="6" sm="3">
        <KPICard
          label="任務總數"
          :value="totalTasks"
          :sub="`已完成 ${completedCount} / ${totalTasks}`"
          icon="mdi-format-list-checks"
          icon-color="primary"
          accent="primary"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <KPICard
          label="逾期任務"
          :value="overdueCount"
          :sub="overdueCount > 0 ? '需要立即處理' : '目前無逾期'"
          icon="mdi-alert-circle-outline"
          icon-color="error"
          accent="error"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <KPICard
          label="進行中"
          :value="inProgressCount"
          sub="正在執行中的任務"
          icon="mdi-progress-clock"
          icon-color="info"
          accent="info"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <KPICard
          label="剩餘天數"
          :value="remainingDays === null ? '—' : `${remainingDays}`"
          :sub="remainingDaysSub"
          icon="mdi-calendar-clock"
          :icon-color="remainingDays !== null && remainingDays < 0 ? 'error' : 'warning'"
          :accent="remainingDays !== null && remainingDays < 0 ? 'error' : 'warning'"
        />
      </v-col>
    </v-row>

    <!-- ── Gantt Chart Card ───────────────────────────────────────── -->
    <v-card rounded="xl" class="mb-5">
      <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3 d-flex align-center gap-2">
        <v-icon icon="mdi-chart-gantt" size="18" color="primary" />
        甘特圖
      </v-card-title>
      <v-divider />
      <v-card-text class="pt-4">
        <EmptyState
          v-if="project.tasks.length === 0"
          icon="mdi-chart-gantt"
          title="尚無任務"
          sub="新增任務後甘特圖將自動顯示"
        />
        <GanttChart
          v-else
          :tasks="project.tasks"
          @task-click="openEditTask"
          @task-date-change="handleGanttDateChange"
        />
      </v-card-text>
    </v-card>

    <!-- ── Task Table Card ─────────────────────────────────────────── -->
    <v-card rounded="xl" class="mb-5">
      <v-card-title class="text-body-1 font-weight-semibold border-b">
        <div class="d-flex align-center gap-4 py-4 w-100">
          <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
          任務列表
          <v-text-field
            v-model="taskSearch"
            prepend-inner-icon="mdi-magnify"
            placeholder="搜尋任務..."
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            style="max-width: 280px"
          />
          <ChipGroup v-model="taskTab" :items="taskTabOptions" />
        </div>
      </v-card-title>

      <v-data-table
        :headers="taskHeaders"
        :items="filteredTasks"
        :search="taskSearch"
        hover
        item-value="id"
        @click:row="(_e: Event, { item }: { item: Task }) => openEditTask(item)"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-icon v-if="item.is_completed" icon="mdi-check-circle" size="16" color="success" />
            <v-icon
              v-else-if="isTaskOverdue(item)"
              icon="mdi-alert-circle"
              size="16"
              color="error"
            />
            <span
              :class="
                item.is_completed
                  ? 'text-grey text-decoration-line-through'
                  : 'text-body-2 font-weight-medium'
              "
            >
              {{ item.name }}
            </span>
            <TaskMetaBadges :attachments-count="attachmentCountForTask(item.id)" />
          </div>
        </template>

        <template #item.assignee="{ item }">
          <div v-if="item.assignee" class="d-flex align-center gap-2">
            <v-avatar color="primary" size="26" class="mr-2">
              <span class="text-caption text-white font-weight-bold">{{
                item.assignee.name.charAt(0)
              }}</span>
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
            :prepend-icon="statusIcon(item.status.icon)"
            :style="{ backgroundColor: item.status.color + '22', color: item.status.color }"
            class="font-weight-medium"
          >
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
          <div class="d-flex align-center gap-2" style="min-width: 110px">
            <v-progress-linear
              :model-value="item.progress"
              :color="item.is_completed ? 'success' : isTaskOverdue(item) ? 'error' : 'primary'"
              bg-color="grey-lighten-3"
              rounded
              height="5"
              class="flex-grow-1"
            />
            <span class="text-caption text-grey-darken-1 pms-tnum">{{ item.progress }}%</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div v-if="canEdit" class="d-flex gap-1" @click.stop>
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              color="grey"
              @click="openEditTask(item)"
            />
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="handleDeleteTask(item)"
            />
          </div>
        </template>

        <template #no-data>
          <EmptyState
            icon="mdi-format-list-checks"
            :title="emptyTaskTitle"
            sub="切換上方分頁或新增任務"
          />
        </template>
      </v-data-table>
    </v-card>

    <!-- ── Attachments Panel ──────────────────────────────────────── -->
    <v-card rounded="xl">
      <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-3 d-flex align-center gap-2">
        <v-icon icon="mdi-paperclip" size="18" color="primary" />
        附件
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-5">
        <AttachmentsPanel :project-id="project.id" />
      </v-card-text>
    </v-card>

    <!-- Members dialog -->
    <v-dialog v-model="showMembersDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
          <span>專案成員（{{ memberNames.length }}）</span>
          <v-btn icon="mdi-close" variant="text" size="small" @click="showMembersDialog = false" />
        </v-card-title>
        <v-divider />
        <v-list density="comfortable">
          <v-list-item v-for="m in project.members" :key="m.id">
            <template #prepend>
              <v-avatar color="primary" size="32">
                <span class="text-caption text-white font-weight-bold">{{ m.name.charAt(0) }}</span>
              </v-avatar>
            </template>
            <v-list-item-title class="text-body-2">{{ m.name }}</v-list-item-title>
            <template #append>
              <v-chip size="x-small" variant="tonal" color="primary">
                {{ m.pivot.role }}
              </v-chip>
            </template>
          </v-list-item>
        </v-list>
      </v-card>
    </v-dialog>

    <!-- Task Modal -->
    <TaskModal
      v-if="showTaskModal"
      :task="editingTask"
      :project-id="project.id"
      @close="showTaskModal = false"
      @saved="showTaskModal = false"
    />
  </div>

  <EmptyState
    v-else
    icon="mdi-folder-question-outline"
    title="找不到專案"
    sub="該專案可能已被刪除或你沒有檢視權限"
    class="py-12"
  />
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useProjectStore, type Task } from "@/stores/project";
import { useAttachmentStore } from "@/stores/attachment";
import GanttChart from "@/components/GanttChart.vue";
import TaskModal from "@/components/TaskModal.vue";
import KPICard from "@/components/ui/KPICard.vue";
import AvatarStack from "@/components/ui/AvatarStack.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import ChipGroup from "@/components/ui/ChipGroup.vue";
import TaskMetaBadges from "@/components/ui/TaskMetaBadges.vue";
import AttachmentsPanel from "@/components/project/AttachmentsPanel.vue";
import getEcho from "@/lib/echo";
import api from "@/lib/axios";

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const store = useProjectStore();
const attachmentStore = useAttachmentStore();

const showTaskModal = ref(false);
const showMembersDialog = ref(false);
const editingTask = ref<Task | null>(null);
const wsConnected = ref(false);
const taskSearch = ref("");
const exporting = ref(false);
const taskTab = ref<"all" | "pending" | "in_progress" | "completed" | "overdue">("all");

async function exportProject() {
  if (!project.value) return;
  exporting.value = true;
  try {
    const res = await api.get(`/projects/${project.value.id}/export`, { responseType: "blob" });
    const url = URL.createObjectURL(res.data);
    const a = document.createElement("a");
    a.href = url;
    a.download = `專案報告_${project.value.name}_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
  } finally {
    exporting.value = false;
  }
}

const project = computed(() => store.current);
const totalTasks = computed(() => project.value?.tasks.length ?? 0);
const completedCount = computed(
  () => project.value?.tasks.filter((t) => t.is_completed).length ?? 0,
);
const overdueCount = computed(
  () => project.value?.tasks.filter((t) => isTaskOverdue(t)).length ?? 0,
);
const inProgressCount = computed(
  () =>
    project.value?.tasks.filter((t) => !t.is_completed && t.progress > 0 && t.progress < 100)
      .length ?? 0,
);

const memberNames = computed(() => project.value?.members.map((m) => m.name) ?? []);

// Manager 是否有此專案的編輯權（自己是 owner，或是 admin）
const canEdit = computed(() => {
  if (!project.value) return false;
  if (auth.isAdmin) return true;
  return project.value.owner?.id === auth.user?.id;
});

function formatDate(d: string | null | undefined) {
  if (!d) return "未設定";
  return d.slice(0, 10);
}

const remainingDays = computed<number | null>(() => {
  if (!project.value?.due_date) return null;
  if (project.value.is_completed) return null;
  const due = new Date(project.value.due_date);
  const now = new Date();
  const ms = due.getTime() - now.getTime();
  return Math.ceil(ms / (1000 * 60 * 60 * 24));
});

const remainingDaysSub = computed(() => {
  if (project.value?.is_completed) return "已完成";
  if (remainingDays.value === null) return "未設定截止日";
  if (remainingDays.value < 0) return `已逾期 ${Math.abs(remainingDays.value)} 天`;
  if (remainingDays.value === 0) return "今日截止";
  return `距離截止 ${remainingDays.value} 天`;
});

// Task tabs
const taskTabOptions = computed(() => {
  const tasks = project.value?.tasks ?? [];
  return [
    { value: "all", label: "全部", count: tasks.length },
    {
      value: "pending",
      label: "待處理",
      count: tasks.filter((t) => t.progress === 0 && !t.is_completed).length,
    },
    {
      value: "in_progress",
      label: "進行中",
      count: tasks.filter((t) => t.progress > 0 && t.progress < 100 && !t.is_completed).length,
    },
    { value: "completed", label: "已完成", count: tasks.filter((t) => t.is_completed).length },
    { value: "overdue", label: "逾期", count: tasks.filter((t) => isTaskOverdue(t)).length },
  ];
});

const filteredTasks = computed(() => {
  const tasks = project.value?.tasks ?? [];
  if (taskTab.value === "completed") return tasks.filter((t) => t.is_completed);
  if (taskTab.value === "overdue") return tasks.filter((t) => isTaskOverdue(t));
  if (taskTab.value === "pending") return tasks.filter((t) => t.progress === 0 && !t.is_completed);
  if (taskTab.value === "in_progress")
    return tasks.filter((t) => t.progress > 0 && t.progress < 100 && !t.is_completed);
  return tasks;
});

const emptyTaskTitle = computed(() => {
  switch (taskTab.value) {
    case "overdue":
      return "沒有逾期任務";
    case "completed":
      return "尚無已完成任務";
    case "pending":
      return "沒有待處理任務";
    case "in_progress":
      return "沒有進行中任務";
    default:
      return "目前沒有任務";
  }
});

const taskHeaders = [
  { title: "任務名稱", key: "name", sortable: true },
  { title: "負責人", key: "assignee", sortable: false },
  { title: "開始", key: "start_date", sortable: true },
  { title: "結束", key: "end_date", sortable: true },
  { title: "狀態", key: "status", sortable: false },
  { title: "優先級", key: "priority", sortable: false },
  { title: "進度", key: "progress", sortable: true, width: "150px" },
  { title: "", key: "actions", sortable: false, width: "80px" },
];

function isTaskOverdue(task: Task) {
  return !task.is_completed && new Date(task.end_date) < new Date();
}

function statusIcon(icon: string | null | undefined) {
  if (!icon) return "mdi-circle-outline";
  const normalized = icon.replace(/_/g, "-");
  return normalized.startsWith("mdi-") ? normalized : `mdi-${normalized}`;
}

function attachmentCountForTask(taskId: number): number {
  if (!project.value) return 0;
  const list = attachmentStore.byProject[project.value.id] ?? [];
  return list.filter((a) => a.task_id === taskId).length;
}

function openCreateTask() {
  editingTask.value = null;
  showTaskModal.value = true;
}

function openEditTask(task: Task) {
  editingTask.value = task;
  showTaskModal.value = true;
}

async function handleDeleteTask(task: Task) {
  if (!confirm(`確定要刪除任務「${task.name}」？`)) return;
  if (project.value) await store.deleteTask(project.value.id, task.id);
}

async function handleGanttDateChange(taskId: number, start: string, end: string) {
  if (!project.value) return;
  await store.updateTask(project.value.id, taskId, { start_date: start, end_date: end });
}

// WebSocket
let channelLeave: (() => void) | null = null;

function subscribeToChannel(projectId: number) {
  const echo = getEcho();
  if (!echo) return;

  const channel = echo.private(`project.${projectId}`);
  channel.subscribed(() => {
    wsConnected.value = true;
  });

  channel.listen(".TaskSaved", (data: { task: Task }) => {
    if (!store.current || store.current.id !== projectId) return;
    const idx = store.current.tasks.findIndex((t) => t.id === data.task.id);
    if (idx !== -1) store.current.tasks[idx] = data.task;
    else store.current.tasks.push(data.task);
  });

  channel.listen(".TaskDeleted", (data: { task_id: number }) => {
    if (!store.current || store.current.id !== projectId) return;
    store.current.tasks = store.current.tasks.filter((t) => t.id !== data.task_id);
  });

  channel.listen(
    ".ProjectProgressUpdated",
    (data: { project_id: number; progress_percent: number }) => {
      if (!store.current || store.current.id !== data.project_id) return;
      store.current.progress_percent = data.progress_percent;
    },
  );

  channelLeave = () => echo.leave(`project.${projectId}`);
}

onMounted(async () => {
  const id = Number(route.params.id);
  await store.fetchDetail(id);
  subscribeToChannel(id);
});

onBeforeUnmount(() => {
  channelLeave?.();
  wsConnected.value = false;
});
</script>

<style scoped>
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.pms-ws-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 999px;
}
.pms-ws-dot--live {
  animation: pms-pulse 1.4s ease-in-out infinite;
}
@keyframes pms-pulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.45;
    transform: scale(1.4);
  }
}
</style>
