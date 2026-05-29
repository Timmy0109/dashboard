<script setup lang="ts">
// ProjectsToolbar — 搜尋 + 狀態 ChipGroup filter + view toggle（表格 / 卡片）
import ChipGroup from '@/components/ui/ChipGroup.vue'

interface StatusOption { value: string; label: string; count: number }

defineProps<{
  search: string
  status: string
  view: 'table' | 'card'
  statusOptions: StatusOption[]
}>()

const emit = defineEmits<{
  'update:search': [v: string]
  'update:status': [v: string]
  'update:view': [v: 'table' | 'card']
}>()
</script>

<template>
  <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-3">
    <div class="d-flex align-center gap-3 flex-wrap flex-grow-1">
      <v-text-field
        :model-value="search"
        prepend-inner-icon="mdi-magnify"
        placeholder="搜尋專案名稱 / 編號 / 負責人..."
        variant="outlined"
        density="compact"
        hide-details
        rounded="lg"
        style="max-width: 320px; min-width: 220px"
        @update:model-value="emit('update:search', $event)"
      />
      <ChipGroup
        :model-value="status"
        :items="statusOptions"
        @update:model-value="emit('update:status', $event as string)"
      />
    </div>

    <v-btn-toggle
      :model-value="view"
      mandatory
      density="compact"
      rounded="lg"
      color="primary"
      @update:model-value="emit('update:view', $event as 'table' | 'card')"
    >
      <v-btn value="table" size="small" icon="mdi-table" title="表格檢視" />
      <v-btn value="card" size="small" icon="mdi-view-grid-outline" title="卡片檢視" />
    </v-btn-toggle>
  </div>
</template>
