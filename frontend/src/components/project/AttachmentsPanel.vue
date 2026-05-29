<script setup lang="ts">
// AttachmentsPanel — 列出整個專案的所有附件（cursor pagination）
// 支援副檔名 filter、依任務分組檢視、檔案 icon、上傳者、大小、建立時間。
import { computed, onMounted, ref, watch } from 'vue'
import { useAttachmentStore } from '@/stores/attachment'
import type { ProjectAttachment } from '@/types/attachment'
import EmptyState from '@/components/ui/EmptyState.vue'
import ChipGroup from '@/components/ui/ChipGroup.vue'

const props = defineProps<{ projectId: number }>()

const attachmentStore = useAttachmentStore()
const groupMode = ref<'all' | 'task'>('all')
const extFilter = ref<string>('all')

const items = computed<ProjectAttachment[]>(
  () => attachmentStore.byProject[props.projectId] ?? [],
)
const nextCursor = computed<string | null>(
  () => attachmentStore.nextCursorByProject[props.projectId] ?? null,
)
const loading = computed(() => attachmentStore.loading)

function fileExt(name: string): string {
  const dot = name.lastIndexOf('.')
  if (dot < 0 || dot === name.length - 1) return ''
  return name.slice(dot + 1).toLowerCase()
}

const availableExts = computed<string[]>(() => {
  const set = new Set<string>()
  for (const a of items.value) {
    const ext = fileExt(a.original_name)
    if (ext) set.add(ext)
  }
  return Array.from(set).sort()
})

const extItems = computed(() => [
  { value: 'all', label: '全部', count: items.value.length },
  ...availableExts.value.map(ext => ({
    value: ext,
    label: ext.toUpperCase(),
    count: items.value.filter(a => fileExt(a.original_name) === ext).length,
  })),
])

const filtered = computed<ProjectAttachment[]>(() => {
  if (extFilter.value === 'all') return items.value
  return items.value.filter(a => fileExt(a.original_name) === extFilter.value)
})

interface TaskGroup {
  task_id: number
  task_name: string
  attachments: ProjectAttachment[]
}

const groupedByTask = computed<TaskGroup[]>(() => {
  const map = new Map<number, TaskGroup>()
  for (const a of filtered.value) {
    const tid = a.task_id
    if (!map.has(tid)) {
      map.set(tid, { task_id: tid, task_name: a.task_name, attachments: [] })
    }
    map.get(tid)!.attachments.push(a)
  }
  return Array.from(map.values())
})

function iconFor(mime: string, name: string) {
  const ext = fileExt(name)
  if (mime.startsWith('image/')) return { icon: 'mdi-file-image-outline', color: 'success' }
  if (mime.startsWith('video/')) return { icon: 'mdi-file-video-outline', color: 'error' }
  if (mime.startsWith('audio/')) return { icon: 'mdi-file-music-outline', color: 'warning' }
  if (mime === 'application/pdf' || ext === 'pdf') return { icon: 'mdi-file-pdf-box', color: 'error' }
  if (['doc', 'docx'].includes(ext)) return { icon: 'mdi-file-word-box', color: 'primary' }
  if (['xls', 'xlsx', 'csv'].includes(ext)) return { icon: 'mdi-file-excel-box', color: 'success' }
  if (['ppt', 'pptx'].includes(ext)) return { icon: 'mdi-file-powerpoint-box', color: 'warning' }
  if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return { icon: 'mdi-folder-zip-outline', color: 'info' }
  return { icon: 'mdi-file-outline', color: 'grey' }
}

function formatDate(iso: string) {
  return iso.slice(0, 10)
}

async function refresh() {
  await attachmentStore.fetchForProject(props.projectId)
}

async function loadMore() {
  if (!nextCursor.value) return
  await attachmentStore.fetchForProject(props.projectId, { cursor: nextCursor.value })
}

onMounted(() => {
  refresh()
})

watch(() => props.projectId, () => {
  refresh()
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-3">
      <ChipGroup
        v-model="groupMode"
        :items="[
          { value: 'all', label: '全部檔案', count: filtered.length },
          { value: 'task', label: '依任務分組', count: groupedByTask.length },
        ]"
      />
      <ChipGroup
        v-if="availableExts.length > 0"
        v-model="extFilter"
        :items="extItems"
      />
    </div>

    <div v-if="loading && items.length === 0" class="d-flex justify-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <EmptyState
      v-else-if="filtered.length === 0"
      icon="mdi-paperclip"
      title="尚無附件"
      sub="此專案目前沒有任何附件"
    />

    <!-- 全部檔案 grid -->
    <div v-else-if="groupMode === 'all'" class="pms-att-grid">
      <v-card
        v-for="a in filtered"
        :key="a.id"
        rounded="xl"
        variant="outlined"
        class="pa-4"
      >
        <div class="d-flex align-start gap-3">
          <v-avatar
            :color="iconFor(a.mime_type, a.original_name).color"
            variant="tonal"
            size="44"
            rounded="lg"
          >
            <v-icon :icon="iconFor(a.mime_type, a.original_name).icon" size="22" />
          </v-avatar>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-body-2 font-weight-medium text-truncate" :title="a.original_name">
              {{ a.original_name }}
            </div>
            <RouterLink
              :to="`/projects/${projectId}?task=${a.task_id}`"
              class="text-caption text-primary text-decoration-none d-inline-flex align-center mt-1"
            >
              <v-icon icon="mdi-format-list-checks" size="12" class="mr-1" />
              {{ a.task_name }}
            </RouterLink>
            <div class="text-caption text-medium-emphasis mt-2 d-flex align-center flex-wrap gap-x-3 gap-y-1">
              <span class="d-inline-flex align-center">
                <v-icon icon="mdi-database-outline" size="12" class="mr-1" />
                {{ a.size_human }}
              </span>
              <span v-if="a.uploader" class="d-inline-flex align-center">
                <v-icon icon="mdi-account-outline" size="12" class="mr-1" />
                {{ a.uploader.name }}
              </span>
              <span class="d-inline-flex align-center">
                <v-icon icon="mdi-calendar" size="12" class="mr-1" />
                {{ formatDate(a.created_at) }}
              </span>
            </div>
          </div>
          <v-btn
            :href="a.download_url"
            target="_blank"
            rel="noopener"
            icon="mdi-download"
            variant="text"
            size="small"
            color="primary"
            density="comfortable"
          />
        </div>
      </v-card>
    </div>

    <!-- 依任務分組 -->
    <div v-else class="d-flex flex-column gap-4">
      <v-card
        v-for="group in groupedByTask"
        :key="group.task_id"
        rounded="xl"
        variant="outlined"
      >
        <div class="d-flex align-center justify-space-between pa-4 pb-2">
          <div class="d-flex align-center gap-2">
            <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
            <span class="text-body-2 font-weight-semibold">{{ group.task_name }}</span>
          </div>
          <v-chip size="x-small" variant="tonal" color="primary">
            {{ group.attachments.length }} 個檔案
          </v-chip>
        </div>
        <v-divider />
        <v-list density="compact">
          <v-list-item
            v-for="a in group.attachments"
            :key="a.id"
            :href="a.download_url"
            target="_blank"
            rel="noopener"
          >
            <template #prepend>
              <v-avatar
                :color="iconFor(a.mime_type, a.original_name).color"
                variant="tonal"
                size="32"
                rounded="lg"
              >
                <v-icon :icon="iconFor(a.mime_type, a.original_name).icon" size="16" />
              </v-avatar>
            </template>
            <v-list-item-title class="text-body-2">{{ a.original_name }}</v-list-item-title>
            <v-list-item-subtitle class="text-caption">
              {{ a.size_human }} · {{ a.uploader?.name ?? '未知' }} · {{ formatDate(a.created_at) }}
            </v-list-item-subtitle>
            <template #append>
              <v-icon icon="mdi-download" size="18" color="grey" />
            </template>
          </v-list-item>
        </v-list>
      </v-card>
    </div>

    <div v-if="nextCursor" class="d-flex justify-center mt-4">
      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        :loading="loading"
        prepend-icon="mdi-chevron-down"
        @click="loadMore"
      >
        載入更多
      </v-btn>
    </div>
  </div>
</template>

<style scoped>
.pms-att-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
}
</style>
