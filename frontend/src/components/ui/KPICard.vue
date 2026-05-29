<script setup lang="ts">
// KPICard — 統計卡片：頂部彩條 + icon + 趨勢 + 大數字 + 副標
import { computed } from 'vue'

type Accent = 'primary' | 'success' | 'warning' | 'error' | 'info'

const props = withDefaults(defineProps<{
  label: string
  value: number | string
  icon: string
  iconColor: string
  iconBg?: string
  trend?: string
  sub?: string
  accent?: Accent
  to?: string
}>(), {
  accent: 'primary',
})

// Vuetify 主題色 token；保留半透明背景 fallback
const accentVar = computed(() => `rgb(var(--v-theme-${props.accent}))`)
const iconBgStyle = computed(() => props.iconBg
  ? { backgroundColor: props.iconBg }
  : { backgroundColor: `rgba(var(--v-theme-${props.iconColor}), 0.12)` })

const trendIsUp = computed(() => props.trend?.startsWith('+'))
const trendIsDown = computed(() => props.trend?.startsWith('-'))
const trendColor = computed(() =>
  trendIsUp.value ? 'success' : trendIsDown.value ? 'error' : 'grey')
</script>

<template>
  <v-card
    class="pms-kpi-card position-relative overflow-hidden"
    rounded="xl"
    :to="to"
    :ripple="!!to"
    :hover="!!to"
  >
    <!-- accent bar -->
    <div class="pms-kpi-card__accent" :style="{ background: accentVar }" />

    <v-card-text class="pa-5 pt-6">
      <div class="d-flex align-start justify-space-between mb-3">
        <div class="pms-kpi-card__icon-box" :style="iconBgStyle">
          <v-icon :icon="icon" :color="iconColor" size="20" />
        </div>
        <v-chip
          v-if="trend"
          size="x-small"
          :color="trendColor"
          variant="tonal"
          density="compact"
        >
          {{ trend }}
        </v-chip>
      </div>

      <div class="text-h5 font-weight-bold pms-tnum">{{ value }}</div>
      <div class="text-body-2 text-medium-emphasis mt-1">{{ label }}</div>
      <div v-if="sub" class="text-caption text-disabled mt-1">{{ sub }}</div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.pms-kpi-card {
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pms-kpi-card:hover {
  transform: translateY(-1px);
}
.pms-kpi-card__accent {
  position: absolute;
  inset: 0 0 auto 0;
  height: 3px;
}
.pms-kpi-card__icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
