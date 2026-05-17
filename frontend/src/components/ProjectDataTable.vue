<template>
  <v-card rounded="xl">
    <v-data-table
      :headers="headers"
      :items="projects"
      :loading="loading"
      :search="search"
      hover
      item-value="id"
      @click:row="(_e: Event, { item }: { item: ProjectListItem }) => router.push(`/projects/${item.id}`)"
    >
      <template #top>
        <v-toolbar flat rounded="t-xl">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            placeholder="搜尋專案..."
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            class="mx-4 my-2"
            style="max-width:300px"
          />
        </v-toolbar>
      </template>

      <template #item.name="{ item }">
        <div>
          <div class="text-body-2 font-weight-medium">{{ item.name }}</div>
          <div v-if="item.project_no" class="text-caption text-grey">{{ item.project_no }}</div>
        </div>
      </template>

      <template #item.owner="{ item }">
        <div v-if="item.owner" class="d-flex align-center gap-2">
          <v-avatar color="primary" size="26">
            <span class="text-caption text-white font-weight-bold">{{ item.owner.name.charAt(0) }}</span>
          </v-avatar>
          <span class="text-body-2">{{ item.owner.name }}</span>
        </div>
      </template>

      <template #item.start_date="{ item }">
        <span class="text-body-2">{{ item.start_date?.slice(0, 10) }}</span>
      </template>

      <template #item.due_date="{ item }">
        <span :class="isOverdue(item) ? 'text-error font-weight-medium' : 'text-body-2'">
          <v-icon v-if="isOverdue(item)" icon="mdi-alert-circle" size="14" color="error" class="mr-1" />
          {{ item.due_date ? item.due_date.slice(0, 10) : '—' }}
        </span>
      </template>

      <template #item.progress_percent="{ item }">
        <div class="d-flex align-center gap-2" style="min-width:120px">
          <v-progress-linear
            :model-value="item.progress_percent"
            :color="item.progress_percent >= 100 ? 'success' : 'primary'"
            bg-color="grey-lighten-3"
            rounded
            height="5"
            class="flex-grow-1"
          />
          <span class="text-caption text-grey-darken-1">{{ item.progress_percent }}%</span>
        </div>
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
          {{ item.status.name }}
        </v-chip>
      </template>

      <template #item.actions="{ item }">
        <div v-if="auth.canManageMembers" class="d-flex gap-1" @click.stop>
          <v-btn icon="mdi-pencil" size="small" variant="text" color="grey" @click="emit('edit', item)" />
          <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="emit('delete', item)" />
        </div>
      </template>

      <template #no-data>
        <div class="text-center py-8 text-grey">目前沒有專案，點擊「新增專案」開始</div>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { ProjectListItem } from '@/stores/project'

defineProps<{ projects: ProjectListItem[]; loading?: boolean }>()
const emit = defineEmits<{ edit: [p: ProjectListItem]; delete: [p: ProjectListItem] }>()

const auth = useAuthStore()
const router = useRouter()
const search = ref('')

const headers = [
  { title: '專案名稱',   key: 'name',             sortable: true },
  { title: '負責人',    key: 'owner',             sortable: false },
  { title: '開始日期',   key: 'start_date',        sortable: true },
  { title: '預計結束',   key: 'due_date',          sortable: true },
  { title: '進度',     key: 'progress_percent',   sortable: true, width: '160px' },
  { title: '優先級',    key: 'priority',           sortable: false },
  { title: '狀態',     key: 'status',             sortable: false },
  { title: '',        key: 'actions',            sortable: false, width: '80px' },
]

function isOverdue(project: ProjectListItem) {
  if (!project.due_date || project.is_completed) return false
  return new Date(project.due_date) < new Date()
}
</script>
