<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h6 font-weight-bold">首頁總覽</h2>
      <p class="text-body-2 text-grey">歡迎回來，{{ auth.user?.name }}，以下是今日專案概況</p>
    </div>

    <div v-if="store.loading" class="d-flex justify-center align-center py-16">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="store.stats">
      <!-- Stat Cards -->
      <v-row class="mb-5">
        <v-col v-for="card in statCards" :key="card.label" cols="6" sm="4" md="2">
          <v-card
            rounded="xl"
            height="100%"
            :to="card.to"
            :style="card.to ? 'cursor:pointer' : ''"
            :hover="!!card.to"
          >
            <v-card-text class="pa-4">
              <div class="d-flex align-center gap-2 mb-2">
                <v-icon :icon="card.icon" :color="card.iconColor" size="20" />
                <span class="text-caption text-grey">{{ card.label }}</span>
              </div>
              <div class="text-h5 font-weight-bold" :class="card.color">{{ card.value }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Member: my tasks panel -->
      <template v-if="auth.user?.role === 'member'">
        <div class="mb-4">
          <h3 class="text-body-1 font-weight-semibold mb-3">我的任務</h3>
          <div v-if="myTasksLoading" class="d-flex justify-center py-6"><v-progress-circular indeterminate /></div>
          <v-row v-else-if="myTasks" dense>
            <!-- Overdue -->
            <v-col cols="12" md="4">
              <v-card rounded="xl" variant="tonal" color="error">
                <v-card-title class="text-body-2 font-weight-semibold pa-4 pb-1 d-flex align-center gap-2">
                  <v-icon size="16" color="error">mdi-alert-circle</v-icon>
                  逾期任務
                  <v-chip size="x-small" color="error" class="ml-auto">{{ myTasks.overdue.length }}</v-chip>
                </v-card-title>
                <v-card-text class="pa-2">
                  <div v-if="!myTasks.overdue.length" class="text-caption text-grey text-center py-3">無逾期任務</div>
                  <v-list density="compact" bg-color="transparent">
                    <v-list-item
                      v-for="t in myTasks.overdue"
                      :key="t.id"
                      :to="`/projects/${t.project_id}`"
                      rounded="lg"
                      class="px-2 py-1"
                    >
                      <v-list-item-title class="text-body-2">{{ t.name }}</v-list-item-title>
                      <v-list-item-subtitle class="text-caption">{{ t.project_name }} · {{ t.end_date }}</v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-card-text>
              </v-card>
            </v-col>

            <!-- Due soon -->
            <v-col cols="12" md="4">
              <v-card rounded="xl" variant="tonal" color="warning">
                <v-card-title class="text-body-2 font-weight-semibold pa-4 pb-1 d-flex align-center gap-2">
                  <v-icon size="16" color="warning">mdi-clock-alert-outline</v-icon>
                  即將到期
                  <v-chip size="x-small" color="warning" class="ml-auto">{{ myTasks.due_soon.length }}</v-chip>
                </v-card-title>
                <v-card-text class="pa-2">
                  <div v-if="!myTasks.due_soon.length" class="text-caption text-grey text-center py-3">無即將到期任務</div>
                  <v-list density="compact" bg-color="transparent">
                    <v-list-item
                      v-for="t in myTasks.due_soon"
                      :key="t.id"
                      :to="`/projects/${t.project_id}`"
                      rounded="lg"
                      class="px-2 py-1"
                    >
                      <v-list-item-title class="text-body-2">{{ t.name }}</v-list-item-title>
                      <v-list-item-subtitle class="text-caption">{{ t.project_name }} · {{ t.end_date }}</v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-card-text>
              </v-card>
            </v-col>

            <!-- In progress -->
            <v-col cols="12" md="4">
              <v-card rounded="xl" variant="tonal" color="primary">
                <v-card-title class="text-body-2 font-weight-semibold pa-4 pb-1 d-flex align-center gap-2">
                  <v-icon size="16" color="primary">mdi-progress-clock</v-icon>
                  進行中
                  <v-chip size="x-small" color="primary" class="ml-auto">{{ myTasks.in_progress.length }}</v-chip>
                </v-card-title>
                <v-card-text class="pa-2">
                  <div v-if="!myTasks.in_progress.length" class="text-caption text-grey text-center py-3">無進行中任務</div>
                  <v-list density="compact" bg-color="transparent">
                    <v-list-item
                      v-for="t in myTasks.in_progress"
                      :key="t.id"
                      :to="`/projects/${t.project_id}`"
                      rounded="lg"
                      class="px-2 py-1"
                    >
                      <v-list-item-title class="text-body-2">{{ t.name }}</v-list-item-title>
                      <v-list-item-subtitle class="text-caption d-flex align-center gap-1">
                        {{ t.project_name }}
                        <v-progress-linear :model-value="t.progress" color="primary" bg-color="grey-lighten-3" rounded height="4" class="ml-1" style="max-width:60px" />
                        <span>{{ t.progress }}%</span>
                      </v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </div>
      </template>

      <!-- Progress ring + Project list -->
      <v-row class="mb-2">
        <!-- Overall progress -->
        <v-col cols="12" lg="4">
          <v-card rounded="xl" height="100%">
            <v-card-title class="text-body-1 font-weight-semibold pa-5 pb-0">專案整體進度</v-card-title>
            <v-card-text class="d-flex flex-column align-center pt-4">
              <!-- SVG ring -->
              <div class="position-relative" style="width:120px;height:120px">
                <svg width="120" height="120" viewBox="0 0 36 36" style="transform:rotate(-90deg)">
                  <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e0e0e0" stroke-width="2.5" />
                  <circle
                    cx="18" cy="18" r="15.9"
                    fill="none"
                    stroke="#00897B"
                    stroke-width="2.5"
                    :stroke-dasharray="`${overallProgress} ${100 - overallProgress}`"
                    stroke-linecap="round"
                  />
                </svg>
                <div class="position-absolute inset-0 d-flex flex-column align-center justify-center" style="top:0;left:0;right:0;bottom:0;position:absolute">
                  <span class="text-h6 font-weight-bold">{{ overallProgress }}%</span>
                  <span class="text-caption text-grey">整體進度</span>
                </div>
              </div>

              <v-list density="compact" class="w-100 mt-3 pa-0">
                <v-list-item class="px-0 py-1">
                  <template #title><span class="text-caption text-grey">已完成專案</span></template>
                  <template #append><span class="text-caption font-weight-bold text-success">{{ store.stats.completed_projects }}</span></template>
                </v-list-item>
                <v-divider />
                <v-list-item class="px-0 py-1">
                  <template #title><span class="text-caption text-grey">進行中專案</span></template>
                  <template #append><span class="text-caption font-weight-bold text-primary">{{ store.stats.active_projects }}</span></template>
                </v-list-item>
                <v-divider />
                <v-list-item class="px-0 py-1">
                  <template #title><span class="text-caption text-grey">逾期任務</span></template>
                  <template #append>
                    <span class="text-caption font-weight-bold" :class="store.stats.overdue_tasks > 0 ? 'text-error' : 'text-grey'">
                      {{ store.stats.overdue_tasks }}
                    </span>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Project list -->
        <v-col cols="12" lg="8">
          <v-card rounded="xl">
            <v-card-title class="d-flex align-center justify-space-between pa-5 pb-0">
              <span class="text-body-1 font-weight-semibold">專案列表</span>
              <v-btn variant="text" color="primary" size="small" to="/projects" density="compact">查看全部</v-btn>
            </v-card-title>
            <v-card-text class="pa-0">
              <v-list lines="two" class="pa-0">
                <template v-for="(project, idx) in store.projects.slice(0, 8)" :key="project.id">
                  <v-list-item
                    :to="`/projects/${project.id}`"
                    rounded="0"
                    class="px-5 py-3"
                  >
                    <template #title>
                      <div class="d-flex align-center gap-2">
                        <span class="text-body-2 font-weight-medium">{{ project.name }}</span>
                        <v-chip
                          v-if="project.status"
                          size="x-small"
                          :style="{ backgroundColor: project.status.color + '22', color: project.status.color }"
                          class="font-weight-medium"
                        >
                          {{ project.status.name }}
                        </v-chip>
                      </div>
                    </template>
                    <template #subtitle>
                      <div class="d-flex align-center gap-2 mt-1">
                        <v-progress-linear
                          :model-value="project.progress_percent"
                          :color="project.progress_percent >= 100 ? 'success' : 'primary'"
                          bg-color="grey-lighten-3"
                          rounded
                          height="5"
                          class="flex-grow-1"
                        />
                        <span class="text-caption text-grey-darken-1 shrink-0">{{ project.progress_percent }}%</span>
                      </div>
                    </template>
                    <template #append>
                      <v-chip
                        v-if="project.priority"
                        size="x-small"
                        :style="{ backgroundColor: project.priority.color + '22', color: project.priority.color }"
                        class="font-weight-medium"
                      >
                        {{ project.priority.name }}
                      </v-chip>
                    </template>
                  </v-list-item>
                  <v-divider v-if="idx < Math.min(store.projects.length, 8) - 1" />
                </template>
                <v-list-item v-if="store.projects.length === 0" class="py-8 text-center">
                  <v-list-item-title class="text-body-2 text-grey">目前沒有專案</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import axios from 'axios'

const auth = useAuthStore()
const store = useDashboardStore()

interface MyTask {
  id: number; name: string; project_id: number; project_name: string
  end_date: string | null; progress: number; is_overdue: boolean
  status: { name: string; color: string; icon: string } | null
  priority: { name: string; color: string } | null
}
interface MyTasks { overdue: MyTask[]; due_soon: MyTask[]; in_progress: MyTask[] }

const myTasks = ref<MyTasks | null>(null)
const myTasksLoading = ref(false)

async function fetchMyTasks() {
  myTasksLoading.value = true
  const res = await axios.get('/api/dashboard/my-tasks')
  myTasks.value = res.data
  myTasksLoading.value = false
}

const statCards = computed(() => {
  if (!store.stats) return []
  return [
    { label: '全部專案',   icon: 'mdi-folder-multiple',   iconColor: 'grey',    value: store.stats.total_projects,    color: '',             to: '/projects' },
    { label: '進行中',     icon: 'mdi-progress-clock',    iconColor: 'primary', value: store.stats.active_projects,   color: 'text-primary', to: '/projects' },
    { label: '已完成',     icon: 'mdi-check-circle',      iconColor: 'success', value: store.stats.completed_projects,color: 'text-success', to: '/projects' },
    { label: '全部任務',   icon: 'mdi-clipboard-list',    iconColor: 'grey',    value: store.stats.total_tasks,       color: '',             to: '/todo' },
    { label: '已完成任務', icon: 'mdi-clipboard-check',   iconColor: 'success', value: store.stats.completed_tasks,   color: 'text-success', to: '/todo' },
    {
      label: '逾期任務',
      icon: 'mdi-alert-circle',
      iconColor: store.stats.overdue_tasks > 0 ? 'error' : 'grey',
      value: store.stats.overdue_tasks,
      color: store.stats.overdue_tasks > 0 ? 'text-error' : 'text-grey',
      to: '/todo',
    },
  ]
})

const overallProgress = computed(() => {
  if (!store.projects.length) return 0
  return Math.round(store.projects.reduce((s, p) => s + p.progress_percent, 0) / store.projects.length)
})

onMounted(() => {
  store.fetch()
  if (auth.user?.role === 'member') fetchMyTasks()
})
</script>
