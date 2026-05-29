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
    @update:model-value="(v) => emit('update:modelValue', v)"
  >
    <v-tab
      v-for="it in items"
      :key="String(it.value)"
      :value="it.value"
    >
      <span>{{ it.label }}</span>
      <v-chip
        v-if="typeof it.count === 'number'"
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
</style>
