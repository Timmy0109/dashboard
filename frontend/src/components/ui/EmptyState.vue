<script setup lang="ts">
// EmptyState — 灰圓圈 icon + 標題 + 副標，用於空清單、無搜尋結果
withDefaults(defineProps<{
  icon: string
  title: string
  sub?: string
}>(), {
  sub: '',
})
</script>

<template>
  <div class="pms-empty d-flex flex-column align-center text-center pa-6">
    <div class="pms-empty__icon-circle mb-4">
      <v-icon :icon="icon" size="32" class="text-medium-emphasis" />
    </div>
    <div class="text-subtitle-1 font-weight-semibold mb-1">{{ title }}</div>
    <div v-if="sub" class="text-body-2 text-medium-emphasis" style="max-width: 360px">
      {{ sub }}
    </div>
    <slot name="action" />
  </div>
</template>

<style scoped>
.pms-empty__icon-circle {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background-color: rgba(var(--v-theme-on-surface), 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
