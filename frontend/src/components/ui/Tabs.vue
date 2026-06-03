<script setup lang="ts">
// Tabs — 薄包裝 <v-tabs>，支援每個 tab 帶 count chip
interface TabItem {
  value: unknown
  label: string
  count?: number
}

defineProps<{
  modelValue: unknown
  items: TabItem[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: unknown]
}>()
</script>

<template>
  <v-tabs
    :model-value="modelValue"
    color="primary"
    density="comfortable"
    class="pms-tabs"
    slider-color="primary"
    @update:model-value="(v) => emit('update:modelValue', v)"
  >
    <v-tab
      v-for="it in items"
      :key="String(it.value)"
      :value="it.value"
      class="pms-tab"
    >
      <span>{{ it.label }}</span>
      <v-chip
        v-if="typeof it.count === 'number' && it.count > 0"
        size="x-small"
        variant="tonal"
        class="ml-2 pms-tnum"
        density="compact"
      >
        {{ it.count }}
      </v-chip>
    </v-tab>
  </v-tabs>
</template>

<style scoped>
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.pms-tabs :deep(.v-slide-group__content) {
  gap: 4px;
}
.pms-tab {
  text-transform: none;
  letter-spacing: 0;
  font-weight: 500;
  border-radius: 10px 10px 0 0;
  transition: background-color 150ms ease, color 150ms ease;
  min-width: auto;
  padding: 0 14px;
}
.pms-tab:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
}
.pms-tab.v-tab--selected {
  font-weight: 600;
  background-color: rgba(var(--v-theme-primary), 0.08);
}
.pms-tabs :deep(.v-tab__slider) {
  height: 3px;
  border-radius: 2px;
}</style>
