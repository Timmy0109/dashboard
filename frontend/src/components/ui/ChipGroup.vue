<script setup lang="ts">
// ChipGroup — macOS toolbar 風格的 segment 切換群
// Items 可選帶 count，會以小 chip 顯示在 label 旁
interface Item {
  value: unknown
  label: string
  count?: number
}

defineProps<{
  modelValue: unknown
  items: Item[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: unknown]
}>()

function pick(value: unknown) {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="pms-chip-group d-inline-flex align-center pa-1 rounded-pill">
    <button
      v-for="it in items"
      :key="String(it.value)"
      type="button"
      class="pms-chip-group__btn"
      :class="{ 'pms-chip-group__btn--active': modelValue === it.value }"
      @click="pick(it.value)"
    >
      <span class="text-body-2">{{ it.label }}</span>
      <span
        v-if="typeof it.count === 'number'"
        class="pms-chip-group__count text-caption pms-tnum"
      >
        {{ it.count }}
      </span>
    </button>
  </div>
</template>

<style scoped>
.pms-chip-group {
  background-color: rgba(var(--v-theme-on-surface), 0.06);
  gap: 2px;
}
.pms-chip-group__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  background: transparent;
  border: none;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.pms-chip-group__btn:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.06);
}
.pms-chip-group__btn--active {
  background-color: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-primary));
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
  font-weight: 600;
}
.pms-chip-group__count {
  background-color: rgba(var(--v-theme-on-surface), 0.08);
  padding: 0 6px;
  border-radius: 999px;
  min-width: 18px;
  text-align: center;
}
.pms-chip-group__btn--active .pms-chip-group__count {
  background-color: rgba(var(--v-theme-primary), 0.12);
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
