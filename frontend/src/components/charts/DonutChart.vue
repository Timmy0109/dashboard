<script setup lang="ts">
// DonutChart — 純 SVG 圓環，中央顯示總數 + 副標
import { computed } from 'vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface DonutDatum {
  label: string
  value: number
  color: string
}

const props = withDefaults(
  defineProps<{
    data: DonutDatum[]
    size?: number
    stroke?: number
    centerLabel?: string
    centerSub?: string
  }>(),
  {
    size: 200,
    stroke: 24,
    centerLabel: '',
    centerSub: '',
  },
)

const radius = computed(() => (props.size - props.stroke) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const total = computed(() => props.data.reduce((sum, d) => sum + (d.value || 0), 0))

interface Segment {
  color: string
  dashArray: string
  dashOffset: number
  label: string
  value: number
}

const segments = computed<Segment[]>(() => {
  if (total.value <= 0) return []
  let accumulated = 0
  return props.data
    .filter((d) => d.value > 0)
    .map((d) => {
      const fraction = d.value / total.value
      const len = fraction * circumference.value
      const seg: Segment = {
        color: d.color,
        dashArray: `${len} ${circumference.value - len}`,
        dashOffset: -accumulated,
        label: d.label,
        value: d.value,
      }
      accumulated += len
      return seg
    })
})

const hasData = computed(() => props.data.length > 0 && total.value > 0)
const centerFallback = computed(() => (props.centerLabel ? props.centerLabel : String(total.value)))
</script>

<template>
  <EmptyState
    v-if="!hasData"
    icon="mdi-chart-donut"
    title="尚無資料"
    sub="目前沒有可顯示的數據"
  />
  <div
    v-else
    class="pms-donut d-inline-flex align-center justify-center position-relative"
    :style="{ width: size + 'px', height: size + 'px' }"
  >
    <svg
      :width="size"
      :height="size"
      :viewBox="`0 0 ${size} ${size}`"
      role="img"
      :aria-label="centerLabel || '圓環圖'"
    >
      <!-- 背景軌道 -->
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        :stroke-width="stroke"
        stroke="rgba(var(--v-theme-on-surface), 0.08)"
      />
      <!-- 各段 -->
      <circle
        v-for="(seg, i) in segments"
        :key="i"
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        :stroke="seg.color"
        :stroke-width="stroke"
        :stroke-dasharray="seg.dashArray"
        :stroke-dashoffset="seg.dashOffset"
        stroke-linecap="butt"
        :transform="`rotate(-90 ${size / 2} ${size / 2})`"
      >
        <title>{{ seg.label }}: {{ seg.value }}</title>
      </circle>
    </svg>
    <div class="pms-donut__center d-flex flex-column align-center">
      <div class="text-h5 font-weight-bold" style="font-variant-numeric: tabular-nums">
        {{ centerFallback }}
      </div>
      <div v-if="centerSub" class="text-caption text-medium-emphasis mt-1">
        {{ centerSub }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.pms-donut__center {
  position: absolute;
  inset: 0;
  pointer-events: none;
  justify-content: center;
}
</style>
