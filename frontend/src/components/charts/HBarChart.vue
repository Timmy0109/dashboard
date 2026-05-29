<script setup lang="ts">
// HBarChart — 純 CSS 橫向 bar：左 label 80px / 中 bar / 右數字
import { computed } from 'vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface HBarDatum {
  label: string
  value: number
  color?: string
}

const props = withDefaults(
  defineProps<{
    data: HBarDatum[]
    max?: number
  }>(),
  {
    max: undefined,
  },
)

const effectiveMax = computed(() => {
  if (props.max && props.max > 0) return props.max
  const m = Math.max(...props.data.map((d) => d.value), 0)
  return m > 0 ? m : 1
})

function widthPct(v: number) {
  return Math.max(0, Math.min(100, (v / effectiveMax.value) * 100))
}

const hasData = computed(() => props.data.length > 0)
</script>

<template>
  <EmptyState
    v-if="!hasData"
    icon="mdi-chart-bar"
    title="尚無資料"
    sub="目前沒有可顯示的數據"
  />
  <div v-else class="pms-hbar d-flex flex-column">
    <div
      v-for="(item, i) in data"
      :key="i"
      class="pms-hbar__row d-flex align-center"
    >
      <div
        class="pms-hbar__label text-body-2 text-medium-emphasis text-truncate"
        :title="item.label"
      >
        {{ item.label }}
      </div>
      <div class="pms-hbar__track flex-grow-1">
        <div
          class="pms-hbar__fill"
          :style="{
            width: widthPct(item.value) + '%',
            backgroundColor: item.color || 'rgb(var(--v-theme-primary))',
          }"
        />
      </div>
      <div
        class="pms-hbar__value text-body-2 font-weight-semibold text-end"
        style="font-variant-numeric: tabular-nums"
      >
        {{ item.value }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.pms-hbar__row {
  gap: 12px;
  padding: 6px 0;
}
.pms-hbar__label {
  width: 80px;
  flex-shrink: 0;
}
.pms-hbar__track {
  height: 8px;
  border-radius: 4px;
  background-color: rgba(var(--v-theme-on-surface), 0.08);
  overflow: hidden;
}
.pms-hbar__fill {
  height: 100%;
  border-radius: 4px;
  transition: width 280ms ease-out;
}
.pms-hbar__value {
  width: 48px;
  flex-shrink: 0;
}
</style>
