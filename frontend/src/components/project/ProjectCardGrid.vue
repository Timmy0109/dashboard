<script setup lang="ts">
// ProjectCardGrid — 卡片檢視，auto-fill minmax(280px)
import { useRouter } from 'vue-router'
import type { ProjectListItem } from '@/stores/project'
import EmptyState from '@/components/ui/EmptyState.vue'

defineProps<{ projects: ProjectListItem[]; loading?: boolean }>()

const router = useRouter()

function isOverdue(p: ProjectListItem) {
  if (!p.due_date || p.is_completed) return false
  return new Date(p.due_date) < new Date()
}

function go(id: number) {
  router.push(`/projects/${id}`)
}
</script>

<template>
  <div v-if="loading" class="d-flex justify-center py-12">
    <v-progress-circular indeterminate color="primary" />
  </div>

  <EmptyState
    v-else-if="projects.length === 0"
    icon="mdi-folder-outline"
    title="目前沒有專案"
    sub="點擊「新增專案」開始建立"
  />

  <div v-else class="pms-project-grid">
    <v-card
      v-for="p in projects"
      :key="p.id"
      rounded="xl"
      hover
      ripple
      class="pms-project-card"
      @click="go(p.id)"
    >
      <v-card-text class="pa-5">
        <div class="d-flex align-start justify-space-between gap-2 mb-2">
          <div style="min-width:0" class="flex-grow-1">
            <div class="text-body-1 font-weight-semibold text-truncate" :title="p.name">
              {{ p.name }}
            </div>
            <div v-if="p.project_no" class="text-caption text-medium-emphasis">
              {{ p.project_no }}
            </div>
          </div>
          <v-chip
            v-if="p.status"
            size="x-small"
            :style="{ backgroundColor: p.status.color + '22', color: p.status.color }"
            class="font-weight-medium"
          >
            {{ p.status.name }}
          </v-chip>
        </div>

        <div v-if="p.owner" class="d-flex align-center gap-2 mb-3">
          <v-avatar color="primary" size="22">
            <span class="text-caption text-white font-weight-bold">{{ p.owner.name.charAt(0) }}</span>
          </v-avatar>
          <span class="text-caption text-medium-emphasis">{{ p.owner.name }}</span>
        </div>

        <div class="d-flex justify-space-between mb-1">
          <span class="text-caption text-medium-emphasis">整體進度</span>
          <span
            class="text-caption font-weight-bold pms-tnum"
            :class="p.progress_percent >= 100 ? 'text-success' : 'text-primary'"
          >{{ p.progress_percent }}%</span>
        </div>
        <v-progress-linear
          :model-value="p.progress_percent"
          :color="p.progress_percent >= 100 ? 'success' : 'primary'"
          bg-color="grey-lighten-3"
          rounded
          height="6"
        />

        <div class="d-flex align-center justify-space-between mt-3">
          <div class="text-caption text-medium-emphasis d-flex align-center">
            <v-icon icon="mdi-calendar-end" size="12" class="mr-1" />
            <span :class="isOverdue(p) ? 'text-error font-weight-medium' : ''">
              {{ p.due_date ? p.due_date.slice(0, 10) : '未設定' }}
            </span>
          </div>
          <v-chip
            v-if="p.priority"
            size="x-small"
            :style="{ backgroundColor: p.priority.color + '22', color: p.priority.color }"
          >
            {{ p.priority.name }}
          </v-chip>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.pms-project-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}
.pms-project-card {
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pms-project-card:hover {
  transform: translateY(-1px);
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
