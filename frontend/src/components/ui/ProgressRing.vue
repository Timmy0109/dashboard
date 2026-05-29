<script setup lang="ts">
// ProgressRing — SVG 環狀進度，中央顯示百分比 + 副標
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  value: number          // 0 - 100
  size?: number
  stroke?: number        // viewBox (0..36) 上的 stroke 寬度
  color?: string         // 任意 CSS color；預設用主題 primary
  label?: string
  sub?: string
}>(), {
  size: 120,
  stroke: 2.5,
  color: 'rgb(var(--v-theme-primary))',
  sub: '整體進度',
})

const clamped = computed(() => Math.max(0, Math.min(100, props.value)))
const dasharray = computed(() => `${clamped.value} ${100 - clamped.value}`)
</script>

<template>
  <div class="pms-ring position-relative" :style="{ width: size + 'px', height: size + 'px' }">
    <svg :width="size" :height="size" viewBox="0 0 36 36" style="transform: rotate(-90deg)">
      <!-- track -->
      <circle
        cx="18" cy="18" r="15.9155"
        fill="none"
        stroke="rgb(var(--v-border-color))"
        :stroke-opacity="0.25"
        :stroke-width="stroke"
      />
      <!-- progress -->
      <circle
        cx="18" cy="18" r="15.9155"
        fill="none"
        :stroke="color"
        :stroke-width="stroke"
        :stroke-dasharray="dasharray"
        stroke-linecap="round"
        style="transition: stroke-dasharray 0.4s ease"
      />
    </svg>
    <div class="pms-ring__center">
      <span class="text-h6 font-weight-bold pms-tnum">
        {{ label ?? `${clamped}%` }}
      </span>
      <span v-if="sub" class="text-caption text-medium-emphasis">{{ sub }}</span>
    </div>
  </div>
</template>

<style scoped>
.pms-ring__center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  pointer-events: none;
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
