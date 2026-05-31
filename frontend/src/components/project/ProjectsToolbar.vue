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
  <div class="d-flex align-center w-100 justify-space-between flex-wrap gap-3">
    <div class="d-flex align-center gap-4 flex-wrap flex-grow-1">
      <v-text-field
        :model-value="search"
        prepend-inner-icon="mdi-magnify"
        placeholder="搜尋專案名稱 / 負責人..."
        variant="outlined"
        density="compact"
        hide-details
        rounded="lg"
        @update:model-value="emit('update:search', $event)"
        style="max-width: 260px"
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
      color="primary"
      @update:model-value="emit('update:view', $event as 'table' | 'card')"
    >
      <v-btn value="table" icon="mdi-table" title="表格檢視" />
      <v-btn value="card" icon="mdi-view-grid-outline" title="卡片檢視" />
    </v-btn-toggle>
  </div>
</template>
