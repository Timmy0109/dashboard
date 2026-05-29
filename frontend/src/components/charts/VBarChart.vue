<script setup lang="ts">
// VBarChart — vue-chartjs 直立 bar，含 hover tooltip
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  BarElement,
  CategoryScale,
  LinearScale,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'
import { useTheme } from 'vuetify'
import EmptyState from '@/components/ui/EmptyState.vue'

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale)

interface VBarDatum {
  label: string
  value: number
}

const props = withDefaults(
  defineProps<{
    data: VBarDatum[]
    height?: number
    color?: string
  }>(),
  {
    height: 200,
    color: 'primary',
  },
)

const theme = useTheme()

const themeColors = computed(() => theme.global.current.value.colors)

function resolveColor(name: string) {
  const c = themeColors.value as Record<string, string>
  return c[name] || name
}

const hasData = computed(() => props.data.length > 0)

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: props.data.map((d) => d.label),
  datasets: [
    {
      label: '數量',
      data: props.data.map((d) => d.value),
      backgroundColor: resolveColor(props.color),
      borderRadius: 6,
      borderSkipped: false,
      maxBarThickness: 36,
    },
  ],
}))

const chartOptions = computed<ChartOptions<'bar'>>(() => {
  const onSurface = themeColors.value['on-surface'] || '#000'
  const surface = themeColors.value.surface || '#fff'
  const gridColor = `${onSurface}1F` // ~12% alpha
  const tickColor = `${onSurface}99`
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: surface,
        titleColor: onSurface,
        bodyColor: onSurface,
        borderColor: gridColor,
        borderWidth: 1,
        padding: 8,
        displayColors: false,
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: tickColor, font: { size: 11 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: tickColor, font: { size: 11 } },
      },
    },
  }
})
</script>

<template>
  <EmptyState
    v-if="!hasData"
    icon="mdi-chart-bar"
    title="尚無資料"
    sub="目前沒有可顯示的數據"
  />
  <div v-else :style="{ height: height + 'px', position: 'relative' }">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
