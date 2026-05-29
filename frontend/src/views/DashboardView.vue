<script setup lang="ts">
// DashboardView — 首頁總覽
// 6 KPI cards / member 我的任務 3 欄 / ProgressRing + 專案列表 / Activity Feed
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useActivityFeedStore } from '@/stores/activityFeed'
import api from '@/lib/axios'

import KPICard from '@/components/ui/KPICard.vue'
import ProgressRing from '@/components/ui/ProgressRing.vue'
import AvatarStack from '@/components/ui/AvatarStack.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ProjectModal from '@/components/ProjectModal.vue'

const router = useRouter()
const auth = useAuthStore()
const store = useDashboardStore()
const activityFeed = useActivityFeedStore()

interface MyTask {
  id: number; name: string; project_id: number; project_name: string
  end_date: string | null; progress: number; is_overdue: boolean
  status: { name: string; color: string; icon: string } | null
  priority: { name: string; color: string } | null
}
interface MyTasks { overdue: MyTask[]; due_soon: MyTask[]; in_progress: MyTask[] }

const myTasks = ref<MyTasks | null>(null)
const myTasksLoading = ref(false)
const refreshing = ref(false)
const showProjectModal = ref(false)

async function fetchMyTasks() {
  myTasksLoading.value = true
  try {
    const res = await api.get('/dashboard/my-tasks')
    myTasks.value = res.data
  } finally {
    myTasksLoading.value = false
  }
}

async function refreshAll() {
  refreshing.value = true
  try {
    await store.fetch()
    if (auth.user?.role === 'member') await fetchMyTasks()
    await activityFeed.fetch('company').catch(() => {})
  } finally {
    refreshing.value = false
  }
}

// Header subtitle: 今日日期 + 進行中/逾期
const today = computed(() => {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
})

const headerSub = computed(() => {
  if (!store.stats) return today.value
  return `${today.value} · 進行中 ${store.stats.active_projects} · 逾期任務 ${store.stats.overdue_tasks}`
})

const canCreateProject = computed(() =>
  auth.user?.role === 'manager' || auth.user?.role === 'admin'
)

// KPI cards
interface KPI {
  label: string
  value: number
  icon: string
  iconColor: string
  accent: 'primary' | 'success' | 'warning' | 'error' | 'info'
  to?: string
  trend?: string
}

const kpis = computed<KPI[]>(() => {
  if (!store.stats) return []
  const s = store.stats
  return [
    { label: '全部專案',   value: s.total_projects,     icon: 'mdi-folder-multiple', iconColor: 'primary', accent: 'primary', to: '/projects' },
    { label: '進行中',     value: s.active_projects,    icon: 'mdi-progress-clock',  iconColor: 'info',    accent: 'info',    to: '/projects' },
    { label: '已完成',     value: s.completed_projects, icon: 'mdi-check-circle',    iconColor: 'success', accent: 'success', to: '/projects' },
    { label: '全部任務',   value: s.total_tasks,        icon: 'mdi-clipboard-list',  iconColor: 'primary', accent: 'primary', to: '/todo' },
    { label: '已完成任務', value: s.completed_tasks,    icon: 'mdi-clipboard-check', iconColor: 'success', accent: 'success', to: '/todo' },
    {
      label: '逾期任務',
      value: s.overdue_tasks,
      icon: 'mdi-alert-circle',
      iconColor: s.overdue_tasks > 0 ? 'error' : 'grey',
      accent: s.overdue_tasks > 0 ? 'error' : 'warning',
      trend: s.overdue_tasks > 0 ? '需注意' : undefined,
      to: '/todo',
    },
  ]
})

const overallProgress = computed(() => {
  if (!store.projects.length) return 0
  return Math.round(store.projects.reduce((sum, p) => sum + p.progress_percent, 0) / store.projects.length)
})

// Member 我的任務 cards
const myTaskColumns = computed(() => {
  const t = myTasks.value
  if (!t) return []
  return [
    {
      key: 'overdue',
      title: '逾期任務',
      icon: 'mdi-alert-circle',
      accent: 'error' as const,
      tasks: t.overdue.slice(0, 5),
      emptyText: '目前沒有逾期任務',
    },
    {
      key: 'due_soon',
      title: '即將到期',
      icon: 'mdi-clock-alert-outline',
      accent: 'warning' as const,
      tasks: t.due_soon.slice(0, 5),
      emptyText: '近期沒有即將到期的任務',
    },
    {
      key: 'in_progress',
      title: '進行中',
      icon: 'mdi-progress-clock',
      accent: 'primary' as const,
      tasks: t.in_progress.slice(0, 5),
      emptyText: '尚未開始任何進行中的任務',
    },
  ]
})

// Activity feed
const recentActivity = computed(() => activityFeed.items.slice(0, 8))

function verbFor(event: string): string {
  const map: Record<string, string> = {
    'task.created':   '建立了任務',
    'task.updated':   '更新了任務',
    'task.completed': '完成了任務',
    'task.deleted':   '刪除了任務',
    'task.assigned':  '指派了任務',
    'comment.created':'留言於任務',
    'attachment.uploaded': '上傳了附件至',
  }
  return map[event] ?? '更新了'
}

function timeAgo(iso: string): string {
  const now = Date.now()
  const t = new Date(iso).getTime()
  const diff = Math.max(0, now - t)
  const min = Math.floor(diff / 60_000)
  if (min < 1)   return '剛剛'
  if (min < 60)  return `${min} 分鐘前`
  const hr = Math.floor(min / 60)
  if (hr < 24)   return `${hr} 小時前`
  const day = Math.floor(hr / 24)
  if (day < 7)   return `${day} 天前`
  return iso.slice(0, 10)
}

function onProjectSaved() {
  showProjectModal.value = false
  void store.fetch()
}

onMounted(async () => {
  await store.fetch()
  if (auth.user?.role === 'member') void fetchMyTasks()
  // 公司活動 feed：後端依 auth 自動限縮，前端僅請求 scope=company
  void activityFeed.fetch('company').catch(() => {})
})

onBeforeUnmount(() => {
  activityFeed.stopListening()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-start justify-space-between flex-wrap gap-3 mb-6">
      <div>
        <h2 class="text-h5 font-weight-bold">
          {{ auth.user?.name ? `歡迎回來，${auth.user.name}` : '首頁總覽' }}
        </h2>
        <p class="text-body-2 text-medium-emphasis mt-1 mb-0">{{ headerSub }}</p>
      </div>
      <div class="d-flex align-center gap-2">
        <v-btn
          variant="outlined"
          prepend-icon="mdi-refresh"
          :loading="refreshing"
          @click="refreshAll"
        >
          刷新
        </v-btn>
        <v-btn
          v-if="canCreateProject"
          color="primary"
          variant="flat"
          prepend-icon="mdi-plus"
          @click="showProjectModal = true"
        >
          新增專案
        </v-btn>
      </div>
    </div>

    <div v-if="store.loading && !store.stats" class="d-flex justify-center align-center py-16">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="store.stats">
      <!-- KPI 6 cards -->
      <v-row class="mb-5" dense>
        <v-col v-for="card in kpis" :key="card.label" cols="6" sm="4" md="2">
          <KPICard
            :label="card.label"
            :value="card.value"
            :icon="card.icon"
            :icon-color="card.iconColor"
            :accent="card.accent"
            :trend="card.trend"
            :to="card.to"
          />
        </v-col>
      </v-row>

      <!-- Member-only 我的任務 -->
      <template v-if="auth.user?.role === 'member'">
        <h3 class="text-body-1 font-weight-medium mb-3">我的任務</h3>
        <div v-if="myTasksLoading" class="d-flex justify-center py-6">
          <v-progress-circular indeterminate color="primary" />
        </div>
        <v-row v-else-if="myTasks" class="mb-5" dense>
          <v-col
            v-for="col in myTaskColumns"
            :key="col.key"
            cols="12"
            md="4"
          >
            <v-card
              rounded="xl"
              class="pms-my-task-card position-relative overflow-hidden h-100"
            >
              <div
                class="pms-my-task-card__accent"
                :style="{ background: `rgb(var(--v-theme-${col.accent}))` }"
              />
              <v-card-title class="d-flex align-center gap-2 pa-4 pb-2">
                <v-icon :icon="col.icon" :color="col.accent" size="18" />
                <span class="text-body-2 font-weight-medium">{{ col.title }}</span>
                <v-spacer />
                <v-chip size="x-small" :color="col.accent" variant="tonal">
                  {{ col.tasks.length }}
                </v-chip>
              </v-card-title>
              <v-divider />
              <v-card-text class="pa-2">
                <div
                  v-if="!col.tasks.length"
                  class="text-caption text-medium-emphasis text-center py-6"
                >
                  {{ col.emptyText }}
                </div>
                <v-list v-else density="compact" bg-color="transparent" class="pa-0">
                  <v-list-item
                    v-for="t in col.tasks"
                    :key="t.id"
                    :to="`/projects/${t.project_id}`"
                    rounded="lg"
                    class="px-2 py-2"
                  >
                    <v-list-item-title class="text-body-2">{{ t.name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption mt-1 d-flex align-center gap-2 flex-wrap">
                      <span>{{ t.project_name }}</span>
                      <span v-if="t.end_date" class="text-medium-emphasis">· {{ t.end_date }}</span>
                      <template v-if="col.key === 'in_progress'">
                        <v-progress-linear
                          :model-value="t.progress"
                          color="primary"
                          bg-color="grey-lighten-3"
                          rounded
                          height="4"
                          style="max-width: 64px"
                          class="ml-1"
                        />
                        <span class="text-medium-emphasis">{{ t.progress }}%</span>
                      </template>
                    </v-list-item-subtitle>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </template>

      <!-- 整體進度 + 專案列表 -->
      <v-row class="mb-5" align="stretch">
        <!-- 整體進度 -->
        <v-col cols="12" lg="4">
          <v-card rounded="xl" class="h-100">
            <v-card-title class="text-body-1 font-weight-medium pa-5 pb-0">
              專案整體進度
            </v-card-title>
            <v-card-text class="d-flex flex-column align-center pt-5">
              <ProgressRing
                :value="overallProgress"
                :size="140"
                :stroke="3"
                sub="整體進度"
              />

              <v-list density="compact" bg-color="transparent" class="w-100 mt-4 pa-0">
                <v-list-item class="px-0 py-1">
                  <template #title>
                    <span class="text-caption text-medium-emphasis">已完成專案</span>
                  </template>
                  <template #append>
                    <span class="text-body-2 font-weight-bold text-success">
                      {{ store.stats.completed_projects }}
                    </span>
                  </template>
                </v-list-item>
                <v-divider />
                <v-list-item class="px-0 py-1">
                  <template #title>
                    <span class="text-caption text-medium-emphasis">進行中專案</span>
                  </template>
                  <template #append>
                    <span class="text-body-2 font-weight-bold text-primary">
                      {{ store.stats.active_projects }}
                    </span>
                  </template>
                </v-list-item>
                <v-divider />
                <v-list-item class="px-0 py-1">
                  <template #title>
                    <span class="text-caption text-medium-emphasis">逾期任務</span>
                  </template>
                  <template #append>
                    <span
                      class="text-body-2 font-weight-bold"
                      :class="store.stats.overdue_tasks > 0 ? 'text-error' : 'text-medium-emphasis'"
                    >
                      {{ store.stats.overdue_tasks }}
                    </span>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- 專案列表 -->
        <v-col cols="12" lg="8">
          <v-card rounded="xl" class="h-100">
            <v-card-title class="d-flex align-center justify-space-between pa-5 pb-3">
              <span class="text-body-1 font-weight-medium">專案列表</span>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                to="/projects"
                density="compact"
              >
                查看全部
              </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0">
              <EmptyState
                v-if="store.projects.length === 0"
                icon="mdi-folder-open-outline"
                title="目前沒有專案"
                sub="點擊右上角『新增專案』開始建立第一個專案"
              />
              <v-list v-else lines="two" class="pa-0">
                <template
                  v-for="(project, idx) in store.projects.slice(0, 10)"
                  :key="project.id"
                >
                  <v-list-item
                    :to="`/projects/${project.id}`"
                    rounded="0"
                    class="px-5 py-3"
                  >
                    <template #title>
                      <div class="d-flex align-center gap-2 flex-wrap">
                        <span class="text-body-2 font-weight-medium">{{ project.name }}</span>
                        <v-chip
                          v-if="project.status"
                          size="x-small"
                          :style="{
                            backgroundColor: project.status.color + '22',
                            color: project.status.color,
                          }"
                          class="font-weight-medium"
                        >
                          {{ project.status.name }}
                        </v-chip>
                      </div>
                    </template>
                    <template #subtitle>
                      <div class="d-flex align-center gap-3 mt-2">
                        <AvatarStack
                          v-if="project.owner"
                          :names="[project.owner.name]"
                          :max="1"
                          :size="22"
                        />
                        <v-progress-linear
                          :model-value="project.progress_percent"
                          :color="project.progress_percent >= 100 ? 'success' : 'primary'"
                          bg-color="grey-lighten-3"
                          rounded
                          height="6"
                          class="flex-grow-1"
                        />
                        <span class="text-caption text-medium-emphasis" style="min-width: 36px; text-align: right">
                          {{ project.progress_percent }}%
                        </span>
                      </div>
                    </template>
                    <template #append>
                      <v-chip
                        v-if="project.priority"
                        size="x-small"
                        :style="{
                          backgroundColor: project.priority.color + '22',
                          color: project.priority.color,
                        }"
                        class="font-weight-medium ml-2"
                      >
                        {{ project.priority.name }}
                      </v-chip>
                    </template>
                  </v-list-item>
                  <v-divider v-if="idx < Math.min(store.projects.length, 10) - 1" />
                </template>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- 近期活動 -->
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 pa-5 pb-3">
          <v-icon icon="mdi-bell-outline" color="primary" size="20" />
          <span class="text-body-1 font-weight-medium">近期活動</span>
          <v-spacer />
          <v-chip size="x-small" color="primary" variant="tonal">
            {{ recentActivity.length }} 則
          </v-chip>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-0">
          <div v-if="activityFeed.loading && recentActivity.length === 0" class="d-flex justify-center py-6">
            <v-progress-circular indeterminate color="primary" size="24" />
          </div>
          <EmptyState
            v-else-if="recentActivity.length === 0"
            icon="mdi-bell-off-outline"
            title="目前沒有活動"
            sub="當團隊有新的任務、留言或附件時，會出現在這裡"
          />
          <v-list v-else lines="two" class="pa-0">
            <template v-for="(item, idx) in recentActivity" :key="item.id">
              <v-list-item
                :to="`/projects/${item.project_id}`"
                class="px-5 py-3"
              >
                <template #prepend>
                  <v-avatar size="32" color="primary">
                    <span class="text-caption font-weight-bold text-white">
                      {{ item.actor?.name?.charAt(0) ?? '?' }}
                    </span>
                  </v-avatar>
                </template>
                <template #title>
                  <div class="text-body-2">
                    <span class="font-weight-medium">{{ item.actor?.name ?? '系統' }}</span>
                    <span class="text-medium-emphasis"> {{ verbFor(item.event) }} </span>
                    <span class="font-weight-medium">{{ item.task_name }}</span>
                  </div>
                </template>
                <template #subtitle>
                  <span class="text-caption text-medium-emphasis">
                    {{ item.project_name }} · {{ timeAgo(item.created_at) }}
                  </span>
                </template>
              </v-list-item>
              <v-divider v-if="idx < recentActivity.length - 1" />
            </template>
          </v-list>
        </v-card-text>
      </v-card>
    </template>

    <!-- ProjectModal -->
    <ProjectModal
      v-if="showProjectModal"
      :project="null"
      @close="showProjectModal = false"
      @saved="onProjectSaved"
    />
  </div>
</template>

<style scoped>
.pms-my-task-card__accent {
  position: absolute;
  inset: 0 0 auto 0;
  height: 3px;
}
</style>
